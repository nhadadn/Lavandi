<?php

namespace App\Http\Requests;

use App\Validacion\Contrasena;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los datos del formulario de la contraseña nueva: el token y el correo del enlace, y la contraseña nueva dos veces,
 * con la regla configurada (registro-E24; ADR-0018, decisión 2). Si no pasan, la página vuelve al formulario con el
 * error junto a su campo.
 */
class ContrasenaNuevaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'correo' => ['required', 'string'],
            'password' => [...Contrasena::reglas(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'El enlace no está completo. Ábrelo otra vez desde el correo.',
            'token.string' => 'El enlace no está completo. Ábrelo otra vez desde el correo.',
            'correo.required' => 'El enlace no está completo. Ábrelo otra vez desde el correo.',
            'correo.string' => 'El enlace no está completo. Ábrelo otra vez desde el correo.',
            ...Contrasena::mensajes(),
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ];
    }
}
