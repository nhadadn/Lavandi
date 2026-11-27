<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las tres tablas del cambio repartidor y las dos llaves que faltaban hacia repartidores
 * (openspec/changes/repartidor/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * - repartidores: el socio repartidor del Módulo C. Nombre, teléfono, correo y foto viven en usuarios. La plaza la fija
 *   la administración al darlo de alta (design.md, lectura 2): vacía solo mientras está pendiente, con su CHECK, y por
 *   eso la tabla está en la lista plaza_opcional de config/modelo.php. La baja es borrado suave: sus entregas se
 *   conservan (repartidor-E07). Sin columnas de documentos: la verificación documental está excluida
 *   (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C); si entra por Orden de Cambio, es una tabla nueva.
 * - pedido_asignaciones: cada oferta de un pedido a un repartidor, con su respuesta. Sin único: cada intento es una
 *   fila. Quién crea la oferta es DD-014 (repartidor-E13). Las llaves son compuestas con la plaza: la base rechaza
 *   ofrecer un pedido de una plaza a un repartidor de otra, o a uno pendiente sin plaza (modelo-E06).
 * - evidencias: la ruta de la foto de evidencia en el almacenamiento privado (ADR-0006), ligada al pedido y a quien la
 *   tomó. Si es obligatoria y en qué momentos es DD-110 (repartidor-E24).
 *
 * Y las llaves compuestas (id_repartidores, id_plazas) de pedidos y de calificaciones, que nacieron antes que
 * repartidores con la columna opcional y sin llave (ADR-0016, regla 4). No cambia sus columnas ni sus datos: con la
 * columna vacía, la llave no se revisa.
 *
 * Ninguna lleva el trigger impedir_cambio(), que es solo de pedidos, pedido_eventos, pagos y reembolsos. Toda llave
 * foránea lleva su índice y ON DELETE RESTRICT (modelo-E09 y modelo-E10).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->crearRepartidores();
        $this->crearAsignaciones();
        $this->crearEvidencias();
        $this->llaveAlRepartidor('pedidos');
        $this->llaveAlRepartidor('calificaciones');
    }

    private function crearRepartidores(): void
    {
        Schema::create('repartidores', function (Blueprint $tabla) {
            $tabla->id('id_repartidores');
            $tabla->ulid('id_publico')->unique();
            // Vacía solo mientras el registro está pendiente: la fija el alta en el panel.
            $tabla->foreignId('id_plazas')->nullable()->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // Una cuenta es a lo más un repartidor (el diagrama del modelo: usuarios ||--o| repartidores).
            $tabla->foreignId('id_usuarios')->unique()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            // Texto libre (design.md, lectura 3): una lista obligaría a inventar sus valores.
            $tabla->string('tipo_vehiculo');
            // El registro de la app nace pendiente, sin acceso a pedidos (repartidor-E01 y E03).
            $tabla->string('estado')->default('pendiente');
            // La hora del alta en el panel (repartidor-E05).
            $tabla->timestampTz('aprobado_en')->nullable();
            $tabla->timestampsTz();
            $tabla->softDeletesTz();
            // Al que apuntan las llaves compuestas de sus hijas (modelo-E06).
            $tabla->unique(['id_repartidores', 'id_plazas']);
        });

        DB::statement(
            "alter table repartidores add constraint repartidores_estado_check
                check (estado in ('pendiente', 'activo', 'suspendido', 'baja'))",
        );
        DB::statement(
            "alter table repartidores add constraint repartidores_plaza_check
                check (estado = 'pendiente' or id_plazas is not null)",
        );
    }

    private function crearAsignaciones(): void
    {
        Schema::create('pedido_asignaciones', function (Blueprint $tabla) {
            $tabla->id('id_pedido_asignaciones');
            $tabla->unsignedBigInteger('id_pedidos');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->unsignedBigInteger('id_repartidores');
            $tabla->string('estado');
            // El motivo del rechazo (repartidor-E16).
            $tabla->text('motivo')->nullable();
            // La hora de la aceptación o del rechazo. created_at es la hora en que se ofreció.
            $tabla->timestampTz('respondido_en')->nullable();
            $tabla->timestampsTz();
            $tabla->index(['id_pedidos', 'id_plazas']);
            $tabla->foreign(['id_pedidos', 'id_plazas'])
                ->references(['id_pedidos', 'id_plazas'])
                ->on('pedidos')
                ->restrictOnDelete();
            $tabla->index(['id_repartidores', 'id_plazas']);
            $tabla->foreign(['id_repartidores', 'id_plazas'])
                ->references(['id_repartidores', 'id_plazas'])
                ->on('repartidores')
                ->restrictOnDelete();
        });

        DB::statement(
            "alter table pedido_asignaciones add constraint pedido_asignaciones_estado_check
                check (estado in ('ofrecido', 'aceptado', 'rechazado'))",
        );
        // Ofrecida, todavía sin respuesta; aceptada o rechazada, con la hora de su respuesta.
        DB::statement(
            "alter table pedido_asignaciones add constraint pedido_asignaciones_respuesta_check
                check ((estado = 'ofrecido') = (respondido_en is null))",
        );
        // El rechazo queda con su motivo, que no está vacío (repartidor-E16 y E17).
        DB::statement(
            "alter table pedido_asignaciones add constraint pedido_asignaciones_motivo_check
                check (estado <> 'rechazado' or (motivo is not null and btrim(motivo) <> ''))",
        );
    }

    private function crearEvidencias(): void
    {
        Schema::create('evidencias', function (Blueprint $tabla) {
            $tabla->id('id_evidencias');
            $tabla->unsignedBigInteger('id_pedidos');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->string('tipo');
            // Solo la ruta: la foto vive en el almacenamiento privado (ADR-0006).
            $tabla->string('ruta');
            // Quien la tomó.
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            $tabla->timestampTz('tomada_en');
            $tabla->timestampsTz();
            $tabla->index(['id_pedidos', 'id_plazas']);
            $tabla->foreign(['id_pedidos', 'id_plazas'])
                ->references(['id_pedidos', 'id_plazas'])
                ->on('pedidos')
                ->restrictOnDelete();
        });

        DB::statement(
            "alter table evidencias add constraint evidencias_tipo_check check (tipo in ('recoleccion', 'entrega'))",
        );
    }

    /**
     * La llave compuesta con la plaza hacia el repartidor, con su índice, en una tabla que nació antes que repartidores:
     * la base rechaza un repartidor de otra plaza que la de la fila (modelo-E06).
     */
    private function llaveAlRepartidor(string $tabla): void
    {
        Schema::table($tabla, function (Blueprint $tabla) {
            $tabla->index(['id_repartidores', 'id_plazas']);
            $tabla->foreign(['id_repartidores', 'id_plazas'])
                ->references(['id_repartidores', 'id_plazas'])
                ->on('repartidores')
                ->restrictOnDelete();
        });
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
