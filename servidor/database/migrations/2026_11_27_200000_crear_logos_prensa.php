<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La segunda tabla de landing: logos_prensa (openspec/changes/landing/design.md, "Tablas y migración", tarea 4.0b;
 * vault/arquitectura/modelo-de-datos.md, "Landing"; ADR-0011; ADR-0016).
 *
 * Los logos de medios o menciones de la sección 10 de la portada, "activables conforme se obtengan"
 * (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2), que se editan desde el panel con su interruptor de activo y su orden.
 * El nombre es el del medio, y es también el texto alternativo de la imagen. La imagen es la ruta del archivo en el
 * disco privado de fotos del entorno (adenda del 2026-11-24 de ADR-0006). Un logo nuevo nace inactivo, al principio del
 * orden.
 *
 * Sin id_plazas: la portada es una para todo el sitio, y la tabla está en la lista sin_plaza de config/modelo.php
 * (modelo-E05). Sin el trigger impedir_cambio(): es contenido, no historial. Nace vacía: los logos los da EL CLIENTE
 * (DD-117).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logos_prensa', function (Blueprint $tabla) {
            $tabla->id('id_logos_prensa');
            $tabla->string('nombre');
            $tabla->string('imagen');
            $tabla->boolean('activo')->default(false);
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->timestampsTz();
            $tabla->index(['activo', 'orden']);
        });

        DB::statement("alter table logos_prensa add constraint logos_prensa_nombre_check check (btrim(nombre) <> '')");
        DB::statement("alter table logos_prensa add constraint logos_prensa_imagen_check check (btrim(imagen) <> '')");
        DB::statement('alter table logos_prensa add constraint logos_prensa_orden_check check (orden >= 0)');
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
