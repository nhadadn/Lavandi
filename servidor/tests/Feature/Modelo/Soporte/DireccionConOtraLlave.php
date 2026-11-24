<?php

namespace Tests\Feature\Modelo\Soporte;

use App\Models\Modelo;

/**
 * Un modelo de prueba que declara la tabla correcta con una llave que no es la de la base (modelo-E16).
 */
class DireccionConOtraLlave extends Modelo
{
    protected $table = 'direcciones';

    protected $primaryKey = 'id';
}
