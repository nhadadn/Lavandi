<?php

namespace App\Http\Requests;

/**
 * El formulario de interés de lavanderías, de "Lavanderías partner" (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2,
 * sección 5): quién escribe, su lavandería y su correo, los tres obligatorios (landing-E21).
 */
class FormularioDeInteresRequest extends FormularioDeLaPortadaRequest
{
    public static function formulario(): string
    {
        return 'interes';
    }

    public static function seccion(): string
    {
        return 'lavanderias';
    }

    protected function reglasDeLosCampos(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'lavanderia' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    protected function mensajesDeLosCampos(): array
    {
        return [
            'nombre.required' => 'Escribe tu nombre.',
            'nombre.string' => 'Tu nombre va en texto.',
            'nombre.max' => 'Tu nombre es demasiado largo.',
            'lavanderia.required' => 'Escribe el nombre de la lavandería.',
            'lavanderia.string' => 'El nombre de la lavandería va en texto.',
            'lavanderia.max' => 'El nombre de la lavandería es demasiado largo.',
            'correo.required' => 'Escribe tu correo electrónico.',
            'correo.string' => 'Tu correo electrónico va en texto.',
            'correo.email' => 'Revisa tu correo electrónico: no está bien escrito.',
            'correo.max' => 'Tu correo electrónico es demasiado largo.',
        ];
    }
}
