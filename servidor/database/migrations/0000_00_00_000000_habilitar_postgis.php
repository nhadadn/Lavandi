<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La primera migración: habilita PostGIS, para que las que siguen puedan usar geography (ADR-0004).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9): la extensión no se quita al revertir.
     */
    public function down(): void
    {
        //
    }
};
