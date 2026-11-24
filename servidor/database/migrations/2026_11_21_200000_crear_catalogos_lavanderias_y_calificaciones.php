<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las siete tablas de la lavandería: los catálogos tipos_servicio y unidades_medida, lavanderias, lavanderia_servicios,
 * lavanderia_horarios, lavanderia_fotos y la base de calificaciones (openspec/changes/perfil-de-lavanderia/design.md,
 * "Tablas y migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * Los dos catálogos están en la lista sin_plaza de config/modelo.php. Las otras cinco son de operación y llevan
 * id_plazas no nula con su llave a plazas (plazas-E09). Las cuatro hijas llevan además la llave compuesta
 * (id_lavanderias, id_plazas) hacia lavanderias, para que la base rechace una fila de otra plaza (modelo-E06; DD-134,
 * punto 4, C-234). Toda llave foránea lleva su índice y ON DELETE RESTRICT (modelo-E09 y modelo-E10).
 *
 * calificaciones nace antes que pedidos y repartidores: id_pedidos e id_repartidores entran opcionales y sin llave, y
 * la agregan historial-y-calificacion y repartidor (ADR-0016, regla 4). Ninguna de estas tablas lleva el trigger
 * impedir_cambio(), que es solo del historial del pedido y del dinero.
 */
return new class extends Migration
{
    /**
     * Los tres tipos de servicio del contrato (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #6). Un tipo nuevo,
     * como el calzado, sería una fila, pero es Orden de Cambio (DD-046).
     */
    private const TIPOS_SERVICIO = [
        'estandar' => 'Lavado estándar',
        'planchado' => 'Lavado con planchado',
        'seco' => 'Lavado en seco',
    ];

    /**
     * "Precios por kilo" (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5). La pieza entra solo si DD-010 la
     * pide.
     */
    private const UNIDADES_MEDIDA = [
        'kg' => 'kilo',
    ];

    public function up(): void
    {
        $this->crearCatalogos();
        $this->crearLavanderias();
        $this->crearHijas();
        $this->crearCalificaciones();
    }

    private function crearCatalogos(): void
    {
        Schema::create('tipos_servicio', function (Blueprint $tabla) {
            $tabla->id('id_tipos_servicio');
            // Único, como roles.codigo (C-235): es como el código busca un tipo.
            $tabla->string('codigo')->unique();
            $tabla->string('nombre');
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();
        });

        Schema::create('unidades_medida', function (Blueprint $tabla) {
            $tabla->id('id_unidades_medida');
            // Único, como roles.codigo (C-235).
            $tabla->string('codigo')->unique();
            $tabla->string('nombre');
            $tabla->timestampsTz();
        });

        $ahora = now();

        foreach (self::TIPOS_SERVICIO as $codigo => $nombre) {
            DB::table('tipos_servicio')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        foreach (self::UNIDADES_MEDIDA as $codigo => $nombre) {
            DB::table('unidades_medida')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function crearLavanderias(): void
    {
        Schema::create('lavanderias', function (Blueprint $tabla) {
            $tabla->id('id_lavanderias');
            $tabla->ulid('id_publico')->unique();
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // Quien la administra: la cuenta del socio lavandería.
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            $tabla->string('nombre');
            // Opcionales: el registro del Módulo B #1 no los pide (alta-de-lavanderias, Open Questions 3).
            $tabla->text('descripcion')->nullable();
            $tabla->string('telefono')->nullable();
            // La dirección, que el registro sí pide (alta-lavanderias-E01 y E02).
            $tabla->string('calle');
            $tabla->string('numero');
            $tabla->string('colonia');
            $tabla->string('codigo_postal');
            // PostGIS, SRID 4326 (ADR-0004): la distancia en línea recta de lavanderias-cercanas sale de aquí.
            $tabla->geography('ubicacion', subtype: 'point', srid: 4326);
            // Sin valor por omisión: con qué estado nace un registro de la app lo decide DD-053.
            $tabla->string('estado');
            $tabla->timestampTz('aprobada_en')->nullable();
            $tabla->timestampsTz();
            $tabla->softDeletesTz();
            // Al que apuntan las llaves compuestas de sus hijas (modelo-E06).
            $tabla->unique(['id_lavanderias', 'id_plazas']);
            $tabla->spatialIndex('ubicacion');
        });

        DB::statement(
            "alter table lavanderias add constraint lavanderias_estado_check
                check (estado in ('pendiente', 'activa', 'suspendida', 'baja'))",
        );
    }

    /**
     * Los servicios con su precio, los horarios y las fotos: hijas de una lavandería, siempre de su misma plaza.
     */
    private function crearHijas(): void
    {
        Schema::create('lavanderia_servicios', function (Blueprint $tabla) {
            $tabla->id('id_lavanderia_servicios');
            $tabla->unsignedBigInteger('id_lavanderias');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->foreignId('id_tipos_servicio')->index()->constrained('tipos_servicio', 'id_tipos_servicio')
                ->restrictOnDelete();
            $tabla->foreignId('id_unidades_medida')->index()->constrained('unidades_medida', 'id_unidades_medida')
                ->restrictOnDelete();
            // En pesos (ADR-0016, regla 7). Quién lo fija: DD-010.
            $tabla->decimal('precio', 12, 2);
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();
            $this->llaveALaLavanderia($tabla);
            $tabla->unique(['id_lavanderias', 'id_tipos_servicio']);
        });

        DB::statement('alter table lavanderia_servicios add constraint lavanderia_servicios_precio_check check (precio >= 0)');

        Schema::create('lavanderia_horarios', function (Blueprint $tabla) {
            $tabla->id('id_lavanderia_horarios');
            $tabla->unsignedBigInteger('id_lavanderias');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // El día 1 es el lunes (ISO 8601). Puede haber varias filas por día.
            $tabla->smallInteger('dia_semana');
            // Horas del día, no instantes: se leen en la zona_horaria de la plaza (ADR-0016, regla 7).
            $tabla->time('hora_inicio');
            $tabla->time('hora_fin');
            $tabla->timestampsTz();
            $this->llaveALaLavanderia($tabla);
        });

        DB::statement(
            'alter table lavanderia_horarios add constraint lavanderia_horarios_dia_semana_check
                check (dia_semana between 1 and 7)',
        );
        DB::statement(
            'alter table lavanderia_horarios add constraint lavanderia_horarios_hora_fin_check
                check (hora_fin > hora_inicio)',
        );

        Schema::create('lavanderia_fotos', function (Blueprint $tabla) {
            $tabla->id('id_lavanderia_fotos');
            $tabla->unsignedBigInteger('id_lavanderias');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // Solo la ruta: la foto vive en el almacenamiento privado (ADR-0006).
            $tabla->string('ruta');
            $tabla->unsignedSmallInteger('orden');
            $tabla->timestampsTz();
            $this->llaveALaLavanderia($tabla);
        });
    }

    private function crearCalificaciones(): void
    {
        Schema::create('calificaciones', function (Blueprint $tabla) {
            $tabla->id('id_calificaciones');
            // Sin llave todavía: la compuesta con la plaza la agrega historial-y-calificacion, que crea pedidos.
            $tabla->unsignedBigInteger('id_pedidos')->nullable();
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // Quien calificó. El perfil nunca lo muestra (perfil-lavanderia-E08).
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            // Opcionales los dos: el CHECK exige exactamente uno (perfil-lavanderia-E16).
            $tabla->unsignedBigInteger('id_lavanderias')->nullable();
            // Sin llave todavía: la agrega repartidor, que crea repartidores.
            $tabla->unsignedBigInteger('id_repartidores')->nullable();
            $tabla->smallInteger('estrellas');
            $tabla->text('comentario')->nullable();
            // La fecha de la reseña es created_at (perfil-lavanderia-E08).
            $tabla->timestampsTz();
            $this->llaveALaLavanderia($tabla);
            // Una por pedido y calificado. En PostgreSQL, las filas con el pedido o el calificado vacío no chocan.
            $tabla->unique(['id_pedidos', 'id_lavanderias']);
            $tabla->unique(['id_pedidos', 'id_repartidores']);
        });

        DB::statement(
            'alter table calificaciones add constraint calificaciones_estrellas_check check (estrellas between 1 and 5)',
        );
        DB::statement(
            'alter table calificaciones add constraint calificaciones_calificado_check
                check (num_nonnulls(id_lavanderias, id_repartidores) = 1)',
        );
    }

    /**
     * La llave compuesta con la plaza hacia la lavandería, con su índice: la base rechaza una hija de otra plaza que su
     * lavandería (modelo-E06). Con id_lavanderias vacía, como en una calificación del repartidor, no se revisa.
     */
    private function llaveALaLavanderia(Blueprint $tabla): void
    {
        $tabla->index(['id_lavanderias', 'id_plazas']);
        $tabla->foreign(['id_lavanderias', 'id_plazas'])
            ->references(['id_lavanderias', 'id_plazas'])
            ->on('lavanderias')
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
