<?php

namespace App\Filament\Resources\Usuarios\Actions;

use App\Autorizacion\Autorizacion;
use App\Identidad\BloqueoDeCuenta;
use App\Models\Usuario;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

/**
 * Bloquear a un usuario y levantar su bloqueo, en la lista de usuarios y en su ficha (administracion-E03 y E04;
 * NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3). Cada botón solo aparece cuando se puede usar: bloquear en
 * una cuenta activa, levantar en una bloqueada. Quién puede bloquear a quién lo decide el componente de autorización
 * (Autorizacion::puedeBloquear()); qué hace el bloqueo, App\Identidad\BloqueoDeCuenta.
 */
final class AccionesDeBloqueo
{
    /**
     * @return list<Action>
     */
    public static function todas(): array
    {
        return [self::bloquear(), self::levantarBloqueo()];
    }

    public static function bloquear(): Action
    {
        return Action::make('bloquear')
            ->label('Bloquear')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Bloquear al usuario')
            ->modalDescription('Se cierran sus sesiones abiertas y no podrá entrar a la app con su correo ni con Google hasta que se levante el bloqueo. Sus pedidos no cambian: qué pasa con uno en curso todavía no está definido (DD-016).')
            ->modalSubmitActionLabel('Bloquear')
            ->visible(fn (Usuario $record): bool => $record->estado !== Usuario::BLOQUEADO)
            ->authorize(fn (Usuario $record): bool => self::puedeBloquear($record))
            ->action(function (Usuario $record, Action $action): void {
                app(BloqueoDeCuenta::class)->bloquear($record);
                $action->success();
            })
            ->successNotificationTitle('El usuario quedó bloqueado.');
    }

    public static function levantarBloqueo(): Action
    {
        return Action::make('levantarBloqueo')
            ->label('Levantar el bloqueo')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Levantar el bloqueo')
            ->modalDescription('El usuario vuelve a estar activo y puede iniciar sesión otra vez.')
            ->modalSubmitActionLabel('Levantar el bloqueo')
            ->visible(fn (Usuario $record): bool => $record->estado === Usuario::BLOQUEADO)
            ->authorize(fn (Usuario $record): bool => self::puedeBloquear($record))
            ->action(function (Usuario $record, Action $action): void {
                app(BloqueoDeCuenta::class)->levantar($record);
                $action->success();
            })
            ->successNotificationTitle('Se levantó el bloqueo del usuario.');
    }

    private static function puedeBloquear(Usuario $cuenta): bool
    {
        /** @var Usuario $quien */
        $quien = Filament::auth()->user();

        return app(Autorizacion::class)->puedeBloquear($quien, $cuenta);
    }
}
