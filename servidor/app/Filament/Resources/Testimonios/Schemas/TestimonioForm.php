<?php

namespace App\Filament\Resources\Testimonios\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * El formulario de un testimonio en el panel: autor y texto, los dos obligatorios, como en la base, y el interruptor de
 * activo (landing-E15 y landing-E16). Un testimonio nuevo nace inactivo: no se publica hasta activarlo.
 */
class TestimonioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('autor')
                    ->label('Autor')
                    ->helperText('Cómo firma la persona su opinión.')
                    ->required()
                    ->maxLength(255),
                Textarea::make('texto')
                    ->label('Testimonio')
                    ->required()
                    ->rows(4),
                Toggle::make('activo')
                    ->label('Activo en la portada')
                    ->helperText('La portada muestra hasta cinco activos, en el orden de la lista.')
                    ->default(false),
            ])
            ->columns(1);
    }
}
