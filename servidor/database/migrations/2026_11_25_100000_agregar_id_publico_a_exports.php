<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El identificador público de cada descarga: id_publico, un ULID, para que la API de la administración nombre la
 * descarga sin el bigint id de exports (ADR-0016, regla 5; ADR-0022, decisión 15;
 * openspec/changes/administracion-en-la-app/design.md, sección 4, "Descargas en CSV y Excel").
 *
 * - Lo asigna App\Exportacion\Exportacion al crear la fila, con HasUlids. La llave sigue siendo id, y la ruta de descarga
 *   de Filament sigue resolviendo por id: el panel no cambia.
 * - Es opcional, por ADR-0016, regla 9: las filas de antes de esta migración se quedan sin id_publico. La API no las
 *   lista, y se borran solas a los días de vida (exportacion.dias_de_vida).
 * - Es la única migración del cambio administracion-en-la-app. No edita la que creó la tabla: alguien ya pudo correrla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $tabla) {
            $tabla->ulid('id_publico')->nullable()->unique();
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
