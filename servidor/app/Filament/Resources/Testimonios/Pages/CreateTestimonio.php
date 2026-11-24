<?php

namespace App\Filament\Resources\Testimonios\Pages;

use App\Filament\Resources\Testimonios\TestimonioResource;
use App\Models\Testimonio;
use Filament\Resources\Pages\CreateRecord;

/**
 * El alta de un testimonio. Entra al final del orden de la lista, para no mover los que ya están.
 */
class CreateTestimonio extends CreateRecord
{
    protected static string $resource = TestimonioResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $ultimo = Testimonio::query()->max('orden');

        return [...$data, 'orden' => $ultimo === null ? 0 : (int) $ultimo + 1];
    }
}
