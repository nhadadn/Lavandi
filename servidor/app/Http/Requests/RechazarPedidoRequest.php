<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El motivo con que la lavandería rechaza un pedido: obligatorio, texto y con el largo máximo de
 * config/pedidos.php (pedidos-lavanderia-E13). Llega sin los espacios de las orillas, y uno de puros espacios llega
 * vacío: los recortan los middleware globales de Laravel.
 */
class RechazarPedidoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'max:'.$this->maximo()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe el motivo del rechazo.',
            'motivo.string' => 'Escribe el motivo del rechazo.',
            'motivo.max' => 'El motivo no puede pasar de :max caracteres.',
        ];
    }

    private function maximo(): int
    {
        return (int) config('pedidos.motivo_de_rechazo_maximo');
    }
}
