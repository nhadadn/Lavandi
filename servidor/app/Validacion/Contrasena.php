<?php

namespace App\Validacion;

use Illuminate\Validation\Rules\Password;

/**
 * La regla de la contraseña de una cuenta: obligatoria, texto y con el mínimo de caracteres de config/acceso.php
 * (ADR-0018, decisión 2). La usan el registro (registro-E03) y la contraseña nueva de la recuperación (registro-E24).
 *
 * El mínimo lo aplica Password::defaults(), que App\Providers\AppServiceProvider arma con la configuración; así
 * también lo usa cualquier pantalla de Filament que pida una contraseña.
 *
 *     $request->validate(
 *         ['password' => Contrasena::reglas()],
 *         Contrasena::mensajes(),
 *     );
 */
final class Contrasena
{
    /**
     * @return list<mixed>
     */
    public static function reglas(): array
    {
        return ['required', 'string', Password::defaults()];
    }

    /**
     * Los mensajes en español del campo, para pasarlos como mensajes propios del validador.
     *
     * @return array<string, string>
     */
    public static function mensajes(string $campo = 'password'): array
    {
        return [
            "{$campo}.required" => 'Escribe una contraseña.',
            "{$campo}.string" => 'La contraseña no es válida.',
            "{$campo}.min" => 'La contraseña debe tener al menos :min caracteres.',
        ];
    }
}
