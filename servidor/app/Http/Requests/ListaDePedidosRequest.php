<?php

namespace App\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El filtro por fecha del historial: el primer y el último día, AAAA-MM-DD, los dos opcionales y los dos dentro del
 * rango. Se leen como días de la plaza de cada pedido, no del teléfono (historial-E03; design.md, decisión 1).
 */
class ListaDePedidosRequest extends FormRequest
{
    private const FORMATO = 'Y-m-d';

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
            'desde' => ['nullable', 'date_format:'.self::FORMATO],
            'hasta' => [
                'nullable',
                'date_format:'.self::FORMATO,
                // Solo contra un primer día bien escrito: si no lo está, el error es solo suyo.
                Rule::when($this->bienEscrita('desde'), ['after_or_equal:desde']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'desde.date_format' => 'La fecha desde va como AAAA-MM-DD.',
            'hasta.date_format' => 'La fecha hasta va como AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.',
        ];
    }

    /**
     * Si el campo es una fecha AAAA-MM-DD que existe en el calendario, como la acepta date_format.
     */
    private function bienEscrita(string $campo): bool
    {
        $valor = $this->input($campo);

        if (! is_string($valor)) {
            return false;
        }

        $fecha = DateTimeImmutable::createFromFormat('!'.self::FORMATO, $valor);

        return $fecha !== false && $fecha->format(self::FORMATO) === $valor;
    }
}
