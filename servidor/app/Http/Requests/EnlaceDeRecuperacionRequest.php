<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El correo con el que se pide el enlace de recuperación (registro-E22). La regla no consulta si tiene cuenta: la
 * respuesta es la misma en los dos casos (registro-E23).
 */
class EnlaceDeRecuperacionRequest extends FormRequest
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
            'correo' => ['bail', 'required', 'string', 'email', 'max:255'],
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
        ];
    }
}
