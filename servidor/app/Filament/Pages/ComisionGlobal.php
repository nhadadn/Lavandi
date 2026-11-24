<?php

namespace App\Filament\Pages;

use App\Autorizacion\Autorizacion;
use App\Comisiones\PorcentajesDeComision;
use App\Models\Usuario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * El porcentaje de comisión global, en el panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #5, "Porcentaje
 * de comisión por socio o global"; openspec/changes/administracion/, tarea 3.1; administracion-E11 y E13).
 *
 * Es uno solo para todo el sistema: aplica a toda lavandería sin porcentaje propio, de cualquier plaza (página
 * ComisionesPorLavanderia; design.md, Open Questions 4). La página muestra el vigente, o "Sin porcentaje", desde cuándo
 * rige y el botón para fijarlo. Ninguno viene de fábrica: los captura EL CLIENTE. Quién lo fija lo dice el componente de
 * autorización; las reglas y la vigencia, App\Comisiones\PorcentajesDeComision.
 */
class ComisionGlobal extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Comisiones';

    protected static ?string $navigationLabel = 'Comisión global';

    protected static ?string $title = 'Comisión global';

    protected static ?string $slug = 'comision-global';

    public static function canAccess(): bool
    {
        $cuenta = Filament::auth()->user();

        return $cuenta instanceof Usuario && app(Autorizacion::class)->puedeFijarLaComisionGlobal($cuenta);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('fijarPorcentaje')
                ->label('Fijar porcentaje')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalHeading('Porcentaje global')
                ->modalDescription('Aplica a toda lavandería, de cualquier plaza, que no tenga porcentaje propio. El anterior queda como historial.')
                ->modalSubmitActionLabel('Guardar')
                ->fillForm(fn (): array => ['porcentaje' => self::porcentajes()->globalVigente()?->porcentaje])
                ->schema([self::campoDelPorcentaje()])
                ->authorize(fn (): bool => self::canAccess())
                ->action(function (array $data, Action $action): void {
                    self::porcentajes()->fijarGlobal($data['porcentaje']);
                    $action->success();
                })
                ->successNotificationTitle('Se guardó el porcentaje global.'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('porcentaje')
                ->label('Porcentaje global')
                ->state(fn (): string => ($vigente = self::porcentajes()->globalVigente()) === null
                    ? 'Sin porcentaje'
                    : "{$vigente->porcentaje} %"),
            TextEntry::make('vigente_desde')
                ->label('Vigente desde')
                // En UTC: el global no es de una plaza, así que no tiene la hora de ninguna.
                ->state(fn (): ?string => ($vigente = self::porcentajes()->globalVigente()) === null
                    ? null
                    : $vigente->vigente_desde->utc()->format('d/m/Y H:i').' UTC')
                ->placeholder('—'),
        ]);
    }

    /**
     * El campo del porcentaje, con las reglas y los mensajes de las comisiones (administracion-E13). Lo usa también la
     * página del porcentaje propio.
     */
    public static function campoDelPorcentaje(): TextInput
    {
        $mensajes = [];

        foreach (PorcentajesDeComision::mensajes() as $clave => $mensaje) {
            $mensajes[substr($clave, strlen('porcentaje.'))] = $mensaje;
        }

        return TextInput::make('porcentaje')
            ->label('Porcentaje')
            ->suffix('%')
            ->rules(PorcentajesDeComision::reglas()['porcentaje'])
            ->validationMessages($mensajes);
    }

    private static function porcentajes(): PorcentajesDeComision
    {
        return app(PorcentajesDeComision::class);
    }
}
