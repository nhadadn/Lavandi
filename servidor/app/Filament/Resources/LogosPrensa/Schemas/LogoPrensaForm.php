<?php

namespace App\Filament\Resources\LogosPrensa\Schemas;

use App\Fotos\ReglasDeLaFoto;
use App\Models\LogoPrensa;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * El formulario de un logo de prensa en el panel: el nombre del medio, que es también el texto alternativo de la imagen,
 * y la imagen, los dos obligatorios, como en la base, y el interruptor de activo (landing-E17 y landing-E18). Un logo
 * nuevo nace inactivo: no se publica hasta activarlo.
 *
 * La imagen se sube al disco privado de fotos del entorno (config('fotos.disco')), en la carpeta prensa, con un nombre
 * nuevo, y de ahí la lee la ruta pública de la portada (adenda del 2026-11-24 de ADR-0006). Acepta los tipos y el peso de
 * config/fotos.php, los mismos de las demás fotos.
 */
class LogoPrensaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Medio')
                    ->helperText('El nombre del medio o de la mención. Es también el texto alternativo del logo.')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('imagen')
                    ->label('Logo')
                    ->image()
                    ->rules(['mimes:'.implode(',', ReglasDeLaFoto::tipos())])
                    ->maxSize(fn (): int => (int) config('fotos.peso_maximo_kb'))
                    ->disk(fn (): string => (string) config('fotos.disco'))
                    ->directory(LogoPrensa::CARPETA)
                    ->visibility('private')
                    ->required(),
                Toggle::make('activo')
                    ->label('Activo en la portada')
                    ->helperText('La portada muestra todos los activos, en el orden de la lista.')
                    ->default(false),
            ])
            ->columns(1);
    }
}
