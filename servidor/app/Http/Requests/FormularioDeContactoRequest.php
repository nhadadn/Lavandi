<?php

namespace App\Http\Requests;

/**
 * El formulario de contacto, de "Contacto & Soporte" (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 11): quién
 * escribe, su correo y su mensaje, los tres obligatorios (landing-E21).
 */
class FormularioDeContactoRequest extends FormularioDeLaPortadaRequest
{
    public static function formulario(): string
    {
        return 'contacto';
    }

    public static function seccion(): string
    {
        return 'contacto';
    }

    protected function reglasDeLosCampos(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'string', 'email', 'max:255'],
            'mensaje' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function mensajesDeLosCampos(): array
    {
        return [
            'nombre.required' => 'Escribe tu nombre.',
            'nombre.string' => 'Tu nombre va en texto.',
            'nombre.max' => 'Tu nombre es demasiado largo.',
            'correo.required' => 'Escribe tu correo electrónico.',
            'correo.string' => 'Tu correo electrónico va en texto.',
            'correo.email' => 'Revisa tu correo electrónico: no está bien escrito.',
            'correo.max' => 'Tu correo electrónico es demasiado largo.',
            'mensaje.required' => 'Escribe tu mensaje.',
            'mensaje.string' => 'Tu mensaje va en texto.',
            'mensaje.max' => 'Tu mensaje es demasiado largo.',
        ];
    }
}
