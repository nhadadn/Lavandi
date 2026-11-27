<?php

namespace App\Direcciones;

use App\Models\Municipio;
use Illuminate\Container\Attributes\Bind;

/**
 * El servicio que convierte una dirección escrita en un punto del mapa (ADR-0009, decisión 4 y su precisión de la
 * adenda del 2026-11-21; openspec/changes/direcciones/design.md, decisiones 1 y 10).
 *
 * Se consulta una sola vez en la vida de cada dirección, al guardarla (presupuesto-de-mapas.md, decisión 3); una
 * favorita o una dirección marcada en el mapa no lo consultan. Las pruebas lo sustituyen por uno simulado, así que
 * ninguna gasta consultas ni necesita llave.
 *
 * Recibe la dirección con su texto, que ya lleva el municipio y el estado del catálogo, y con su municipio del
 * catálogo. El servicio real es Google Geocoding (App\Direcciones\GeocodificadorDeGoogle), que usa solo el texto. Fuera
 * de producción y sin su llave, va el de prueba (App\Direcciones\GeocodificadorDePrueba, direcciones-E15), que decide
 * por el municipio; quién decide es App\Direcciones\GeocodificadorSegunEntorno.
 */
#[Bind(GeocodificadorSegunEntorno::class)]
interface Geocodificador
{
    /**
     * El punto de la dirección, o null si el servicio no la encuentra (direcciones-E03).
     *
     * @param  string  $texto  "Calle 123, Colonia, 00000 Municipio, Estado" (AltaDeDireccion::textoParaGeocodificar()).
     * @param  Municipio  $municipio  El municipio del catálogo, con su estado.
     *
     * @throws GeocodificacionNoDisponible Si el servicio no se pudo consultar: sin llave, sin red o con error.
     */
    public function geocodificar(string $texto, Municipio $municipio): ?Punto;
}
