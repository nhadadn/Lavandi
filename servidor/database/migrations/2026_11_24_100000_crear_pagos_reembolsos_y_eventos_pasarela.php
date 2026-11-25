<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las tres tablas del cambio pago (openspec/changes/pago/design.md, "Tablas y migración";
 * vault/arquitectura/modelo-de-datos.md, "Dinero"; ADR-0016). Ninguna tabla ni columna nombra a un proveedor de pagos:
 * la que cobró se dice con el valor de la columna pasarela (C-192; modelo-E14).
 *
 * - pagos: el cobro del pedido y, si DD-012 lo pide, su ajuste. Lleva la plaza de su pedido, con la llave compuesta
 *   (id_pedidos, id_plazas) hacia pedidos, y el único (id_pagos, id_plazas) al que apunta reembolsos (modelo-E06).
 *   Ningún dato de tarjeta: lo único que lo liga con la pasarela es id_pasarela_orden, junto con pasarela (pago-E05;
 *   NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3). Un pedido tiene un solo cobro: un intento rechazado se reintenta con
 *   el mismo (pago-E04). No se borra nunca (BEFORE DELETE con impedir_cambio()).
 * - reembolsos: nace vacía; la usan pago-E15 y pago-E16, bloqueados por DD-012, DD-016 y DD-103. Sus estados son los del
 *   modelo de datos. Que el monto no pase de lo pagado mira otra fila: lo valida la transacción que registre el
 *   reembolso (pago-E16). Tampoco se borra.
 * - eventos_pasarela: cada aviso (webhook) de la pasarela, sin plaza (lista sin_plaza de config/modelo.php). El único
 *   (pasarela, id_pasarela_evento) hace que un aviso repetido choque en la base y no se procese dos veces (pago-E10). Su
 *   tipo es el evento ya traducido, sin proveedor: aprobado, rechazado, vencido u otro. Sin trigger: no es una de las
 *   cuatro tablas del historial.
 *
 * pasarela admite conekta, la del contrato (ADR-0010), y simulada, la de local y las pruebas, que el código no usa en
 * ningún otro entorno (design.md, "Decisiones de la implementación"). Si el cliente firma el cambio de pasarela
 * (DD-079), la elegida entra en una migración hacia adelante, con su ADR.
 *
 * Toda llave foránea lleva su índice y ON DELETE RESTRICT (modelo-E09 y modelo-E10).
 */
