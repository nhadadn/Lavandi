<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El código postal de GET /api/codigos-postales/{codigo_postal}/colonias: cinco números, como en POST /api/direcciones
 * (direcciones-E20). Viene en la ruta, no en el cuerpo, así que se valida desde ahí. La forma exacta está en
 * App\Http\Controllers\Api\CatalogoDelTerritorioController.
 */
class ColoniasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return ['codigo_postal' => $this->route('codigo_postal')];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo_postal' => ['regex:'.GuardarDireccionRequest::CODIGO_POSTAL],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_postal.regex' => 'El código postal son cinco números.',
        ];
    }

    public function codigoPostal(): string
    {
        return (string) $this->validated('codigo_postal');
    }
}
