<?php

namespace App\Direcciones;

use App\Models\Municipio;
use Illuminate\Support\Str;

/**
 * La geocodificación de prueba (direcciones-E15): sin la llave de Google y fuera de producción, una dirección escrita en
 * el municipio de Durango cae dentro de la zona de la plaza de prueba de Durango (plazas-E16), y cualquier otra no se
 * encuentra, como en direcciones-E03. Sirve para probar el pedido desde un teléfono o el emulador mientras no existan
 * las llaves de prueba (DD-072).
 *
 * Decide por el municipio del catálogo, no por el texto (openspec/changes/direcciones/design.md, decisión 10): ubica
 * solo la dirección cuyo municipio se llama Durango y es del Estado de Durango. Así Gómez Palacio, que también lleva
 * "Durango" en el estado, no se encuentra.
 *
 * El punto no es real: sale del centro de esa zona de prueba más un desplazamiento que depende solo del texto, de modo
 * que la misma dirección cae siempre en el mismo punto y dos direcciones distintas, casi siempre en puntos distintos.
 * El desplazamiento es de hasta 0.015 grados por lado, y la zona de prueba mide unos 6 × 6 km, así que el punto queda
 * dentro.
 */
final class GeocodificadorDePrueba implements Geocodificador
{
    /** El centro de la zona de la plaza de prueba de Durango. */
    private const CENTRO_DE_DURANGO = [24.0277, -104.6532];

    private const DESPLAZAMIENTO_MAXIMO = 0.015;

    /** El municipio y el estado que ubica, comparados sin acentos ni mayúsculas. */
    private const MUNICIPIO = 'durango';

    private const ESTADO = 'durango';

    public function geocodificar(string $texto, Municipio $municipio): ?Punto
    {
        if (self::comparable($municipio->nombre) !== self::MUNICIPIO
            || self::comparable($municipio->entidadFederativa?->nombre) !== self::ESTADO) {
            return null;
        }

        $huella = crc32($texto);
        [$latitud, $longitud] = self::CENTRO_DE_DURANGO;

        return new Punto(
            round($latitud + $this->desplazamiento($huella), 6),
            round($longitud + $this->desplazamiento(intdiv($huella, 2003)), 6),
        );
    }

    /** Un desplazamiento entre -0.015 y 0.015 grados que sale del número dado. */
    private function desplazamiento(int $numero): float
    {
        return (($numero % 2001) / 1000 - 1) * self::DESPLAZAMIENTO_MAXIMO;
    }

    private static function comparable(?string $nombre): string
    {
        return Str::lower(Str::ascii(trim((string) $nombre)));
    }
}
