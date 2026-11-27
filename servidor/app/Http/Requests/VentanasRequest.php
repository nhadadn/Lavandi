<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La fecha de la consulta de ventanas: un día del calendario, AAAA-MM-DD, que se lee en la hora de la plaza de la
 * lavandería, no en la del teléfono (agenda-E02).
 */
class VentanasRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'Falta la fecha.',
            'fecha.date_format' => 'La fecha va como AAAA-MM-DD, en la hora de la plaza.',
        ];
    }
}
