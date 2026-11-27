<?php

namespace App\Filament\Resources\Testimonios\Pages;

use App\Filament\Resources\Testimonios\TestimonioResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * La edición de un testimonio: autor, texto y su interruptor de activo.
 */
class EditTestimonio extends EditRecord
{
    protected static string $resource = TestimonioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Borrar'),
        ];
    }
}
