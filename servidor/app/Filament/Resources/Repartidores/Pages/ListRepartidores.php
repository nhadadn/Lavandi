<?php

namespace App\Filament\Resources\Repartidores\Pages;

use App\Filament\Resources\Repartidores\RepartidorResource;
use Filament\Resources\Pages\ListRecords;

/**
 * La lista de repartidores del panel. Sin botón de alta desde cero: el repartidor se registra desde la app y el alta
 * autoriza ese registro (repartidor-E05; design.md, lectura 1).
 */
class ListRepartidores extends ListRecords
{
    protected static string $resource = RepartidorResource::class;
}
