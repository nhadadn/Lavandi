<?php

namespace App\Filament\Resources\Lavanderias\Schemas;

use App\Lavanderias\ServiciosYVentanas;
use App\Models\Municipio;
use App\Models\TipoServicio;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * El formulario de edición de una lavandería en el panel: los mismos datos del registro de la app, con los mismos
 * nombres de campo (alta-lavanderias-E13). No valida por su cuenta: al guardar, EditLavanderia pasa todo por
 * App\Lavanderias\ReglasDeLaLavanderia y App\Lavanderias\EdicionDeLavanderia, como la app, y cada error vuelve a su
 * campo.
 *
 * La foto no se guarda al subirla (storeFiles(false)): la edición la sube al disco privado de fotos y reemplaza la
 * principal, como en la app. Vacía, la foto no cambia.
 */
class LavanderiaForm
{
    /** Los días de la semana, del 1, el lunes, al 7 (ISO 8601), como en lavanderia_horarios. */
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lavandería')
                    ->schema([
                        TextInput::make('nombre')->label('Nombre'),
                        FileUpload::make('foto')
                            ->label('Foto')
                            ->image()
                            ->disk(fn (): string => (string) config('fotos.disco'))
                            ->visibility('private')
                            ->storeFiles(false)
                            ->helperText('Sube una foto solo para cambiarla.'),
                    ]),
                Section::make('Dirección')
                    ->statePath('direccion')
                    ->columns(2)
                    ->schema([
                        TextInput::make('calle')->label('Calle'),
                        TextInput::make('numero')->label('Número'),
                        TextInput::make('colonia')->label('Colonia'),
                        TextInput::make('codigo_postal')->label('Código postal'),
                        Select::make('municipio')
                            ->label('Municipio')
                            ->options(fn (): array => self::municipios())
                            ->searchable(),
                    ]),
                Section::make('Servicios')
                    ->schema([
                        Repeater::make('servicios')
                            ->hiddenLabel()
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar servicio')
                            ->schema([
                                Select::make('tipo')->label('Servicio')->options(fn (): array => self::tiposDeServicio()),
                                TextInput::make('precio')->label('Precio por kilo, en pesos'),
                            ]),
                    ]),
                Section::make('Horario')
                    ->description('Ventanas de una hora por día, en la hora de la plaza.')
                    ->schema([
                        Repeater::make('ventanas')
                            ->hiddenLabel()
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar ventana')
                            ->schema([
                                Select::make('dia_semana')->label('Día')->options(self::DIAS),
                                TextInput::make('hora_inicio')->label('Inicio')->placeholder('HH:MM'),
                                TextInput::make('hora_fin')->label('Fin')->placeholder('HH:MM'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Los municipios del catálogo del territorio, por su código: "Durango, Durango".
     *
     * @return array<string, string>
     */
    private static function municipios(): array
    {
        return Municipio::query()->with('entidadFederativa')->orderBy('nombre')->get()
            ->mapWithKeys(fn (Municipio $municipio): array => [
                $municipio->codigo => trim("{$municipio->nombre}, {$municipio->entidadFederativa?->nombre}", ', '),
            ])->all();
    }

    /**
     * Los tipos de servicio que se pueden registrar hoy: los activos con su unidad definida. El lavado en seco espera
     * DD-010 (ServiciosYVentanas::UNIDAD_POR_TIPO).
     *
     * @return array<string, string>
     */
    private static function tiposDeServicio(): array
    {
        return TipoServicio::query()
            ->where('activo', true)
            ->whereIn('codigo', array_keys(ServiciosYVentanas::UNIDAD_POR_TIPO))
            ->orderBy('id_tipos_servicio')
            ->pluck('nombre', 'codigo')
            ->all();
    }
}
