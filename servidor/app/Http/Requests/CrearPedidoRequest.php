<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El resumen que acepta el usuario: la lavandería, el servicio, la ventana como la dio la agenda y la recogida y la
 * entrega, por sus identificadores públicos (openspec/changes/resumen-y-confirmacion/specs/confirmacion/spec.md). La
 * forma exacta está en App\Http\Controllers\Api\PedidosController.
 *
 * Solo se leen estos campos: cualquier otro que mande la app, como un folio, un estado o que ya pagó, se ignora
 * (confirmacion-E07).
 */
class CrearPedidoRequest extends FormRequest
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
            'servicio' => ['required', 'string'],
            // Su forma y si se ofrece los revisa la agenda, con el error en "ventana" (agenda-E09; confirmacion-E08).
            'ventana' => ['required', 'array'],
            'ventana.inicio' => ['required'],
            'ventana.fin' => ['required'],
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
            'servicio.required' => 'Falta el servicio del pedido.',
            'ventana.required' => 'Falta la ventana de recolección.',
            'ventana.array' => 'La ventana va con su inicio y su fin, como los da la agenda.',
            'ventana.*.required' => 'La ventana va con su inicio y su fin, como los da la agenda.',
            'recogida.required' => 'Falta la dirección de recogida.',
            '*.string' => 'Este campo va en texto.',
        ];
    }
}
