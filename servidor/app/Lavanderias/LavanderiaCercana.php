<?php

namespace App\Lavanderias;

/**
 * Una lavandería de la consulta de cercanas, con lo que lleva su pin y su tarjeta en el mapa (cercanas-E06 y E12): su
 * identificador público, su nombre, su ubicación, su distancia en línea recta al punto de la consulta, y el promedio y
 * el número de sus calificaciones.
 */
final readonly class LavanderiaCercana
{
    public function __construct(
        public string $idPublico,
        public string $nombre,
        public float $latitud,
        public float $longitud,
        public int $distanciaMetros,
        public ?float $calificacionPromedio,
        public int $numeroDeCalificaciones,
    ) {}

    /**
     * @return array{
     *     id_publico: string,
     *     nombre: string,
     *     latitud: float,
     *     longitud: float,
     *     distancia_metros: int,
     *     calificacion_promedio: float|null,
     *     numero_de_calificaciones: int
     * }
     */
    public function toArray(): array
    {
        return [
            'id_publico' => $this->idPublico,
            'nombre' => $this->nombre,
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'distancia_metros' => $this->distanciaMetros,
            'calificacion_promedio' => $this->calificacionPromedio,
            'numero_de_calificaciones' => $this->numeroDeCalificaciones,
        ];
    }
}
