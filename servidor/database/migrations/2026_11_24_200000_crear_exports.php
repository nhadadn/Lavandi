<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla exports de la exportación de Filament: una fila por descarga que pide la administración, con el disco y el
 * nombre del archivo, el exportador, las filas escritas, quién la pidió y cuándo terminó (openspec/changes/exportacion/
 * design.md, "Tablas y migración"; ADR-0003).
 *
 * Es del framework: está en la lista del_framework de config/modelo.php y conserva las columnas de Filament (id,
 * completed_at, file_disk, file_name, exporter, processed_rows, total_rows, successful_rows). La única que cambia es la
 * de la cuenta: id_usuarios hacia usuarios.id_usuarios, porque aquí no existe la tabla users de Laravel; el modelo
 * App\Exportacion\Exportacion la usa en su relación user() (design.md, pregunta abierta 3). Las fechas van con zona
 * horaria, como las del negocio (ADR-0016).
 *
 * No es del negocio ni lleva plaza: los reportes son de todas las plazas (indicadores, proposal.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->timestampTz('completed_at')->nullable();
            $tabla->string('file_disk');
            $tabla->string('file_name')->nullable();
            $tabla->string('exporter');
            $tabla->unsignedInteger('processed_rows')->default(0);
            $tabla->unsignedInteger('total_rows');
            $tabla->unsignedInteger('successful_rows')->default(0);
            // La cuenta de la administración que pidió la descarga: solo ella baja el archivo (exportacion-E11).
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
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
