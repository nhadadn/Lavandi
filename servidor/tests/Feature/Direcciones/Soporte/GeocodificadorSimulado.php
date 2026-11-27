<?php

namespace Tests\Feature\Direcciones\Soporte;

use App\Direcciones\Geocodificador;
use App\Direcciones\Punto;
use App\Models\Municipio;

/**
 * El servicio de geocodificación simulado de las pruebas (openspec/changes/direcciones/design.md, decisión 1): ninguna
 * prueba gasta consultas ni necesita llave. Devuelve siempre la misma respuesta y anota cada consulta, para contar
 * cuántas hizo el servidor.
 */
final class GeocodificadorSimulado implements Geocodificador
{
    /** @var list<string> El texto de cada consulta, en orden. */
    public array $consultas = [];

    /** @var list<string> El código del municipio de cada consulta, en orden. */
    public array $municipios = [];

    /**
     * @param  Punto|null  $respuesta  El punto que "encuentra"; null si no encuentra ninguna dirección.
     */
    public function __construct(private readonly ?Punto $respuesta) {}

    public function geocodificar(string $texto, Municipio $municipio): ?Punto
    {
        $this->consultas[] = $texto;
        $this->municipios[] = $municipio->codigo;

        return $this->respuesta;
    }
}