return new class extends Migration
{
    /** Los valores de la columna pasarela, en pagos y en eventos_pasarela. */
    private const PASARELAS = "('conekta', 'simulada')";

    public function up(): void
    {
        $this->crearPagos();
        $this->crearReembolsos();
        $this->crearEventos();
    }

    private function crearPagos(): void
    {
        Schema::create('pagos', function (Blueprint $tabla) {
            $tabla->id('id_pagos');
            $tabla->ulid('id_publico')->unique();
            $tabla->unsignedBigInteger('id_pedidos');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->string('tipo');
            $tabla->string('metodo');
            // Cuál cobró: conekta mientras el cliente no firme el cambio (DD-079).
            $tabla->string('pasarela');
            // En pesos (ADR-0016, regla 7). El del cobro lo fija el modelo de cobro (pago-E14, DD-012).
            $tabla->decimal('monto', 12, 2);
            $tabla->string('estado')->default('pendiente');
            // La referencia de la orden en la pasarela; vacía hasta crear el checkout (pago-E01).
            $tabla->string('id_pasarela_orden')->nullable();
            // Solo del efectivo (DD-056).
            $tabla->string('referencia_efectivo')->nullable();
            $tabla->timestampTz('vence_en')->nullable();
            // Lo llena el aviso de aprobado (pago-E03).
            $tabla->timestampTz('pagado_en')->nullable();
            $tabla->timestampsTz();
            $tabla->index(['id_pedidos', 'id_plazas']);
            $tabla->foreign(['id_pedidos', 'id_plazas'])
                ->references(['id_pedidos', 'id_plazas'])
                ->on('pedidos')
                ->restrictOnDelete();
            // Una orden no se repite en su pasarela; varias filas sin orden todavía no chocan.
            $tabla->unique(['pasarela', 'id_pasarela_orden']);
            // Al que apunta la llave compuesta de reembolsos (modelo-E06).
            $tabla->unique(['id_pagos', 'id_plazas']);
        });

        DB::statement("alter table pagos add constraint pagos_tipo_check check (tipo in ('cobro', 'ajuste'))");
        DB::statement("alter table pagos add constraint pagos_metodo_check check (metodo in ('tarjeta', 'efectivo'))");
        DB::statement(
            "alter table pagos add constraint pagos_estado_check
                check (estado in ('pendiente', 'pagado', 'vencido', 'fallido', 'cancelado'))",
        );
        DB::statement('alter table pagos add constraint pagos_pasarela_check check (pasarela in '.self::PASARELAS.')');
        DB::statement('alter table pagos add constraint pagos_monto_check check (monto > 0)');
        // Un pago pagado dice cuándo (pago-E03 y pago-E11).
        DB::statement("alter table pagos add constraint pagos_pagado_en_check check (estado <> 'pagado' or pagado_en is not null)");
        // Un solo cobro por pedido; el ajuste de DD-012 va aparte.
        DB::statement("create unique index pagos_un_cobro_por_pedido on pagos (id_pedidos) where tipo = 'cobro'");
        DB::unprepared(
            'create trigger pagos_impedir_borrado
                before delete on pagos for each row execute function impedir_cambio();',
        );
    }

    private function crearReembolsos(): void
    {
        Schema::create('reembolsos', function (Blueprint $tabla) {
            $tabla->id('id_reembolsos');
            $tabla->unsignedBigInteger('id_pagos');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->decimal('monto', 12, 2);
            // Por qué se reembolsa: lo fijan DD-012, DD-016 y DD-103.
            $tabla->text('motivo')->nullable();
            // La pasarela es la de su pago.
            $tabla->string('id_pasarela_reembolso')->nullable();
            $tabla->string('estado');
            $tabla->timestampTz('reembolsado_en')->nullable();
            $tabla->timestampsTz();
            $tabla->index(['id_pagos', 'id_plazas']);
            $tabla->foreign(['id_pagos', 'id_plazas'])
                ->references(['id_pagos', 'id_plazas'])
                ->on('pagos')
                ->restrictOnDelete();
        });

        DB::statement('alter table reembolsos add constraint reembolsos_monto_check check (monto > 0)');
        DB::statement(
            "alter table reembolsos add constraint reembolsos_estado_check
                check (estado in ('pendiente', 'reembolsado', 'fallido'))",
        );
        DB::unprepared(
            'create trigger reembolsos_impedir_borrado
                before delete on reembolsos for each row execute function impedir_cambio();',
        );
    }

    private function crearEventos(): void
    {
        Schema::create('eventos_pasarela', function (Blueprint $tabla) {
            $tabla->id('id_eventos_pasarela');
            $tabla->string('pasarela');
            $tabla->string('id_pasarela_evento');
            $tabla->string('tipo');
            // El aviso como llegó, sin datos de tarjeta (design.md, "Risks / Trade-offs").
            $tabla->jsonb('contenido');
            $tabla->timestampTz('recibido_en');
            // Vacía hasta procesarlo.
            $tabla->timestampTz('procesado_en')->nullable();
            // Por qué falló, o por qué no cambió nada.
            $tabla->text('error')->nullable();
            $tabla->timestampsTz();
            $tabla->unique(['pasarela', 'id_pasarela_evento']);
        });

        DB::statement('alter table eventos_pasarela add constraint eventos_pasarela_pasarela_check check (pasarela in '.self::PASARELAS.')');
        DB::statement(
            "alter table eventos_pasarela add constraint eventos_pasarela_tipo_check
                check (tipo in ('aprobado', 'rechazado', 'vencido', 'otro'))",
        );
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
