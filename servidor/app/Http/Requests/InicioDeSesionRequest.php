<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Los datos del inicio de sesión: el correo y la contraseña (registro-E07).
 */
class InicioDeSesionRequest extends FormRequest
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
            'correo' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'correo.required' => 'Escribe tu correo.',
            'correo.string' => 'El correo no es válido.',
            'password.required' => 'Escribe tu contraseña.',
            'password.string' => 'La contraseña no es válida.',
        ];
    }
}
