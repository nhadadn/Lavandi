<?php

namespace App\Filament\Resources\LogosPrensa\Pages;

use App\Filament\Resources\LogosPrensa\LogoPrensaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * La lista de logos de prensa del panel, con el botón de alta.
 */
class ListLogosPrensa extends ListRecords
{
    protected static string $resource = LogoPrensaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Agregar logo de prensa'),
        ];
    }
}
