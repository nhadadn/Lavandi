<?php

namespace App\Filament\Resources\LogosPrensa\Pages;

use App\Filament\Resources\LogosPrensa\LogoPrensaResource;
use App\Models\LogoPrensa;
use Filament\Resources\Pages\CreateRecord;

/**
 * El alta de un logo de prensa. Entra al final del orden de la lista, para no mover los que ya están.
 */
class CreateLogoPrensa extends CreateRecord
{
    protected static string $resource = LogoPrensaResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $ultimo = LogoPrensa::query()->max('orden');

        return [...$data, 'orden' => $ultimo === null ? 0 : (int) $ultimo + 1];
    }
}
