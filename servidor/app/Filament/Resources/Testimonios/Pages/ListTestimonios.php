<?php

namespace App\Filament\Resources\Testimonios\Pages;

use App\Filament\Resources\Testimonios\TestimonioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * La lista de testimonios del panel, con el botón de alta.
 */
class ListTestimonios extends ListRecords
{
    protected static string $resource = TestimonioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Agregar testimonio'),
        ];
    }
}
