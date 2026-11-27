<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El punto de la consulta de cercanas, en grados decimales de WGS 84 (cercanas-E06), y sus filtros opcionales. La
 * ubicación de la persona viaja solo aquí: no se guarda (openspec/changes/lavanderias-cercanas/design.md, decisión 6).
 */
class LavanderiasCercanasRequest extends FormRequest
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
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            // Los filtros, opcionales y combinables (cercanas-E16 a E18). Sus cortes viven en la configuración de la
            // app (design.md, decisión 5).
            'distancia_maxima_metros' => ['nullable', 'integer', 'min:1'],
            'calificacion_minima' => ['nullable', 'numeric', 'between:1,5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitud.required' => 'Falta la latitud del punto.',
            'latitud.numeric' => 'La latitud debe ser un número.',
            'latitud.between' => 'La latitud va de -90 a 90.',
            'longitud.required' => 'Falta la longitud del punto.',
            'longitud.numeric' => 'La longitud debe ser un número.',
            'longitud.between' => 'La longitud va de -180 a 180.',
            'distancia_maxima_metros.integer' => 'La distancia máxima va en metros, sin decimales.',
            'distancia_maxima_metros.min' => 'La distancia máxima debe ser de al menos 1 metro.',
            'calificacion_minima.numeric' => 'La calificación mínima debe ser un número.',
            'calificacion_minima.between' => 'La calificación mínima va de 1 a 5.',
        ];
    }
}
