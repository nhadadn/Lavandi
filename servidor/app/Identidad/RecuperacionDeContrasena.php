<?php

namespace App\Identidad;

use App\Models\Usuario;
use App\Notifications\EnlaceDeRecuperacion;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * La recuperación de la contraseña por correo, sobre el broker de contraseñas de Laravel (ADR-0005 y ADR-0018).
 *
 * El broker guarda el token con hash en password_reset_tokens, cuya columna email guarda el correo de la cuenta
 * (design.md, "Tablas y migración"), y lo da por vencido con el expire de config/auth.php. El token sirve una sola
 * vez: el broker lo borra al guardar la contraseña nueva.
 */
final class RecuperacionDeContrasena
{
    public function __construct(private readonly PasswordBrokerFactory $brokers) {}

    /**
     * Manda el enlace al correo si tiene cuenta (registro-E22). Si no la tiene, no manda nada (registro-E23).
     *
     * No devuelve nada a propósito: quien llama responde lo mismo exista o no la cuenta, y lo mismo si el broker frenó
     * el envío (ADR-0018, decisión 5).
     *
     * Dos peticiones casi a la vez para la misma cuenta pueden guardar su token al mismo tiempo; la segunda choca con
     * la llave de password_reset_tokens. Ese choque se ignora: la primera ya manda el enlace, y un error delataría que
     * la cuenta existe (registro-E23).
     */
    public function mandarEnlace(string $correo): void
    {
        try {
            $this->broker()->sendResetLink(
                $this->credenciales($correo),
                function (Usuario $usuario, #[\SensitiveParameter] string $token): void {
                    // Al correo de la cuenta, sin depender de que el modelo sea Notifiable.
                    Notification::route('mail', $usuario->correo)
                        ->notify(new EnlaceDeRecuperacion($token, $usuario->correo));
                },
            );
        } catch (UniqueConstraintViolationException) {
            // La otra petición guardó su token y manda su enlace.
        }
    }

    /**
     * Guarda la contraseña nueva si el enlace es de esa cuenta, está vigente y no se ha usado (registro-E24 y
     * registro-E25). Al guardarla, revoca todos los tokens de Sanctum de la cuenta: se cierran sus sesiones
     * (ADR-0018, decisión 6).
     *
     * @return bool Si se guardó. Una cuenta que no existe y un enlace vencido, usado o ajeno dan lo mismo: false.
     */
    public function restablecer(
        string $correo,
        #[\SensitiveParameter] string $token,
        #[\SensitiveParameter] string $password,
    ): bool {
        $estado = $this->broker()->reset(
            [...$this->credenciales($correo), 'token' => $token, 'password' => $password],
            function (Usuario $usuario, #[\SensitiveParameter] string $password): void {
                // Se cifra aquí y no en el cast hashed del modelo, que guarda tal cual un texto con forma de hash
                // (registro-E05).
                $hash = Hash::make($password);

                DB::transaction(function () use ($usuario, $hash): void {
                    $usuario->forceFill(['password' => $hash])->save();
                    $usuario->tokens()->delete();
                });
            },
        );

        return $estado === PasswordBroker::PASSWORD_RESET;
    }

    private function broker(): PasswordBroker
    {
        return $this->brokers->broker();
    }

    /**
     * La cuenta se busca por su correo sin distinguir mayúsculas, como en el inicio de sesión.
     *
     * @return array<string, \Closure(Builder<Usuario>): void>
     */
    private function credenciales(string $correo): array
    {
        return ['correo' => function (Builder $consulta) use ($correo): void {
            $consulta->conCorreo($correo);
        }];
    }
}
