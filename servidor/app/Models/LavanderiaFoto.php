<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDeLaLavanderia;

/**
 * Una foto de la lavandería: en la base solo van su ruta y su orden; la foto vive en el almacenamiento privado del
 * entorno y llega a la app con una URL firmada que vence (ADR-0006; perfil-lavanderia-E03, tarea 2.1). La ruta nunca
 * sale por la API.
 */
class LavanderiaFoto extends Modelo
{
    use HeredaLaPlazaDeLaLavanderia;

    protected $table = 'lavanderia_fotos';

    protected $primaryKey = 'id_lavanderia_fotos';

    protected $fillable = ['id_plazas', 'ruta', 'orden'];

    protected $hidden = ['ruta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }
}
