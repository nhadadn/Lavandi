<?php

namespace App\Landing;

/**
 * El mapa de "Cobertura" como un dibujo SVG propio (ADR-0009, decisión 5; design.md, pregunta abierta 3): proyecta los
 * polígonos de las zonas activas en un lienzo de 1000 unidades de ancho, sin teselas, sin librería de mapas y sin
 * ninguna petición a un servicio de mapas (landing-E12 y landing-E13).
 *
 * La proyección es equirectangular, con las longitudes acortadas por el coseno de la latitud media: para un territorio
 * del tamaño de Durango la forma se ve bien y el cálculo es de una línea. Se dibuja en el servidor, así que el mapa se
 * ve aunque no cargue el JavaScript; acercar, alejar y el nombre de la zona los agrega resources/js/portada.js.
 */
final class MapaDeCobertura
{
    /** El ancho del lienzo, en unidades del SVG. */
    public const ANCHO = 1000;

    /** El alto mínimo del lienzo: una zona muy ancha y baja no deja el mapa como una raya. */
    public const ALTO_MINIMO = 500;

    /** El margen alrededor de las zonas, como fracción de su tamaño. */
    private const MARGEN = 0.05;

    /**
     * @param  list<array{plaza: string, nombre: string, geometria: array{type: string, coordinates: list<mixed>}}>  $zonas
     * @return array{ancho: int, alto: int, zonas: list<array{plaza: string, nombre: string, trazo: string}>}
     */
    public function dibujar(array $zonas): array
    {
        $puntos = [];
        foreach ($zonas as $zona) {
            foreach (self::anillos($zona['geometria']) as $anillo) {
                array_push($puntos, ...$anillo);
            }
        }

        if ($puntos === []) {
            return ['ancho' => self::ANCHO, 'alto' => self::ALTO_MINIMO, 'zonas' => []];
        }

        $longitudes = array_column($puntos, 0);
        $latitudes = array_column($puntos, 1);
        [$oeste, $este, $sur, $norte] = [min($longitudes), max($longitudes), min($latitudes), max($latitudes)];

        $coseno = cos(deg2rad(($sur + $norte) / 2));
        $anchoProyectado = max(($este - $oeste) * $coseno, 1e-9) * (1 + 2 * self::MARGEN);
        $altoProyectado = max($norte - $sur, 1e-9) * (1 + 2 * self::MARGEN);

        $escala = min(self::ANCHO / $anchoProyectado, self::ANCHO / $altoProyectado);
        $alto = (int) ceil(max(self::ALTO_MINIMO, $altoProyectado * $escala));
        $desplazamientoX = (self::ANCHO - ($este - $oeste) * $coseno * $escala) / 2;
        $desplazamientoY = ($alto - ($norte - $sur) * $escala) / 2;

        $proyectar = fn (array $punto): string => sprintf(
            '%s,%s',
            self::numero($desplazamientoX + ((float) $punto[0] - $oeste) * $coseno * $escala),
            self::numero($desplazamientoY + ($norte - (float) $punto[1]) * $escala),
        );

        return [
            'ancho' => self::ANCHO,
            'alto' => $alto,
            'zonas' => array_map(fn (array $zona): array => [
                'plaza' => $zona['plaza'],
                'nombre' => $zona['nombre'],
                'trazo' => implode(' ', array_map(
                    fn (array $anillo): string => 'M'.implode('L', array_map($proyectar, $anillo)).'Z',
                    self::anillos($zona['geometria']),
                )),
            ], $zonas),
        ];
    }

    /**
     * Los anillos de un Polygon o un MultiPolygon de GeoJSON, cada uno como lista de [longitud, latitud].
     *
     * @param  array{type: string, coordinates: list<mixed>}  $geometria
     * @return list<list<array{0: float|int, 1: float|int}>>
     */
    private static function anillos(array $geometria): array
    {
        $poligonos = match ($geometria['type']) {
            'Polygon' => [$geometria['coordinates']],
            'MultiPolygon' => $geometria['coordinates'],
            default => [],
        };

        $anillos = [];
        foreach ($poligonos as $poligono) {
            foreach ((array) $poligono as $anillo) {
                $anillos[] = array_values(array_filter((array) $anillo, fn (mixed $punto): bool => is_array($punto) && count($punto) >= 2));
            }
        }

        return array_values(array_filter($anillos, fn (array $anillo): bool => count($anillo) >= 3));
    }

    /** Un número del trazo, con un decimal: en un lienzo de 1000 unidades basta. */
    private static function numero(float $valor): string
    {
        $texto = number_format($valor, 1, '.', '');

        return str_ends_with($texto, '.0') ? substr($texto, 0, -2) : $texto;
    }
}
