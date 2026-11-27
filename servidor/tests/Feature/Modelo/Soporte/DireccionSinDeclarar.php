<?php

namespace Tests\Feature\Modelo\Soporte;

use App\Models\Modelo;

/**
 * Un modelo de prueba que no declara $table ni $primaryKey: Eloquent supondría direccion_sin_declarars e id
 * (modelo-E16).
 */
class DireccionSinDeclarar extends Modelo
{
    //
}
