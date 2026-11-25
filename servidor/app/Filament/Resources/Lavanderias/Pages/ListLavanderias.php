<?php

namespace App\Filament\Resources\Lavanderias\Pages;

use App\Filament\Resources\Lavanderias\LavanderiaResource;
use Filament\Resources\Pages\ListRecords;

/**
 * La lista de lavanderías del panel. Sin botón de alta: el alta desde el panel espera DD-053 (alta-lavanderias-E11).
 */
class ListLavanderias extends ListRecords
{
    protected static string $resource = LavanderiaResource::class;
}
