<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El cierre de la sesión en un teléfono: opcionalmente, el token de Firebase de ese teléfono, para que la API lo borre
 * y los avisos de la cuenta dejen de llegarle (avisos-E05). La forma exacta está en
 * App\Http\Controllers\Api\SesionController.
 *
 * Sin el token, la sesión se cierra igual: es el caso del teléfono que nunca dio permiso de avisos.
 */
class CierreDeSesionRequest extends FormRequest
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
            'token_fcm' => ['nullable', 'string', 'max:'.RegistroDeTelefonoRequest::LARGO_MAXIMO_DEL_TOKEN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token_fcm.string' => 'El token del teléfono va en texto.',
            'token_fcm.max' => 'El token del teléfono es demasiado largo.',
        ];
    }
}
