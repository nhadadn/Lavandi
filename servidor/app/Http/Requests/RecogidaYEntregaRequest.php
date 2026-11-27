<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La recogida y la entrega que el usuario eligió para un pedido con una lavandería, por sus identificadores públicos
 * (openspec/changes/direcciones/specs/direcciones/spec.md, "Dirección de recogida y dirección de entrega"). La forma
 * exacta está en App\Http\Controllers\Api\DireccionesController.
 *
 * Sin entrega, la entrega es la misma dirección de la recogida (direcciones-E09).
 */
class RecogidaYEntregaRequest extends FormRequest
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
            'lavanderia' => ['required', 'string'],
            'recogida' => ['required', 'string'],
            'entrega' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lavanderia.required' => 'Falta la lavandería del pedido.',
            'recogida.required' => 'Falta la dirección de recogida.',
            '*.string' => 'Este campo debe ser el identificador público, en texto.',
        ];
    }
}
