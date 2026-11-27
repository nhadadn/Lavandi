<?php

namespace App\Filament\Resources\Lavanderias\Actions;

use App\Lavanderias\EstadoDeLaLavanderia;
use App\Models\Lavanderia;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Las acciones de estado de la lavandería en el panel, en la lista y en la página de cada una (alta-lavanderias-E14,
 * E15 y E17; ADR-0003: "la suspensión, el levantamiento y la baja son acciones del recurso"). Cada botón solo aparece
 * cuando la transición se puede hacer, y la regla vive en App\Lavanderias\EstadoDeLaLavanderia, no aquí.
 */
final class AccionesDeEstado
{
    /**
     * @return list<Action>
     */
    public static function todas(): array
    {
        return [self::suspender(), self::levantarSuspension(), self::darDeBaja()];
    }

    public static function darDeBaja(): Action
    {
        return Action::make('darDeBaja')
            ->label('Dar de baja')
            ->icon(Heroicon::OutlinedArchiveBoxXMark)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Dar de baja la lavandería')
            ->modalDescription('Deja de aparecer a los usuarios y de recibir pedidos. Sus datos y sus pedidos pasados se quedan en la base. La baja no se deshace desde el panel.')
            ->modalSubmitActionLabel('Dar de baja')
            ->visible(fn (Lavanderia $record): bool => app(EstadoDeLaLavanderia::class)->puedeDarseDeBaja($record))
            ->action(function (Lavanderia $record, Action $action): void {
                app(EstadoDeLaLavanderia::class)->darDeBaja($record);
                $action->success();
            })
            ->successNotificationTitle('La lavandería quedó dada de baja.');
    }

    public static function suspender(): Action
    {
        return Action::make('suspender')
            ->label('Suspender')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Suspender la lavandería')
            ->modalDescription('Mientras esté suspendida, no aparece a los usuarios ni recibe pedidos nuevos. Lo que pasa con sus pedidos en curso todavía no está definido (DD-104).')
            ->modalSubmitActionLabel('Suspender')
            ->visible(fn (Lavanderia $record): bool => app(EstadoDeLaLavanderia::class)->puedeSuspenderse($record))
            ->action(function (Lavanderia $record, Action $action): void {
                app(EstadoDeLaLavanderia::class)->suspender($record);
                $action->success();
            })
            ->successNotificationTitle('La lavandería quedó suspendida.');
    }

    public static function levantarSuspension(): Action
    {
        return Action::make('levantarSuspension')
            ->label('Levantar la suspensión')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Levantar la suspensión')
            ->modalDescription('La lavandería recupera el estado que tenía: activa si ya tenía alta, pendiente si no.')
            ->modalSubmitActionLabel('Levantar la suspensión')
            ->visible(fn (Lavanderia $record): bool => app(EstadoDeLaLavanderia::class)->puedeLevantarseLaSuspension($record))
            ->action(function (Lavanderia $record, Action $action): void {
                app(EstadoDeLaLavanderia::class)->levantarSuspension($record);
                $action->success();
            })
            ->successNotificationTitle('Se levantó la suspensión de la lavandería.');
    }
}
