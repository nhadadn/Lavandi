<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo del territorio para la dirección asistida, y el municipio de la dirección como llave a él
 * (openspec/changes/direcciones/design.md, "La dirección asistida: el catálogo y el cambio en direcciones", decisiones
 * 7 y 8; C-238; ADR-0009, adenda del 2026-11-21, decisión 6; ADR-0016).
 *
 * - entidades_federativas, municipios y colonias: los estados, los municipios del territorio del contrato
 *   (NSV-2026-001-v8.pdf:4:Cláusula I) y las colonias de cada código postal. Están en la lista sin_plaza de
 *   config/modelo.php: el territorio no es de una plaza. Nacen vacíos: los llena el comando direcciones:cargar-catalogo
 *   con el archivo de Correos de México, que no se versiona (DD-138).
 * - Los códigos son los del archivo: el del estado, dos números; el del municipio, cinco (el del estado y los tres
 *   suyos); el código postal, cinco. Los CHECK lo exigen en la base.
 * - colonias no lleva único por nombre: el archivo puede traer dos asentamientos con el mismo nombre en el mismo código
 *   postal, de distinto tipo. Ninguna tabla apunta a colonias.
 * - direcciones deja la columna de texto municipio y apunta al catálogo con id_municipios, opcional: la API la exige en
 *   la escrita y la deja opcional en la del mapa (direcciones-E04 y E17). La colonia y el código postal siguen siendo
 *   texto: el catálogo los sugiere y no los exige (direcciones-E20). Las filas que ya existen son de prueba y pierden
 *   su municipio de texto (decisión 29 del acta del 2026-11-20).
 *
 * Va en una migración aparte porque la que crea direcciones ya corrió: las migraciones solo van hacia adelante
 * (ADR-0016, regla 9). Toda llave foránea lleva su índice y ON DELETE RESTRICT (modelo-E09 y modelo-E10): un municipio
 * con direcciones no se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entidades_federativas', function (Blueprint $tabla) {
            $tabla->id('id_entidades_federativas');
            // c_estado del archivo: es como la API y la carga nombran al estado.
            $tabla->string('codigo')->unique();
            // d_estado del archivo, como "Durango" o "Coahuila de Zaragoza".
            $tabla->string('nombre');
            $tabla->timestampsTz();
        });

        Schema::create('municipios', function (Blueprint $tabla) {
            $tabla->id('id_municipios');
            $tabla->foreignId('id_entidades_federativas')
                ->index()
                ->constrained('entidades_federativas', 'id_entidades_federativas')
                ->restrictOnDelete();
            // c_estado más c_mnpio del archivo: es como la API nombra al municipio (C-235).
            $tabla->string('codigo')->unique();
            // D_mnpio del archivo.
            $tabla->string('nombre');
            $tabla->timestampsTz();
        });

        Schema::create('colonias', function (Blueprint $tabla) {
            $tabla->id('id_colonias');
            $tabla->foreignId('id_municipios')->index()->constrained('municipios', 'id_municipios')->restrictOnDelete();
            // d_codigo del archivo. Así se buscan las colonias (direcciones-E19).
            $tabla->string('codigo_postal')->index();
            // d_asenta y d_tipo_asenta del archivo: el tipo distingue dos con el mismo nombre.
            $tabla->string('nombre');
            $tabla->string('tipo');
            $tabla->timestampsTz();
        });

        DB::statement("alter table entidades_federativas add constraint entidades_federativas_codigo_check
            check (codigo ~ '^[0-9]{2}$')");
        DB::statement("alter table municipios add constraint municipios_codigo_check
            check (codigo ~ '^[0-9]{5}$')");
        DB::statement("alter table colonias add constraint colonias_codigo_postal_check
            check (codigo_postal ~ '^[0-9]{5}$')");

        Schema::table('direcciones', function (Blueprint $tabla) {
            $tabla->dropColumn('municipio');
        });

        Schema::table('direcciones', function (Blueprint $tabla) {
            $tabla->foreignId('id_municipios')
                ->nullable()
                ->index()
                ->constrained('municipios', 'id_municipios')
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
