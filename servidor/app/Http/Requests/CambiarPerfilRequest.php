<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El cambio del perfil: por ahora, solo el nombre (openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md,
 * "Nombre del usuario en el perfil"). La forma exacta está en App\Http\Controllers\Api\PerfilController.
 *
 * Solo se lee el nombre: cualquier otro campo, como un número de tarjeta, se ignora y no llega a ninguna tabla
 * (perfil-usuario-E06). El correo no se cambia desde el perfil: el numeral 15 no lo lista.
 *
 * Laravel quita los espacios de los lados y deja vacío lo que queda en blanco, así que un nombre solo con espacios se
 * rechaza igual que uno vacío (perfil-usuario-E02).
 */
class CambiarPerfilRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe tu nombre.',
            'nombre.string' => 'El nombre va en texto.',
            'nombre.max' => 'El nombre es demasiado largo.',
        ];
    }
}
