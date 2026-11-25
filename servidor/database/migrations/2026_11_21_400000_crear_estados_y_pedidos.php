<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las cinco tablas del pedido: el catálogo estados_pedido, pedidos, pedido_servicios, pedido_direcciones y
 * pedido_eventos (openspec/changes/resumen-y-confirmacion/design.md, "Tablas y migración";
 * vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * - estados_pedido es un catálogo, en la lista sin_plaza de config/modelo.php. La semilla son los cinco estados del
 *   usuario (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #12). El estado previo al pago y los demás internos
 *   los fija DD-123: hasta entonces existen solo en las pruebas.
 * - pedidos lleva la plaza de su lavandería, con la llave compuesta (id_lavanderias, id_plazas), y el único
 *   (id_pedidos, id_plazas) al que apuntan las llaves compuestas de sus hijas (modelo-E06). El folio es único por
 *   plaza y queda vacío hasta Confirmado: PostgreSQL admite varios vacíos en un único (design.md, decisión 6).
 *   id_repartidores nace opcional y sin llave: la compuesta hacia repartidores la agrega el cambio repartidor
 *   (ADR-0016, regla 4).
 * - Las tres hijas llevan la llave compuesta (id_pedidos, id_plazas) hacia pedidos. pedido_direcciones lleva además la
 *   compuesta (id_direcciones, id_plazas) hacia direcciones: la base rechaza copiar una dirección de otra plaza o sin
 *   plaza (direcciones-E11 y E12).
 * - pedidos no se borra (BEFORE DELETE) y pedido_eventos no se borra ni se edita (BEFORE DELETE y BEFORE UPDATE), con
 *   la función impedir_cambio() (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, "historial completo"; ADR-0016, regla 6).
 *
 * Toda llave foránea lleva su índice y ON DELETE RESTRICT (modelo-E09 y modelo-E10).
 */
return new class extends Migration
{
    /**
     * Los cinco estados que ve el usuario, en su orden: código, nombre para el usuario.
     */
    private const ESTADOS_DEL_USUARIO = [
        'confirmado' => 'Confirmado',
        'recolectado' => 'Recolectado',
        'en_proceso' => 'En proceso',
        'listo' => 'Listo',
        'entregado' => 'Entregado',
    ];

    public function up(): void
    {
        $this->crearEstados();
        $this->crearPedidos();
        $this->crearServicios();
        $this->crearDirecciones();
        $this->crearEventos();
    }

    private function crearEstados(): void
    {
        Schema::create('estados_pedido', function (Blueprint $tabla) {
            $tabla->id('id_estados_pedido');
            // Único, como roles.codigo (C-235): es como el código busca un estado.
            $tabla->string('codigo')->unique();
            $tabla->string('nombre_usuario');
            $tabla->smallInteger('orden');
            $tabla->boolean('visible_usuario');
            $tabla->timestampsTz();
        });

        $ahora = now();
        $orden = 1;

        foreach (self::ESTADOS_DEL_USUARIO as $codigo => $nombre) {
            DB::table('estados_pedido')->insert([
                'codigo' => $codigo,
                'nombre_usuario' => $nombre,
                'orden' => $orden++,
                'visible_usuario' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function crearPedidos(): void
    {
        Schema::create('pedidos', function (Blueprint $tabla) {
            $tabla->id('id_pedidos');
            $tabla->ulid('id_publico')->unique();
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // Vacío hasta Confirmado. El formato es DD-101.
            $tabla->string('folio')->nullable();
            // Quien hizo el pedido: solo esa cuenta lo ve (confirmacion-E12).
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            $tabla->unsignedBigInteger('id_lavanderias');
            // Sin llave todavía: la compuesta con la plaza la agrega repartidor, que crea repartidores.
            $tabla->unsignedBigInteger('id_repartidores')->nullable();
            // El estado actual; su historia está en pedido_eventos (ADR-0016, segunda excepción).
            $tabla->foreignId('id_estados_pedido')->index()->constrained('estados_pedido', 'id_estados_pedido')
                ->restrictOnDelete();
            // Instantes absolutos en UTC, como los da App\Agenda\Agenda::validar().
            $tabla->timestampTz('ventana_inicio');
            $tabla->timestampTz('ventana_fin');
            // En pesos (ADR-0016, regla 7). Vacíos al crear el pedido: el estimado espera DD-012 y DD-010; el final,
            // el peso (DD-012); el envío, DD-019 y DD-025.
            $tabla->decimal('monto_estimado', 12, 2)->nullable();
            $tabla->decimal('monto_final', 12, 2)->nullable();
            $tabla->decimal('envio', 12, 2)->nullable();
            // Vacía hasta que exista comisiones (administracion, DD-020).
            $tabla->decimal('comision_porcentaje', 5, 2)->nullable();
            // Lo llena la confirmación, cuando la pasarela confirma el pago (C-188).
            $tabla->timestampTz('confirmado_en')->nullable();
            // DD-016.
            $tabla->timestampTz('cancelado_en')->nullable();
            $tabla->timestampsTz();
            $tabla->unique(['id_plazas', 'folio']);
            // Al que apuntan las llaves compuestas de sus hijas (modelo-E06).
            $tabla->unique(['id_pedidos', 'id_plazas']);
            $tabla->index(['id_lavanderias', 'id_plazas']);
            $tabla->foreign(['id_lavanderias', 'id_plazas'])
                ->references(['id_lavanderias', 'id_plazas'])
                ->on('lavanderias')
                ->restrictOnDelete();
        });

        DB::statement('alter table pedidos add constraint pedidos_ventana_check check (ventana_fin > ventana_inicio)');
        DB::statement(
            'alter table pedidos add constraint pedidos_montos_check
                check (monto_estimado >= 0 and monto_final >= 0 and envio >= 0)',
        );
        DB::statement(
            'alter table pedidos add constraint pedidos_comision_porcentaje_check
                check (comision_porcentaje between 0 and 100)',
        );
        DB::unprepared(
            'create trigger pedidos_impedir_borrado
                before delete on pedidos for each row execute function impedir_cambio();',
        );
    }

    /**
     * Los servicios del pedido, con el precio copiado de la lavandería al crearlo (ADR-0016, primera excepción). Uno o
     * varios por pedido es DD-098.
     */
    private function crearServicios(): void
    {
        Schema::create('pedido_servicios', function (Blueprint $tabla) {
            $tabla->id('id_pedido_servicios');
            $tabla->unsignedBigInteger('id_pedidos');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->foreignId('id_tipos_servicio')->index()->constrained('tipos_servicio', 'id_tipos_servicio')
                ->restrictOnDelete();
            $tabla->foreignId('id_unidades_medida')->index()->constrained('unidades_medida', 'id_unidades_medida')
                ->restrictOnDelete();
            // La estimada espera DD-012; la real, el peso.
            $tabla->decimal('cantidad_estimada', 8, 2)->nullable();
            $tabla->decimal('cantidad_real', 8, 2)->nullable();
            $tabla->decimal('precio_unitario', 12, 2);
            // Los subtotales no se capturan: los calcula la base (modelo-de-datos.md).
            $tabla->decimal('monto_estimado', 12, 2)->nullable()->storedAs('cantidad_estimada * precio_unitario');
            $tabla->decimal('monto_real', 12, 2)->nullable()->storedAs('cantidad_real * precio_unitario');
            $tabla->timestampsTz();
            $this->llaveAlPedido($tabla);
        });

        DB::statement(
            'alter table pedido_servicios add constraint pedido_servicios_precio_unitario_check
                check (precio_unitario >= 0)',
        );
        DB::statement(
            'alter table pedido_servicios add constraint pedido_servicios_cantidades_check
                check (cantidad_estimada > 0 and cantidad_real > 0)',
        );
    }

    /**
     * La copia de la recogida y de la entrega al crear el pedido (ADR-0016, primera excepción): si la dirección
     * guardada cambia o se borra, el pedido conserva la suya (confirmacion-E14).
     */
    private function crearDirecciones(): void
    {
        Schema::create('pedido_direcciones', function (Blueprint $tabla) {
            $tabla->id('id_pedido_direcciones');
            $tabla->unsignedBigInteger('id_pedidos');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->string('tipo');
            // De cuál dirección salió la copia.
            $tabla->unsignedBigInteger('id_direcciones');
            $tabla->string('alias')->nullable();
            $tabla->string('calle')->nullable();
            $tabla->string('numero_exterior')->nullable();
            $tabla->string('numero_interior')->nullable();
            $tabla->string('colonia');
            $tabla->string('codigo_postal')->nullable();
            $tabla->string('municipio')->nullable();
            $tabla->text('referencias')->nullable();
            $tabla->geography('ubicacion', subtype: 'point', srid: 4326);
            $tabla->timestampsTz();
            $tabla->unique(['id_pedidos', 'tipo']);
            $this->llaveAlPedido($tabla);
            // La dirección de otra plaza que la del pedido, o sin plaza, no entra (direcciones-E11 y E12).
            $tabla->index(['id_direcciones', 'id_plazas']);
            $tabla->foreign(['id_direcciones', 'id_plazas'])
                ->references(['id_direcciones', 'id_plazas'])
                ->on('direcciones')
                ->restrictOnDelete();
            $tabla->spatialIndex('ubicacion');
        });

        DB::statement(
            "alter table pedido_direcciones add constraint pedido_direcciones_tipo_check
                check (tipo in ('recoleccion', 'entrega'))",
        );
    }

    /**
     * El historial del pedido: cada cambio de estado es una fila, en la misma transacción que el estado actual
     * (ADR-0016, segunda excepción; ADR-0007). Solo se agrega.
     */
    private function crearEventos(): void
    {
        Schema::create('pedido_eventos', function (Blueprint $tabla) {
            $tabla->id('id_pedido_eventos');
            $tabla->unsignedBigInteger('id_pedidos');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->foreignId('id_estados_pedido')->index()->constrained('estados_pedido', 'id_estados_pedido')
                ->restrictOnDelete();
            // Quien hizo el cambio; vacía cuando lo hace el sistema, como la confirmación por el pago.
            $tabla->foreignId('id_usuarios')->nullable()->index()->constrained('usuarios', 'id_usuarios')
                ->restrictOnDelete();
            // Lo usa el rechazo de pedidos-de-la-lavanderia.
            $tabla->text('motivo')->nullable();
            $tabla->timestampTz('ocurrido_en');
            $tabla->timestampsTz();
            $this->llaveAlPedido($tabla);
        });

        DB::unprepared(
            'create trigger pedido_eventos_impedir_borrado
                before delete on pedido_eventos for each row execute function impedir_cambio();
             create trigger pedido_eventos_impedir_edicion
                before update on pedido_eventos for each row execute function impedir_cambio();',
        );
    }

    /**
     * La llave compuesta con la plaza hacia el pedido, con su índice: la base rechaza una hija de otra plaza que su
     * pedido (modelo-E06).
     */
    private function llaveAlPedido(Blueprint $tabla): void
    {
        $tabla->index(['id_pedidos', 'id_plazas']);
        $tabla->foreign(['id_pedidos', 'id_plazas'])
            ->references(['id_pedidos', 'id_plazas'])
            ->on('pedidos')
            ->restrictOnDelete();
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
