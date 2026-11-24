<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Los datos de la entrada con Google: solo el idToken que la app recibió de Google (registro-E16). Cualquier otro
 * campo, como un rol, se ignora.
 */
class SesionConGoogleRequest extends FormRequest
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
            'id_token' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_token.required' => 'Falta el token de Google.',
            'id_token.string' => 'No se pudo entrar con tu cuenta de Google.',
        ];
    }
}
