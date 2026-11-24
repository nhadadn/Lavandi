<?php

namespace App\Filament\Auth;

use App\Models\Usuario;
use Filament\Auth\Pages\Login;
use SensitiveParameter;

/**
 * El inicio de sesión del panel. Es el de Filament, salvo que busca la cuenta por correo, porque la tabla usuarios no
 * tiene la columna email (ADR-0016). Quién entra lo decide el componente de autorización a través de
 * Usuario::canAccessPanel() (registro-E15).
 *
 * Vive fuera de app/Filament/Pages para que el panel no la descubra como una página más.
 */
class IniciarSesion extends Login
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'correo' => Usuario::normalizarCorreo((string) $data['email']),
            'password' => $data['password'],
        ];
    }
}
