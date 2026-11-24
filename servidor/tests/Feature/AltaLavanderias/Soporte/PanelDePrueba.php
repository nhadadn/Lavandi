<?php

namespace Tests\Feature\AltaLavanderias\Soporte;

use App\Models\Rol;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

/**
 * Lo que comparten las pruebas del panel de administración (Filament, ADR-0003): una cuenta de administración con su
 * sesión web, el panel admin como el actual y el disco de las subidas temporales de Livewire falso, para que una foto
 * de prueba no quede en storage/.
 */
trait PanelDePrueba
{
    protected function comoAdministracion(): Usuario
    {
        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create();

        Filament::setCurrentPanel('admin');
        Storage::fake('local');
        $this->actingAs($administracion, 'web');

        return $administracion;
    }
}
