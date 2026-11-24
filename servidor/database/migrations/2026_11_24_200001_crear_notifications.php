<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla notifications de Laravel, donde Filament deja los avisos del panel de cada cuenta: hoy, el de archivo listo de
 * una descarga, con sus enlaces (openspec/changes/exportacion/design.md, "Tablas y migración"; exportacion-E10).
 *
 * Es del framework: está en la lista del_framework de config/modelo.php y conserva las columnas de Laravel. La cuenta va
 * en notifiable_type y notifiable_id, que apunta a usuarios.id_usuarios. Las fechas van con zona horaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $tabla) {
            $tabla->uuid('id')->primary();
            $tabla->string('type');
            $tabla->morphs('notifiable');
            // En JSON: Filament filtra los avisos del panel por data->>'format', que en PostgreSQL pide json.
            $tabla->json('data');
            $tabla->timestampTz('read_at')->nullable();
            $tabla->timestampsTz();
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
