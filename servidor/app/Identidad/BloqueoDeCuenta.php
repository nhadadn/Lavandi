<?php

namespace App\Identidad;

use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bloquea una cuenta desde el panel y levanta su bloqueo (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3;
 * openspec/changes/administracion/design.md, lectura 3; administracion-E03 y E04).
 *
 * Bloquear escribe usuarios.estado = 'bloqueado' con la hora en bloqueado_en y revoca todos los tokens de Sanctum de la
 * cuenta, en todos sus teléfonos, como el borrado de cuenta (App\Identidad\BorradoDeCuenta): la siguiente petición de la
 * app con la sesión que tenía abierta se rechaza. Mientras siga bloqueada, ninguna entrada le abre sesión: lo decide
 * App\Autorizacion\Autorizacion::puedeIniciarSesion().
 *
 * Levantar el bloqueo la deja activa y sin bloqueado_en; la persona vuelve a entrar con su contraseña o con Google.
 *
 * No toca sus teléfonos registrados, sus direcciones ni sus pedidos: lo que pasa con un pedido activo es DD-016
 * (administracion-E05). Quién puede bloquear a quién lo decide el componente de autorización
 * (Autorizacion::puedeBloquear()), antes de llamar aquí.
 */
final class BloqueoDeCuenta
{
    /**
     * Bloquea la cuenta. Una ya bloqueada se queda como está, con su hora de bloqueo.
     */
    public function bloquear(Usuario $usuario): void
    {
        DB::transaction(function () use ($usuario): void {
            if ($usuario->estado !== Usuario::BLOQUEADO) {
                $usuario->forceFill([
                    'estado' => Usuario::BLOQUEADO,
                    // En UTC, como toda fecha que se guarda.
                    'bloqueado_en' => CarbonImmutable::now()->utc(),
                ])->save();
            }

            $usuario->tokens()->delete();
        });
    }

    /**
     * Levanta el bloqueo: la cuenta vuelve a activa.
     */
    public function levantar(Usuario $usuario): void
    {
        $usuario->forceFill(['estado' => Usuario::ACTIVO, 'bloqueado_en' => null])->save();
    }
}
