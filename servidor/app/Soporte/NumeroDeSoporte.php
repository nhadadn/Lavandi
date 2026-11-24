<?php

namespace App\Soporte;

/**
 * El número de WhatsApp Business de soporte que lee la app (perfil-usuario-E09 y perfil-usuario-E10), tomado de
 * config/soporte.php.
 *
 * Sale solo con sus dígitos, como lo pide el enlace de WhatsApp, aunque la configuración lo escriba con +, espacios o
 * guiones. Sin número, o con un valor sin ningún dígito, sale vacío: la app no abre un enlace vacío.
 */
final class NumeroDeSoporte
{
    public function whatsapp(): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) config('soporte.whatsapp'));

        return $digitos === '' ? null : $digitos;
    }
}
