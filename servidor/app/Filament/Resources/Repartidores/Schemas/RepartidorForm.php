<?php

namespace App\Filament\Resources\Repartidores\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * El formulario de edición del repartidor en el panel: los cinco datos de su registro, con los mismos nombres de campo
 * que el registro de la app (repartidor-E08). No valida por su cuenta: al guardar, EditRepartidor pasa todo por las
 * reglas del registro (App\Http\Requests\RegistroDeRepartidorRequest) y App\Repartidores\EdicionDeRepartidor, y cada
 * error vuelve a su campo.
 *
 * La foto no se guarda al subirla (storeFiles(false)): la edición la sube al disco privado de fotos y reemplaza la
 * anterior. Vacía, la foto no cambia.
 */
class RepartidorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Repartidor')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')->label('Nombre'),
                        TextInput::make('telefono')->label('Teléfono'),
                        TextInput::make('correo')->label('Correo'),
                        TextInput::make('tipo_vehiculo')->label('Tipo de vehículo'),
                        FileUpload::make('foto')
                            ->label('Foto de perfil')
                            ->image()
                            ->disk(fn (): string => (string) config('fotos.disco'))
                            ->visibility('private')
                            ->storeFiles(false)
                            ->helperText('Sube una foto solo para cambiarla.'),
                    ]),
            ]);
    }
}
