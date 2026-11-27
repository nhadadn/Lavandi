<?php

namespace App\Http\Requests;

use App\Fotos\ReglasDeLaFoto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * La foto de perfil que sube la app (openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md, "Foto de perfil del
 * usuario"). La forma exacta está en App\Http\Controllers\Api\PerfilController.
 *
 * Las mismas reglas que la foto de la lavandería (App\Fotos\ReglasDeLaFoto): una imagen de los tipos y de hasta el peso
 * de config/fotos.php. Corre antes de guardar nada: un archivo que se rechaza no llega al almacenamiento y la foto
 * anterior se conserva (perfil-usuario-E04).
 */
class SubirFotoDePerfilRequest extends FormRequest
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
            'foto' => ReglasDeLaFoto::reglas(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $tipos = implode(', ', ReglasDeLaFoto::tipos());

        return [
            'foto.required' => 'Elige una foto.',
            'foto.file' => "La foto debe ser una imagen: {$tipos}.",
            'foto.image' => "La foto debe ser una imagen: {$tipos}.",
            'foto.mimes' => "La foto debe ser una imagen: {$tipos}.",
            'foto.max' => 'La foto pesa más de :max KB.',
        ];
    }

    public function foto(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('foto');
    }
}
