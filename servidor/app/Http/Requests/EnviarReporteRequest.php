<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El formulario de reporte de Soporte: la descripción y, si la persona quiere, uno de sus pedidos
 * (openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md, "Formulario de reporte"). La forma exacta está en
 * App\Http\Controllers\Api\ReportesDeSoporteController.
 *
 * - La descripción es obligatoria (perfil-usuario-E12). Laravel quita los espacios de los lados y deja vacío lo que
 *   queda en blanco, así que una solo con espacios se rechaza igual que una vacía. El tope de 5000 caracteres protege la
 *   tabla; no lo pide la spec.
 * - El pedido es el id_publico de un pedido de la cuenta. Que sea de ella lo decide el componente de autorización, no
 *   esta validación (perfil-usuario-E13).
 *
 * Solo se leen estos dos campos: cualquier otro se ignora.
 */
class EnviarReporteRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:5000'],
            'pedido' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'Cuéntanos qué pasó.',
            'descripcion.string' => 'La descripción va en texto.',
            'descripcion.max' => 'La descripción es demasiado larga.',
            'pedido.string' => 'El pedido va en texto.',
        ];
    }
}
