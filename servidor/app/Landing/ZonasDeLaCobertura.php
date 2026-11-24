<?php

namespace App\Landing;

use App\Models\Zona;
use JsonException;

/**
 * Las zonas que dibuja "Cobertura" (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 7): las zonas activas de las
 * plazas activas, cada una con su nombre, el de su plaza y su polígono en GeoJSON (landing-E12 y landing-E14). Las
 * carga plazas-y-zonas; aquí solo se leen.
 *
 * El polígono sale simplificado por PostGIS antes de mandarlo, para que el Estado de Durango completo no pese en el
 * teléfono (design.md, "Risks / Trade-offs"): ST_SimplifyPreserveTopology con la tolerancia de config('landing.
 * cobertura.simplificacion'), en grados, y a lo más cinco decimales, cerca de un metro. Las zonas se dibujan con
 * polígonos propios, sin ningún servicio de mapas (ADR-0009, decisión 5).
 */
final class ZonasDeLaCobertura
{
    /**
     * @return list<array{plaza: string, nombre: string, geometria: array{type: string, coordinates: list<mixed>}}>
     */
    public function activas(): array
    {
        $tolerancia = (float) config('landing.cobertura.simplificacion');

        return Zona::query()
            ->join('plazas', 'plazas.id_plazas', '=', 'zonas.id_plazas')
            ->where('zonas.activa', true)
            ->where('plazas.activa', true)
            ->orderBy('plazas.nombre')
            ->orderBy('zonas.nombre')
            ->orderBy('zonas.id_zonas')
            ->selectRaw('plazas.nombre as plaza, zonas.nombre')
            ->selectRaw('ST_AsGeoJSON(ST_Multi(ST_SimplifyPreserveTopology(zonas.poligono::geometry, ?)), 5) as geojson', [$tolerancia])
            ->toBase()
            ->get()
            ->map(fn (object $zona): array => [
                'plaza' => (string) $zona->plaza,
                'nombre' => (string) $zona->nombre,
                'geometria' => self::geometria((string) $zona->geojson),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{type: string, coordinates: list<mixed>}
     *
     * @throws JsonException
     */
    private static function geometria(string $geoJson): array
    {
        /** @var array{type: string, coordinates: list<mixed>} */
        return json_decode($geoJson, true, 512, JSON_THROW_ON_ERROR);
    }
}
