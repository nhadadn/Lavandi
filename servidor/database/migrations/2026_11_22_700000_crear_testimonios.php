<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla de landing: testimonios (openspec/changes/landing/design.md, "Tablas y migración", tarea 4.0;
 * vault/arquitectura/modelo-de-datos.md, "Landing"; ADR-0011; ADR-0016).
 *
 * Las opiniones de la sección 8 de la portada (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2), que se editan desde el
 * panel con su interruptor de activo y su orden. Un testimonio nuevo nace inactivo, al principio del orden.
 *
 * Sin id_plazas: la portada es una para todo el sitio, y la tabla está en la lista sin_plaza de config/modelo.php
 * (modelo-E05). Sin el trigger impedir_cambio(): es contenido, no historial. Nace vacía: los testimonios los da EL
 * CLIENTE (DD-117).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonios', function (Blueprint $tabla) {
            $tabla->id('id_testimonios');
            $tabla->string('autor');
            $tabla->text('texto');
            $tabla->boolean('activo')->default(false);
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->timestampsTz();
            $tabla->index(['activo', 'orden']);
        });

        DB::statement("alter table testimonios add constraint testimonios_autor_check check (btrim(autor) <> '')");
        DB::statement("alter table testimonios add constraint testimonios_texto_check check (btrim(texto) <> '')");
        DB::statement('alter table testimonios add constraint testimonios_orden_check check (orden >= 0)');
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
