<?php

namespace App\Filament\Resources\Usuarios\Pages;

use App\Filament\Resources\Usuarios\Actions\AccionesDeBloqueo;
use App\Filament\Resources\Usuarios\UsuarioResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * La ficha del usuario en el panel: sus datos de cuenta y su historial de pedidos, de solo lectura (administracion-E01),
 * con los botones de bloquear y levantar el bloqueo (administracion-E03 y E04). Sin botón de edición ni de borrado.
 */
class ViewUsuario extends ViewRecord
{
    protected static string $resource = UsuarioResource::class;

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return AccionesDeBloqueo::todas();
    }
}
