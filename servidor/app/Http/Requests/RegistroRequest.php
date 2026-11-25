<?php

namespace App\Http\Requests;

use App\Validacion\Contrasena;
use App\Validacion\CorreoSinCuenta;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los datos del registro: solo el correo y la contraseña (C-225). Cualquier otro campo, como un rol, se ignora
 * (registro-E01).
 */
class RegistroRequest extends FormRequest
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
            'correo' => ['bail', 'required', 'string', 'email', 'max:255', new CorreoSinCuenta],
            'password' => Contrasena::reglas(),
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
            'correo.email' => 'El correo no es válido.',
            'correo.max' => 'El correo no puede tener más de :max caracteres.',
            ...Contrasena::mensajes(),
        ];
    }
}
