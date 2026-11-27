<?php

namespace App\Http\Requests;

use App\Models\Dispositivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El token de Firebase del teléfono y su plataforma, como los manda la app al iniciar sesión y cuando Firebase lo
 * renueva (avisos-E03 y avisos-E04). La forma exacta está en App\Http\Controllers\Api\DispositivosController.
 *
 * - El token es texto y obligatorio. Firebase no fija su largo; el tope de 4096 caracteres protege la tabla y no lo
 *   pide la spec.
 * - La plataforma es ios o android, las que acepta el CHECK de dispositivos.
 *
 * Solo se leen estos dos campos: cualquier otro se ignora.
 */
class RegistroDeTelefonoRequest extends FormRequest
{
    /** El largo máximo del token que se acepta. */
    public const LARGO_MAXIMO_DEL_TOKEN = 4096;

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
            'token_fcm' => ['required', 'string', 'max:'.self::LARGO_MAXIMO_DEL_TOKEN],
            'plataforma' => ['required', 'string', Rule::in(Dispositivo::PLATAFORMAS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token_fcm.required' => 'Falta el token del teléfono.',
            'token_fcm.string' => 'El token del teléfono va en texto.',
            'token_fcm.max' => 'El token del teléfono es demasiado largo.',
            'plataforma.required' => 'Falta la plataforma del teléfono.',
            'plataforma.string' => 'La plataforma va en texto.',
            'plataforma.in' => 'La plataforma es ios o android.',
        ];
    }
}
