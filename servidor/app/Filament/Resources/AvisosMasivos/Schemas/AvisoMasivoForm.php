<?php

namespace App\Filament\Resources\AvisosMasivos\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * El formulario de un aviso masivo: su título y su texto, los dos obligatorios, como en la base (administracion-E23).
 *
 * El aviso es global: va a todas las cuentas con un teléfono registrado. Elegir un segmento espera DD-113
 * (administracion-E24), así que el formulario no lo pide.
 *
 * El texto lleva un tope de caracteres para que el mensaje quepa en lo que acepta FCM por mensaje (grado E, SIN
 * VERIFICAR: 4096 bytes; openspec/changes/administracion/design.md, "El panel").
 */
class AvisoMasivoForm
{
    /** El tope del texto, en caracteres. */
    public const TOPE_DEL_MENSAJE = 1000;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titulo')
                    ->label('Título')
                    ->required()
                    ->maxLength(255),
                Textarea::make('mensaje')
                    ->label('Texto')
                    ->required()
                    ->maxLength(self::TOPE_DEL_MENSAJE)
                    ->rows(4),
                TextEntry::make('destinatarios')
                    ->label('A quién le llega')
                    ->state('A todas las cuentas de la app con un teléfono registrado para avisos, salvo las bloqueadas.'),
            ])
            ->columns(1);
    }
}
