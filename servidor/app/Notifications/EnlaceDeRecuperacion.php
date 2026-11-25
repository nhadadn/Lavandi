<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El correo con el enlace para crear una contraseña nueva (registro-E22; ADR-0018, decisión 4).
 *
 * El enlace abre la página web del servidor con el formulario y vence en el plazo del broker. El nombre del producto
 * sale de la configuración (APP_NAME), nunca del código. Las vistas son propias, en español, sin la plantilla de
 * Laravel, que trae sus textos en inglés.
 */
class EnlaceDeRecuperacion extends Notification
{
    public function __construct(
        #[\SensitiveParameter] public readonly string $token,
        public readonly string $correo,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $producto = (string) config('app.name');

        return (new MailMessage)
            ->subject("Crea tu contraseña nueva de {$producto}")
            ->view(['html' => 'correo.recuperacion', 'text' => 'correo.recuperacion-texto'], [
                'producto' => $producto,
                'enlace' => route('contrasena.formulario', ['token' => $this->token, 'correo' => $this->correo]),
                'minutos' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
    }
}
