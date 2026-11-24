<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla direcciones: las direcciones de recogida y de entrega que guarda cada cuenta, escritas o marcadas en el
 * mapa (openspec/changes/direcciones/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md;
 * ADR-0016).
 *
 * - La plaza es opcional: sale de la zona que contiene el punto al guardar, y queda vacía si el punto no cae en
 *   ninguna zona activa (config/modelo.php, plaza_opcional). Una dirección así existe, pero no se acepta para un pedido
 *   (direcciones-E11).
 * - El único (id_direcciones, id_plazas) es el destino de la llave compuesta de pedido_direcciones, que crea
 *   resumen-y-confirmacion: así la base rechaza una dirección de otra plaza que la del pedido (direcciones-E12).
 * - La colonia es obligatoria (direcciones-E02; presupuesto-de-mapas.md, decisión 4). Las demás señas son opcionales
 *   en la base: una dirección marcada en el mapa puede no tenerlas (direcciones-E04). La API pide calle, número y
 *   municipio a la escrita, porque sin ellos no se puede geocodificar (direcciones-E01).
 * - Toda dirección tiene su punto: la escrita, el que dio la geocodificación; la del mapa, el del pin
 *   (direcciones-E03 y E04).
 * - Borrado suave: una dirección que ya usó un pedido no se borra (ADR-0016, regla 6). No lleva el trigger
 *   impedir_cambio(), que es solo del historial del pedido y del dinero.
 *
 * Toda llave foránea lleva su índice y ON DELETE RESTRICT (modelo-E09 y modelo-E10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direcciones', function (Blueprint $tabla) {
            $tabla->id('id_direcciones');
            $tabla->ulid('id_publico')->unique();
            // La cuenta dueña de la dirección: solo ella la ve y la usa (direcciones-E14).
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            $tabla->foreignId('id_plazas')->nullable()->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // El nombre de la favorita, como "Casa" (direcciones-E06); opcional en las que no son favoritas.
            $tabla->string('alias')->nullable();
            $tabla->string('calle')->nullable();
            $tabla->string('numero_exterior')->nullable();
            $tabla->string('numero_interior')->nullable();
            $tabla->string('colonia');
            $tabla->string('codigo_postal')->nullable();
            $tabla->string('municipio')->nullable();
            $tabla->text('referencias')->nullable();
            // PostGIS, SRID 4326 (ADR-0004): la zona de servicio se comprueba con este punto.
            $tabla->geography('ubicacion', subtype: 'point', srid: 4326);
            $tabla->boolean('es_favorita')->default(false);
            $tabla->timestampsTz();
            $tabla->softDeletesTz();
            // El destino de la llave compuesta de pedido_direcciones (direcciones-E12; ADR-0016, regla 4).
            $tabla->unique(['id_direcciones', 'id_plazas']);
            $tabla->spatialIndex('ubicacion');
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
