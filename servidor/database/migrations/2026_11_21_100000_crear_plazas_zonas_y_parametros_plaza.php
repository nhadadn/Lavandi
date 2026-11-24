<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La dimensión de plaza: plazas, zonas y parametros_plaza (openspec/changes/plazas-y-zonas/design.md, "Tablas y
 * migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * Una plaza es un área operativa formada por zonas, no una unidad política (vault/arquitectura/modelo-multi-plaza.md).
 * plazas está en la lista sin_plaza de config/modelo.php; zonas y parametros_plaza llevan su plaza, no nula.
 *
 * Nace sin filas: una plaza se abre cargando un archivo de datos, sin migraciones (plazas-E08), y las del territorio
 * inicial esperan DD-093 y DD-094. parametros_plaza nace vacía: cada clave la agrega el cambio que la usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plazas', function (Blueprint $tabla) {
            $tabla->id('id_plazas');
            // Único: recargar el archivo de una plaza la reconoce por su nombre y no la duplica (plazas-E08).
            $tabla->string('nombre')->unique();
            // Un nombre de la base de husos IANA. Que sea válido lo revisa el modelo (plazas-E02).
            $tabla->string('zona_horaria');
            // Sin valor por omisión: quien da de alta la plaza dice si ya da servicio al público (plazas-E06).
            $tabla->boolean('activa');
            $tabla->timestampsTz();
        });

        Schema::create('zonas', function (Blueprint $tabla) {
            $tabla->id('id_zonas');
            $tabla->foreignId('id_plazas')->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->string('nombre');
            // PostGIS, SRID 4326 (ADR-0004; ADR-0009, decisión 5). La fuente del polígono es DD-094.
            $tabla->geography('poligono', subtype: 'multipolygon', srid: 4326);
            // La guarda el modelo de datos; la resolución de un punto todavía no la mira (design.md, Open Questions).
            $tabla->boolean('activa');
            $tabla->timestampsTz();
            // También es el índice de la llave foránea, porque empieza con id_plazas.
            $tabla->unique(['id_plazas', 'nombre']);
            $tabla->spatialIndex('poligono');
        });

        Schema::create('parametros_plaza', function (Blueprint $tabla) {
            $tabla->id('id_parametros_plaza');
            $tabla->foreignId('id_plazas')->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // Cada clave la valida el código del cambio que la usa.
            $tabla->string('clave');
            $tabla->jsonb('valor');
            $tabla->timestampTz('vigente_desde');
            $tabla->timestampTz('vigente_hasta')->nullable();
            $tabla->timestampsTz();
            // También es el índice de la llave foránea, porque empieza con id_plazas.
            $tabla->unique(['id_plazas', 'clave', 'vigente_desde']);
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
