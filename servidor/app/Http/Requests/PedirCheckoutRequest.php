<?php

namespace App\Http\Requests;

use App\Models\Pago;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Lo que manda la app para pedir el checkout de su pedido: solo el pedido y el método (pago-E01 y pago-E02). Cualquier
 * otro campo, como un monto o un estado, se ignora: el monto es el del cobro que guarda servidor/.
 *
 * Primero se valida solo el pedido, para buscarlo con el componente de autorización; el método se valida después, con
 * metodo(). Así el pedido de otra cuenta responde 404 aunque el método venga mal (ADR-0019, decisión 3).
 *
 * El método es tarjeta. El efectivo espera DD-056 (pago-E12), así que todavía no está disponible.
 */
class PedirCheckoutRequest extends FormRequest
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
            'pedido' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pedido.required' => 'Falta el pedido.',
            'pedido.string' => 'Falta el pedido.',
            'metodo.required' => 'Elige cómo pagar.',
            'metodo.string' => 'Elige cómo pagar.',
        ];
    }

    /**
     * El método, ya validado: tarjeta.
     *
     * @throws ValidationException
     */
    public function metodo(): string
    {
        return $this->validate([
            'metodo' => ['required', 'string', function (string $campo, mixed $valor, Closure $falla): void {
                if ($valor === Pago::EFECTIVO) {
                    $falla('El pago en efectivo todavía no está disponible.');
                } elseif ($valor !== Pago::TARJETA) {
                    $falla('El método de pago no es válido.');
                }
            }],
        ], $this->messages())['metodo'];
    }
}
