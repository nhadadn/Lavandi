<?php

namespace App\Filament\Resources\AvisosMasivos\Pages;

use App\Filament\Resources\AvisosMasivos\AvisoMasivoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * La lista de los avisos masivos enviados, con el botón para escribir uno nuevo.
 */
class ListAvisosMasivos extends ListRecords
{
    protected static string $resource = AvisoMasivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Escribir aviso'),
        ];
    }
}
