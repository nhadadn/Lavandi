<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla actividad_usuarios: una fila por persona y día, que alimenta los usuarios activos del panel de indicadores
 * (openspec/changes/indicadores/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016;
 * NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5, usuarios activos).
 *
 * - Tabla de enlace: la llave primaria es (id_usuarios, fecha), sin id_actividad_usuarios, sin created_at ni
 *   updated_at, y sin modelo de Eloquent; se lee con el constructor de consultas (openspec/changes/modelo-de-datos/
 *   design.md, decisión 7; config/modelo.php, de_enlace).
 * - Sin id_plazas: la persona no pertenece a una plaza (config/modelo.php, sin_plaza).
 * - La llave a usuarios lleva ON DELETE RESTRICT; su índice es el de la llave primaria, que empieza con ella
 *   (modelo-E10).
 * - Sin el trigger impedir_cambio(), que es solo del historial del pedido y del dinero.
 *
 * Qué la escribe, y por eso qué cuenta como usar la aplicación, es DD-111 (indicadores-E07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_usuarios', function (Blueprint $tabla) {
            $tabla->foreignId('id_usuarios')->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            // El día, no un instante: la persona cuenta una vez por día.
            $tabla->date('fecha');
            $tabla->primary(['id_usuarios', 'fecha']);
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
