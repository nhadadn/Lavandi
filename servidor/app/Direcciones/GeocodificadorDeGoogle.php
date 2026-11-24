<?php

namespace App\Direcciones;

use App\Models\Municipio;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * La geocodificación con Google Geocoding (ADR-0009, decisión 4): una consulta por dirección escrita, al guardarla.
 *
 * - La llave es de cada entorno y vive solo en su .env, como GOOGLE_GEOCODIFICACION_LLAVE (ADR-0012). Sin llave no se
 *   consulta nada.
 * - Solo busca en México (components=country:MX): el territorio del contrato (NSV-2026-001-v8.pdf:4:Cláusula I).
 * - "No la encontró" es solo ZERO_RESULTS (direcciones-E03). Una llave negada, un límite pasado, un error de Google o
 *   la falta de red no dicen nada de la dirección: el servicio no está disponible, y la dirección no se guarda.
 * - Toma el punto del primer resultado, el que Google da como el mejor.
 *
 * Los mensajes de error no llevan la llave, porque van a los registros.
 */
final class GeocodificadorDeGoogle implements Geocodificador
{
    private const SEGUNDOS_DE_ESPERA = 5;

    /**
     * Google recibe solo el texto, que ya lleva el municipio y el estado; el municipio del catálogo no viaja.
     */
    public function geocodificar(string $texto, Municipio $municipio): ?Punto
    {
        $llave = (string) config('services.geocodificacion.llave');

        if ($llave === '') {
            throw GeocodificacionNoDisponible::porque('no hay llave de Google Geocoding en este entorno');
        }

        try {
            $respuesta = Http::timeout(self::SEGUNDOS_DE_ESPERA)
                ->acceptJson()
                ->get((string) config('services.geocodificacion.direccion'), [
                    'address' => $texto,
                    'components' => 'country:MX',
                    'key' => $llave,
                ]);
        } catch (ConnectionException $error) {
            throw GeocodificacionNoDisponible::porque('no se pudo llegar a Google Geocoding', $error);
        }

        if (! $respuesta->successful()) {
            throw GeocodificacionNoDisponible::porque("Google Geocoding respondió {$respuesta->status()}");
        }

        $estado = $respuesta->json('status');

        if ($estado === 'ZERO_RESULTS') {
            return null;
        }

        $latitud = $respuesta->json('results.0.geometry.location.lat');
        $longitud = $respuesta->json('results.0.geometry.location.lng');

        if ($estado !== 'OK' || ! is_numeric($latitud) || ! is_numeric($longitud)) {
            throw GeocodificacionNoDisponible::porque('Google Geocoding respondió '.(is_string($estado) ? $estado : 'sin estado'));
        }

        return new Punto((float) $latitud, (float) $longitud);
    }
}
