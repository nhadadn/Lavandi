<?php

namespace App\Avisos;

use App\Autorizacion\Autorizacion;
use App\Models\Dispositivo;
use App\Models\Usuario;

/**
 * Los teléfonos que reciben los avisos de cada cuenta, en la tabla dispositivos (ADR-0008; ADR-0016;
 * openspec/changes/avisos/design.md, "Tablas y migración").
 *
 * - registrar() liga el token a la cuenta de la sesión, con su plataforma y su último uso (avisos-E04). Si el token ya
 *   estaba, de esta cuenta o de otra, lo pasa a esta en la misma sentencia (INSERT ... ON CONFLICT), sin carrera entre
 *   dos registros a la vez: un teléfono es de una sola cuenta (avisos-E21).
 * - olvidar() borra el token al cerrar la sesión en ese teléfono, solo si es de la cuenta (avisos-E05).
 * - olvidarTodos() borra todos los teléfonos de la cuenta, al borrarla.
 *
 * El token que FCM rechaza lo borra el envío (App\Avisos\MandarAvisoAlTelefono; avisos-E06).
 */
final class TelefonosRegistrados
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function registrar(Usuario $usuario, string $token, string $plataforma): void
    {
        Dispositivo::query()->upsert(
            [[
                'id_usuarios' => $usuario->getKey(),
                'token_fcm' => $token,
                'plataforma' => $plataforma,
                'ultimo_uso_en' => now(),
            ]],
            uniqueBy: ['token_fcm'],
            update: ['id_usuarios', 'plataforma', 'ultimo_uso_en'],
        );
    }

    public function olvidar(Usuario $usuario, string $token): void
    {
        $this->autorizacion->telefonosDeLaCuenta($usuario)->where('token_fcm', $token)->delete();
    }

    public function olvidarTodos(Usuario $usuario): void
    {
        $this->autorizacion->telefonosDeLaCuenta($usuario)->delete();
    }
}
