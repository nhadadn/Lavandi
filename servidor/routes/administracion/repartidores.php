<?php

use App\Http\Controllers\Api\Administracion\PlazasController;
use App\Http\Controllers\Api\Administracion\RepartidoresController;
use Illuminate\Support\Facades\Route;

/*
 * F-D02, gestión de repartidores (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2), en la API de la
 * administración: administracion-app-E19 a E23, con las plazas para el alta.
 *
 * Sus rutas las ponen las tareas 5.1, 5.2 y 5.4 de openspec/changes/administracion-en-la-app/tasks.md, con el contrato de
 * design.md, sección 4, F-D02. Lo carga el grupo de /api/administracion de routes/api.php, que ya les da auth:sanctum,
 * autorizar:administracion, el prefijo administracion/ y el nombre administracion.: aquí no se repiten.
 *
 * El contrato de cada ruta está en su controlador. Sin whereUlid a propósito: un valor que no es ULID responde el mismo
 * 404 que un repartidor que no existe.
 */

Route::get('repartidores', [RepartidoresController::class, 'lista'])->name('repartidores.lista');
Route::get('repartidores/{repartidor}', [RepartidoresController::class, 'mostrar'])->name('repartidores.mostrar');
// Con foto, la app la manda en multipart por POST con _method=PATCH.
Route::patch('repartidores/{repartidor}', [RepartidoresController::class, 'editar'])->name('repartidores.editar');
Route::post('repartidores/{repartidor}/alta', [RepartidoresController::class, 'darDeAlta'])->name('repartidores.alta');
Route::post('repartidores/{repartidor}/suspension', [RepartidoresController::class, 'suspender'])
    ->name('repartidores.suspension.poner');
Route::delete('repartidores/{repartidor}/suspension', [RepartidoresController::class, 'levantarSuspension'])
    ->name('repartidores.suspension.levantar');
Route::post('repartidores/{repartidor}/baja', [RepartidoresController::class, 'darDeBaja'])->name('repartidores.baja');

// Las plazas en que se da de alta a un repartidor.
Route::get('plazas', PlazasController::class)->name('plazas');
