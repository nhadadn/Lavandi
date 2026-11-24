<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Identidad\BorradoDeCuenta;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Borrar la cuenta desde el perfil (ADR-0005; openspec/changes/perfil-y-soporte/, tarea 1.5).
 *
 * Contrato para la app. La ruta lleva sesión y pasa por el componente de autorización:
 *
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     DELETE /api/cuenta                              Borra la cuenta de la sesión (perfil-usuario-E15)
 *
 *     Sin cuerpo. La confirmación previa la pide la app antes de llamar (tarea 2.6). Siempre es la cuenta de la
 *     sesión: la ruta no recibe de quién.
 *
 *     204  (sin cuerpo)
 *          La cuenta queda borrada y todos sus tokens revocados, también los de sus otros teléfonos: cualquier petición
 *          con ellos responde 401. Ya no inicia sesión con correo y contraseña (POST /api/sesion responde 422, "Las
 *          credenciales no son válidas.") ni con Google (POST /api/sesion/google responde 422, "No se pudo entrar con
 *          tu cuenta de Google."). La app vuelve a la bienvenida.
 *
 * Qué pasa con los datos de la cuenta y con un pedido en curso es DD-109 (perfil-usuario-E16).
 */
class BorradoDeCuentaController extends Controller
{
    public function __invoke(Request $peticion, BorradoDeCuenta $borrado): Response
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $borrado->borrar($usuario);

        return response()->noContent();
    }
}
