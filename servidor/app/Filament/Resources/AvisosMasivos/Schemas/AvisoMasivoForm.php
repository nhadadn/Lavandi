<?php

namespace App\Filament\Resources\AvisosMasivos\Schemas;

use App\Avisos\CreacionDeAvisoMasivo;
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
 * Las reglas, los topes y los mensajes de los dos campos son los de App\Avisos\CreacionDeAvisoMasivo, que crea el aviso
 * del panel y el de la API (administracion-app-E11). Se aplican a cada campo como en App\Filament\CamposDelRango, sin
 * cambiarlas:
 * - "required" va con required(), que además marca el campo como obligatorio en el formulario;
 * - el tope ("max") va con maxLength(), que además se lo pone al campo en el navegador;
 * - las demás van como reglas del campo;
 * - los mensajes van como mensajes del campo, que el panel pone bajo él.
 *
 * Los dos campos se recortan antes de validarlos, como los recorta esa clase: Livewire no pasa por el middleware
 * TrimStrings, y sin el recorte el panel contaría los espacios de los extremos que la API no cuenta.
 */
class AvisoMasivoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titulo')
                    ->label('Título')
                    ->trim()
                    ->required(self::esObligatorio('titulo'))
                    ->maxLength(CreacionDeAvisoMasivo::TOPE_DEL_TITULO)
                    ->rules(self::demasReglasDe('titulo', CreacionDeAvisoMasivo::TOPE_DEL_TITULO))
                    ->validationMessages(self::mensajesDe('titulo')),
                Textarea::make('mensaje')
                    ->label('Texto')
                    ->trim()
                    ->required(self::esObligatorio('mensaje'))
                    ->maxLength(CreacionDeAvisoMasivo::TOPE_DEL_MENSAJE)
                    ->rules(self::demasReglasDe('mensaje', CreacionDeAvisoMasivo::TOPE_DEL_MENSAJE))
                    ->validationMessages(self::mensajesDe('mensaje'))
                    ->rows(4),
                TextEntry::make('destinatarios')
                    ->label('A quién le llega')
                    ->state('A todas las cuentas de la app con un teléfono registrado para avisos, salvo las bloqueadas.'),
            ])
            ->columns(1);
    }

    private static function esObligatorio(string $campo): bool
    {
        return in_array('required', CreacionDeAvisoMasivo::reglas()[$campo], true);
    }

    /**
     * Las reglas de la creación del aviso para ese campo, menos las que el campo de Filament ya pone por su cuenta:
     * required() y maxLength() con su tope. Si cambian las reglas de CreacionDeAvisoMasivo::reglas(), esto se revisa en
     * el mismo cambio.
     *
     * @return list<string>
     */
    private static function demasReglasDe(string $campo, int $tope): array
    {
        return array_values(array_diff(CreacionDeAvisoMasivo::reglas()[$campo], ['required', "max:{$tope}"]));
    }

    /**
     * Los mensajes de la creación del aviso para ese campo, por regla, como los pide Filament.
     *
     * @return array<string, string>
     */
    private static function mensajesDe(string $campo): array
    {
        $mensajes = [];

        foreach (CreacionDeAvisoMasivo::mensajes() as $clave => $mensaje) {
            if (str_starts_with($clave, "{$campo}.")) {
                $mensajes[substr($clave, strlen($campo) + 1)] = $mensaje;
            }
        }

        return $mensajes;
    }
}
