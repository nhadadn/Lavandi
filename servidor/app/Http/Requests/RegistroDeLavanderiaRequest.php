<?php

namespace App\Http\Requests;

use App\Lavanderias\ReglasDeLaLavanderia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * El registro de la lavandería desde la app, con los cinco datos del Módulo B #1 (alta-lavanderias-E01 y E02). Las
 * reglas son las de App\Lavanderias\ReglasDeLaLavanderia, las mismas de la edición; la forma exacta está en
 * App\Http\Controllers\Api\LavanderiaDelSocioController.
 *
 * Esta validación corre antes de consultar la geocodificación y de subir la foto: un registro incompleto no gasta
 * consultas ni deja archivos.
 */
class RegistroDeLavanderiaRequest extends FormRequest
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
        return ReglasDeLaLavanderia::para(ReglasDeLaLavanderia::CAMPOS);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ReglasDeLaLavanderia::mensajes();
    }

    public function foto(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('foto');
    }
}
