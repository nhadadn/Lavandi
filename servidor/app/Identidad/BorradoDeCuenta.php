<?php

namespace App\Identidad;

use App\Avisos\TelefonosRegistrados;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Borra la cuenta que lo pide desde la app (ADR-0005; perfil-usuario-E15; openspec/changes/perfil-y-soporte/design.md,
 * decisión 2).
 *
 * Revoca todos sus tokens de Sanctum, en todos sus teléfonos, borra todos sus teléfonos registrados para que ningún aviso
 * les llegue (avisos-E05) y la borra en suave (usuarios.deleted_at). Una cuenta borrada ya no entra:
 *
 * - con correo y contraseña, porque el inicio de sesión no encuentra cuentas borradas (registro-E08);
 * - con Google, porque App\Identidad\EntradaConGoogle rechaza la cuenta borrada y no crea otra con su correo;
 * - ni recibe el enlace de recuperación, porque el broker de contraseñas no encuentra cuentas borradas.
 *
 * La entrada con Apple todavía no existe (DD-069): cuando llegue, rechaza la cuenta borrada igual.
 *
 * Los datos de la cuenta quedan como están: qué se borra, qué se conserva y qué pasa con un pedido en curso es DD-109
 * (perfil-usuario-E16). El borrado suave lo permite sin decidirlo, y no rompe las llaves de pedidos, direcciones y
 * reportes, que la base no deja borrar (ON DELETE RESTRICT).
 */
final class BorradoDeCuenta
{
    public function __construct(private readonly TelefonosRegistrados $telefonos) {}

    public function borrar(Usuario $usuario): void
    {
        DB::transaction(function () use ($usuario): void {
            $usuario->tokens()->delete();
            $this->telefonos->olvidarTodos($usuario);
            $usuario->delete();
        });
    }
}
