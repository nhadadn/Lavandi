<?php

namespace App\Filament\Resources\LogosPrensa\Pages;

use App\Filament\Resources\LogosPrensa\LogoPrensaResource;
use Filament\Resources\Pages\EditRecord;

/**
 * La edición de un logo de prensa: el nombre del medio, la imagen y su interruptor de activo. Sin botón de borrar: un
 * logo se retira de la portada desactivándolo (landing-E17).
 */
class EditLogoPrensa extends EditRecord
{
    protected static string $resource = LogoPrensaResource::class;
}
