<?php

namespace App\Landing;

use App\Soporte\NumeroDeSoporte;

/**
 * El correo y el WhatsApp Business de "Contacto & Soporte" (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 11),
 * los dos de la configuración: el correo de config/landing.php y el número de config/soporte.php, que es el mismo del
 * soporte de la app (App\Soporte\NumeroDeSoporte).
 *
 * Un correo que no es una dirección válida o un número sin dígitos no dan enlace: la portada nunca publica un enlace
 * vacío (landing-E23 y landing-E09).
 */
final class MediosDeContacto
{
    public function __construct(private readonly NumeroDeSoporte $numero) {}

    /**
     * El enlace que abre un mensaje nuevo al correo de contacto, con el correo; o ninguno.
     *
     * @return array{correo: string, enlace: string}|null
     */
    public function correo(): ?array
    {
        $correo = config('landing.contacto.correo');
        $correo = is_string($correo) ? trim($correo) : '';

        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return ['correo' => $correo, 'enlace' => 'mailto:'.$correo];
    }

    /**
     * El enlace que abre una conversación de WhatsApp con el número de soporte; o ninguno.
     */
    public function whatsapp(): ?string
    {
        $digitos = $this->numero->whatsapp();

        return $digitos === null ? null : 'https://wa.me/'.$digitos;
    }
}
