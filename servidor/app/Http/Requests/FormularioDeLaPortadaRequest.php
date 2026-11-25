<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lo que comparten los dos formularios de la portada, el de interés de lavanderías y el de contacto
 * (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, secciones 5 y 11): todos sus campos son obligatorios y el correo tiene
 * que estar bien escrito (landing-E21).
 *
 * - Los campos van dentro del nombre del formulario, interes[nombre] o contacto[correo], para que los errores y los
 *   valores de uno no aparezcan en el otro.
 * - Laravel quita los espacios de los lados y deja vacío lo que queda en blanco: un campo solo con espacios se rechaza
 *   igual que uno vacío.
 * - Rechazado, el envío vuelve a la sección de su formulario en la portada, con el error junto a cada campo que falta.
 * - Los topes de largo protegen el servidor; no los pide la spec.
 * - Qué campos lleva cada formulario es DD-119: mientras tanto, lo mínimo para responder (design.md, lectura 5).
 */
abstract class FormularioDeLaPortadaRequest extends FormRequest
{
    /** El nombre del formulario, que agrupa sus campos. */
    abstract public static function formulario(): string;

    /** La sección de la portada donde vive el formulario, a la que vuelve un envío rechazado. */
    abstract public static function seccion(): string;

    /**
     * Las reglas de cada campo, sin el nombre del formulario.
     *
     * @return array<string, list<string>>
     */
    abstract protected function reglasDeLosCampos(): array;

    /**
     * Los mensajes de cada regla de cada campo, sin el nombre del formulario.
     *
     * @return array<string, string>
     */
    abstract protected function mensajesDeLosCampos(): array;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [static::formulario() => ['nullable', 'array']];

        foreach ($this->reglasDeLosCampos() as $campo => $deCampo) {
            $reglas[static::formulario().'.'.$campo] = $deCampo;
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajes = [static::formulario().'.array' => 'El formulario no se envió completo.'];

        foreach ($this->mensajesDeLosCampos() as $regla => $mensaje) {
            $mensajes[static::formulario().'.'.$regla] = $mensaje;
        }

        return $mensajes;
    }

    /**
     * Rechazado, el envío vuelve a la sección de su formulario.
     */
    protected function getRedirectUrl(): string
    {
        return route('portada').'#'.static::seccion();
    }
}
