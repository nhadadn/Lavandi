<?php

namespace Tests\Feature\Plazas\Soporte;

use App\Models\Plaza;
use App\Models\Zona;

/**
 * Plazas y zonas ficticias para las pruebas (openspec/changes/plazas-y-zonas/design.md, decisión 6). Ninguna es del
 * territorio inicial, que espera DD-093 y DD-094: los cuadrados quedan en el océano Pacífico, lejos de cualquier
 * domicilio.
 */
trait PlazasDePrueba
{
    /**
     * Una plaza ficticia, activa por omisión.
     */
    protected function plazaDePrueba(
        string $nombre = 'Plaza de prueba 1',
        bool $activa = true,
        string $zonaHoraria = 'America/Mexico_City',
    ): Plaza {
        return Plaza::create([
            'nombre' => $nombre,
            'zona_horaria' => $zonaHoraria,
            'activa' => $activa,
        ]);
    }

    /**
     * Una zona de la plaza, activa por omisión, con el polígono GeoJSON que se pasa.
     *
     * @param  array<string, mixed>  $poligono
     */
    protected function zonaDePrueba(Plaza $plaza, string $nombre, array $poligono, bool $activa = true): Zona
    {
        return $plaza->zonas()->create([
            'nombre' => $nombre,
            'poligono' => $poligono,
            'activa' => $activa,
        ]);
    }

    /**
     * El archivo GeoJSON de una plaza de prueba activa, con sus zonas activas, como lo lee el comando plazas:cargar.
     * Se borra al terminar la prueba.
     *
     * @param  array<string, array<string, mixed>>  $zonas  El polígono GeoJSON de cada zona, por su nombre.
     */
    protected function archivoDePlaza(string $plaza, array $zonas): string
    {
        $datos = [
            'type' => 'FeatureCollection',
            'plaza' => ['nombre' => $plaza, 'zona_horaria' => 'America/Mexico_City', 'activa' => true],
            'features' => array_map(
                fn (string $nombre, array $poligono): array => [
                    'type' => 'Feature',
                    'properties' => ['nombre' => $nombre, 'activa' => true],
                    'geometry' => $poligono,
                ],
                array_keys($zonas),
                $zonas,
            ),
        ];

        $archivo = (string) tempnam(sys_get_temp_dir(), 'plaza');
        file_put_contents($archivo, json_encode($datos, JSON_THROW_ON_ERROR));
        $this->beforeApplicationDestroyed(fn () => @unlink($archivo));

        return $archivo;
    }

    /**
     * Un cuadrado en GeoJSON (Polygon), con su esquina suroeste en (oeste, sur) y el lado en grados. Las coordenadas
     * van como las pide GeoJSON: longitud y luego latitud.
     *
     * @return array{type: string, coordinates: list<list<list<float>>>}
     */
    protected function cuadrado(float $oeste, float $sur, float $lado = 1.0): array
    {
        $este = $oeste + $lado;
        $norte = $sur + $lado;

        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$oeste, $sur],
                [$este, $sur],
                [$este, $norte],
                [$oeste, $norte],
                [$oeste, $sur],
            ]],
        ];
    }
}
