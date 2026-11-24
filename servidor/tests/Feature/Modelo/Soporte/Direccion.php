<?php

namespace Tests\Feature\Modelo\Soporte;

use App\Models\Concerns\TieneIdPublico;
use App\Models\Modelo;

/**
 * Un modelo de prueba como el que creará el cambio direcciones: declara su tabla y su llave, y sale a la API por su
 * identificador público (modelo-E15 y modelo-E16).
 */
class Direccion extends Modelo
{
    use TieneIdPublico;

    protected $table = 'direcciones';

    protected $primaryKey = 'id_direcciones';

    protected $fillable = ['alias'];
}
