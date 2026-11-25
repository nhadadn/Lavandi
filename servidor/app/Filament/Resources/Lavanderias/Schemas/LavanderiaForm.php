<?php

namespace App\Filament\Resources\Lavanderias\Schemas;

use App\Filament\Resources\Lavanderias\Pages\EditLavanderia;
use App\Lavanderias\FotosDeLaLavanderia;
use App\Lavanderias\ReglasDeLaLavanderia;
use App\Lavanderias\ServiciosYVentanas;
use App\Models\Lavanderia;
use App\Models\Municipio;
use App\Models\TipoServicio;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * El formulario de edición de una lavandería en el panel: los mismos datos de la edición de la app, con los mismos
 * nombres de campo (alta-lavanderias-E13 y E31). No valida por su cuenta, salvo el máximo de fotos del selector (abajo):
 * al guardar, EditLavanderia pasa todo por App\Lavanderias\ReglasDeLaLavanderia y App\Lavanderias\EdicionDeLavanderia,
 * como la app, y cada error vuelve a su campo.
 *
 * Las fotos son la lista del perfil, en su orden, y la primera es la principal: la foto del registro es una de ellas
 * (alta-lavanderias-E27 a E29). Se pueden quitar, ordenar y agregar al final hasta el máximo de la configuración, que
 * también frena al selector de archivos (alta-lavanderias-E30). Las nuevas no se guardan al subirlas
 * (storeFiles(false)): la edición las sube al disco privado de fotos, como en la app.
 *
 * Dos cuidados del selector, para que las fotos que ya tiene no estorben al guardar otro dato:
 * - No revisa el disco al abrir (fetchFileInformation(false)). Si lo hiciera, Filament quitaría de la lista la foto cuyo
 *   archivo no encuentra, o que no pudo revisar por una falla momentánea del almacenamiento, y la lista parecería
 *   cambiada.
 * - Su propia regla del máximo (maxFiles()) corre antes que la edición y dice el mensaje de la edición. A una lavandería
 *   que ya tiene más fotos que el máximo, porque se bajó, le deja las que tiene: la regla de la edición, que solo corre
 *   si la lista cambia, le pide quedar en el máximo.
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
                        Textarea::make('descripcion')->label('Descripción')->rows(4),
                        FileUpload::make('fotos')
                            ->label('Fotos')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            // Cuenta las fotos que la página leyó al abrir, no las de la base: si la app las cambió
                            // mientras tanto, la edición responde que cambiaron, no con el mensaje del máximo.
                            ->maxFiles(fn (?Lavanderia $record, mixed $livewire): int => max(
                                FotosDeLaLavanderia::maximo(),
                                $livewire instanceof EditLavanderia ? count($livewire->fotosAlAbrir) : ($record?->fotos()->count() ?? 0),
                            ))
                            ->validationMessages(['max' => fn (): string => str_replace(
                                ':max',
                                (string) FotosDeLaLavanderia::maximo(),
                                ReglasDeLaLavanderia::mensajes()['fotos.max'],
                            )])
                            ->disk(fn (): string => (string) config('fotos.disco'))
                            ->visibility('private')
                            ->fetchFileInformation(false)
                            ->storeFiles(false)
                            ->helperText('La primera es la principal. Arrástralas para cambiar el orden.'),
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
