<?php

use App\Http\Controllers\Api\Administracion\UsuariosController;
use Illuminate\Support\Facades\Route;

/*
 * F-D03, gestión de usuarios (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3), en la API de la
 * administración: administracion-app-E24 a E27. El filtro de usuarios activos, E26, espera DD-111, y el bloqueo con un
 * pedido activo, E27, DD-016.
 *
 * Sus rutas las ponen las tareas 7.2 y 7.3 de openspec/changes/administracion-en-la-app/tasks.md, con el contrato de
 * design.md, sección 4, F-D03. Lo carga el grupo de /api/administracion de routes/api.php, que ya les da auth:sanctum,
 * autorizar:administracion, el prefijo administracion/ y el nombre administracion.: aquí no se repiten.
 */

// El contrato de cada ruta está en el controlador. Sin whereUlid a propósito: un valor que no es ULID responde el mismo
// 404 que una cuenta que no es de usuario o que no existe (administracion-app-E12). Sin rutas de alta, edición ni
// borrado: el Módulo D #3 dice consulta, historial y bloqueo.
Route::get('usuarios', [UsuariosController::class, 'lista'])->name('usuarios.lista');
Route::get('usuarios/{usuario}', [UsuariosController::class, 'mostrar'])->name('usuarios.mostrar');
// Bloquear (POST) y levantar el bloqueo (DELETE), con la regla del panel (administracion-E03 y E04).
Route::post('usuarios/{usuario}/bloqueo', [UsuariosController::class, 'bloquear'])->name('usuarios.bloquear');
Route::delete('usuarios/{usuario}/bloqueo', [UsuariosController::class, 'levantarBloqueo'])
    ->name('usuarios.levantar-bloqueo');
