<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La calificación del servicio de un pedido: de 1 a 5 estrellas, un número entero, y un comentario opcional en texto
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14; historial-E06 a E08). Un comentario vacío o solo con
 * espacios llega vacío: es una calificación sin comentario. El largo máximo del comentario es un límite técnico de
 * config/pedidos.php.
 *
 * Cada campo da un solo error, el primero que falla (bail).
 */
class CalificarPedidoRequest extends FormRequest
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
            'estrellas' => ['bail', 'required', 'integer', 'between:1,5'],
            'comentario' => ['bail', 'nullable', 'string', 'max:'.$this->maximo()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estrellas.required' => 'Elige de 1 a 5 estrellas.',
            'estrellas.integer' => 'Las estrellas van como un número entero de 1 a 5.',
            'estrellas.between' => 'Las estrellas van de 1 a 5.',
            'comentario.string' => 'El comentario va en texto.',
            'comentario.max' => 'El comentario no puede pasar de :max caracteres.',
        ];
    }

    private function maximo(): int
    {
        return (int) config('pedidos.comentario_de_calificacion_maximo');
    }
}
