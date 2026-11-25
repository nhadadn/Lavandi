<?php

namespace App\Filament\Resources\Usuarios\Pages;

use App\Filament\Resources\Usuarios\UsuarioResource;
use Filament\Resources\Pages\ListRecords;

/**
 * La lista de usuarios del panel. Sin botón de alta: dar de alta usuarios desde el panel está fuera del contrato.
 */
class ListUsuarios extends ListRecords
{
    protected static string $resource = UsuarioResource::class;
}
