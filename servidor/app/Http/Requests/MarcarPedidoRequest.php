<?php

namespace App\Http\Requests;

use App\Pedidos\CambioDeEstado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El botón que toca la lavandería: uno de los cuatro de su módulo, "Recolectado → En proceso → Listo → En proceso de
 * entrega" (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #3), por el código del estado al que lleva. Cualquier
 * otro valor, como confirmado, entregado, aceptado o rechazado, no es un botón (estados-E05). Si es el paso siguiente
 * del pedido lo decide App\Pedidos\CambioDeEstado.
 */
class MarcarPedidoRequest extends FormRequest
{
    private const NO_ES_UN_BOTON = 'Ese no es un botón de la lavandería.';

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
            'estado' => ['required', 'string', Rule::in(CambioDeEstado::BOTONES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => self::NO_ES_UN_BOTON,
            'estado.string' => self::NO_ES_UN_BOTON,
            'estado.in' => self::NO_ES_UN_BOTON,
        ];
    }
}
