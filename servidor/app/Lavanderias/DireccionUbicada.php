<?php

namespace App\Lavanderias;

use App\Direcciones\Punto;
use App\Models\Municipio;
use App\Models\Plaza;

/**
 * La dirección de una lavandería ya ubicada: su punto, la plaza de la zona que lo contiene y su municipio del catálogo.
 */
final readonly class DireccionUbicada
{
    public function __construct(
        public Punto $punto,
        public Plaza $plaza,
        public Municipio $municipio,
    ) {}

    /**
     * Las columnas de lavanderias que salen de aquí, con las señas tal como las escribió la persona.
     *
     * @param  array{calle: string, numero: string, colonia: string, codigo_postal: string}  $direccion
     * @return array<string, mixed>
     */
    public function columnas(array $direccion): array
    {
        return [
            'calle' => $direccion['calle'],
            'numero' => $direccion['numero'],
            'colonia' => $direccion['colonia'],
            'codigo_postal' => $direccion['codigo_postal'],
            'id_municipios' => $this->municipio->getKey(),
            'id_plazas' => $this->plaza->getKey(),
            'ubicacion' => ['latitud' => $this->punto->latitud, 'longitud' => $this->punto->longitud],
        ];
    }
}
