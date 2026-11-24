<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContrasenaNuevaRequest;
use App\Identidad\RecuperacionDeContrasena;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * La página web del servidor a la que lleva el enlace del correo, con el formulario de la contraseña nueva
 * (ADR-0018, decisión 4). La petición del enlace vive en la API: App\Http\Controllers\Api\RecuperacionController.
 *
 * Escenarios: registro-E24 y registro-E25 · Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md
 */
class RecuperacionController extends Controller
{
    /**
     * GET /contrasena/restablecer/{token}?correo=…: el formulario de la contraseña nueva (registro-E24). Un correo
     * que no es texto se deja vacío; al enviarlo, la validación dice que el enlace no está completo.
     */
    public function mostrarFormulario(Request $request, string $token): View
    {
        $correo = $request->query('correo');

        return view('recuperacion.formulario', [
            'token' => $token,
            'correo' => is_string($correo) ? $correo : '',
        ]);
    }

    /**
     * POST /contrasena/restablecer: guarda la contraseña nueva y cierra las sesiones (registro-E24). Con un enlace
     * vencido, usado o que no es de esa cuenta, muestra el error y no cambia nada (registro-E25).
     */
    public function guardarContrasena(ContrasenaNuevaRequest $request, RecuperacionDeContrasena $recuperacion): Response
    {
        $datos = $request->validated();

        if (! $recuperacion->restablecer($datos['correo'], $datos['token'], $datos['password'])) {
            return response()->view('recuperacion.enlace-no-valido', status: 422);
        }

        return response()->view('recuperacion.lista');
    }
}
