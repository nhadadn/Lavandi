<?php

namespace App\Models;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Una zona de servicio: un polígono de PostGIS, siempre de una plaza (plazas-E03; ADR-0004; ADR-0009, decisión 5).
 *
 * Sin paquete de tipos espaciales (openspec/changes/plazas-y-zonas/design.md, Open Questions): el polígono se escribe
 * en GeoJSON y la base lo convierte con sus funciones. Leído de la base, el atributo es el EWKB que devuelve
 * PostgreSQL; las consultas sobre el polígono usan las funciones de PostGIS. No sale en JSON.
 *
 *     $plaza->zonas()->create([
 *         'nombre' => 'Zona de prueba Centro',
 *         'poligono' => ['type' => 'Polygon', 'coordinates' => [[[-120, 10], [-119, 10], [-119, 11], [-120, 10]]]],
 *         'activa' => true,
 *     ]);
 */
class Zona extends Modelo
{
    protected $table = 'zonas';

    protected $primaryKey = 'id_zonas';

    protected $fillable = ['id_plazas', 'nombre', 'poligono', 'activa'];

    protected $hidden = ['poligono'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Plaza, $this>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'id_plazas');
    }

    /**
     * El polígono se asigna como geometría GeoJSON, Polygon o MultiPolygon, en arreglo o en texto. Se guarda como
     * MultiPolygon en SRID 4326, que es lo que pide la columna.
     *
     * @return Attribute<mixed, Expression>
     */
    protected function poligono(): Attribute
    {
        return Attribute::make(
            set: fn (array|string|Expression $poligono): Expression => $poligono instanceof Expression
                ? $poligono
                : self::geografiaDesdeGeoJson($poligono),
        );
    }

    /**
     * La expresión de PostGIS que convierte una geometría GeoJSON en geography(MultiPolygon, 4326). El GeoJSON va
     * escapado por la conexión, como un literal de texto.
     *
     * @param  array<string, mixed>|string  $geometria
     */
    public static function geografiaDesdeGeoJson(array|string $geometria): Expression
    {
        $geoJson = is_array($geometria) ? json_encode($geometria, JSON_THROW_ON_ERROR) : $geometria;

        return DB::raw(sprintf(
            'ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(%s), 4326))::geography',
            DB::escape($geoJson),
        ));
    }
}
