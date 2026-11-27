<?php

namespace App\Filament\Resources\Repartidores\Actions;

use App\Models\Plaza;
use App\Models\Repartidor;
use App\Models\Usuario;
use App\Repartidores\EstadoDelRepartidor;
use App\Repartidores\PlazasDelAlta;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

/**
 * Las acciones de estado del repartidor en el panel, en la lista y en la página de cada uno (repartidor-E05, E06, E07
 * y E31; ADR-0003). Cada botón solo aparece cuando la transición se puede hacer, y la regla vive en
 * App\Repartidores\EstadoDelRepartidor, no aquí.
 */
final class AccionesDeEstado
{
    /**
     * @return list<Action>
     */
    public static function todas(): array
    {
        return [self::darDeAlta(), self::suspender(), self::levantarSuspension(), self::darDeBaja()];
    }

    /**
     * El alta autoriza el registro pendiente y fija su plaza, que elige la administración entre las plazas activas
     * (design.md, lecturas 1 y 2).
     */
    public static function darDeAlta(): Action
    {
        return Action::make('darDeAlta')
            ->label('Dar de alta')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->modalHeading('Dar de alta al repartidor')
            ->modalDescription('Queda activo en la plaza que elijas y ve los pedidos que se le asignen en ella. La validación de sus datos ocurre fuera de la plataforma.')
            ->modalSubmitActionLabel('Dar de alta')
            ->schema([
                Select::make('plaza')
                    ->label('Plaza')
                    ->options(fn (): array => self::plazasActivas())
                    ->required(),
            ])
            ->visible(fn (Repartidor $record): bool => app(EstadoDelRepartidor::class)->puedeDarseDeAlta($record))
            ->action(function (Repartidor $record, array $data, Action $action): void {
                /** @var Plaza $plaza */
                $plaza = app(PlazasDelAlta::class)->de(self::cuenta())->findOrFail($data['plaza']);
                app(EstadoDelRepartidor::class)->darDeAlta($record, $plaza);
                $action->success();
            })
            ->successNotificationTitle('El repartidor quedó dado de alta.');
    }

    public static function suspender(): Action
    {
        return Action::make('suspender')
            ->label('Suspender')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Suspender al repartidor')
            ->modalDescription('Mientras esté suspendido, no ve ni mueve pedidos. Sigue entrando a la app, que le dice que está suspendido.')
            ->modalSubmitActionLabel('Suspender')
            ->visible(fn (Repartidor $record): bool => app(EstadoDelRepartidor::class)->puedeSuspenderse($record))
            ->action(function (Repartidor $record, Action $action): void {
                app(EstadoDelRepartidor::class)->suspender($record);
                $action->success();
            })
            ->successNotificationTitle('El repartidor quedó suspendido.');
    }

    public static function levantarSuspension(): Action
    {
        return Action::make('levantarSuspension')
            ->label('Levantar la suspensión')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Levantar la suspensión')
            ->modalDescription('El repartidor vuelve a estar activo, en su misma plaza, y recupera sus pedidos.')
            ->modalSubmitActionLabel('Levantar la suspensión')
            ->visible(fn (Repartidor $record): bool => app(EstadoDelRepartidor::class)->puedeLevantarseLaSuspension($record))
            ->action(function (Repartidor $record, Action $action): void {
                app(EstadoDelRepartidor::class)->levantarSuspension($record);
                $action->success();
            })
            ->successNotificationTitle('Se levantó la suspensión del repartidor.');
    }

    public static function darDeBaja(): Action
    {
        return Action::make('darDeBaja')
            ->label('Dar de baja')
            ->icon(Heroicon::OutlinedArchiveBoxXMark)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Dar de baja al repartidor')
            ->modalDescription('Ya no podrá iniciar sesión y se cierran sus sesiones abiertas. Sus entregas completadas se quedan en la base. La baja no se deshace desde el panel.')
            ->modalSubmitActionLabel('Dar de baja')
            ->visible(fn (Repartidor $record): bool => app(EstadoDelRepartidor::class)->puedeDarseDeBaja($record))
            ->action(function (Repartidor $record, Action $action): void {
                app(EstadoDelRepartidor::class)->darDeBaja($record);
                $action->success();
            })
            ->successNotificationTitle('El repartidor quedó dado de baja.');
    }

    /**
     * Las plazas del alta, las activas que ve la administración, por su llave: "Durango". Cuáles son lo dice
     * App\Repartidores\PlazasDelAlta, que usa también la API de la administración.
     *
     * @return array<int, string>
     */
    private static function plazasActivas(): array
    {
        return app(PlazasDelAlta::class)->de(self::cuenta())
            ->orderBy('nombre')
            ->pluck('nombre', 'id_plazas')
            ->all();
    }

    private static function cuenta(): Usuario
    {
        /** @var Usuario */
        return Filament::auth()->user();
    }
}
