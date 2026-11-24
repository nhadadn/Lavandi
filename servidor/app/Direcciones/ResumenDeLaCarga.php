<?php

namespace App\Direcciones;

/**
 * Lo que dejó en la base una carga del catálogo, para que el comando lo diga (direcciones-E16).
 */
final readonly class ResumenDeLaCarga
{
    /**
     * @param  array<string, array{nombre: string, municipios: int, colonias: int}>  $porEstado  Por el código del estado.
     * @param  array<string, string>  $primerasLineas  La primera línea de cada archivo, por su nombre: la nota de Correos
     *                                                 de México.
     * @param  int  $fueraDelTerritorio  Las filas de los archivos que no son de un municipio del territorio.
     * @param  array<string, string>  $estadosQueNoVinieron  Los estados del territorio sin filas en los archivos, por su
     *                                                       código.
     */
    public function __construct(
        public array $porEstado,
        public array $primerasLineas,
        public int $fueraDelTerritorio,
        public array $estadosQueNoVinieron,
    ) {}
}
