<?php

/*
 * F-D01, gestión de lavanderías (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1), en la API de la
 * administración: administracion-app-E12 a E18. El alta, E17, espera DD-053, y los pedidos en curso, E18, DD-104: no
 * tienen ruta.
 *
 * Las rutas son de las tareas 3.1, 3.3 y 3.4 de openspec/changes/administracion-en-la-app/tasks.md, con el contrato de
 * design.md, sección 4, F-D01; el detalle de cada una está en su controlador. Lo carga el grupo de /api/administracion
 * de routes/api.php, que ya les da auth:sanctum, autorizar:administracion, el prefijo administracion/ y el nombre
 * administracion.: aquí no se repiten.
 */

use App\Http\Controllers\Api\Administracion\LavanderiasController;
use Illuminate\Support\Facades\Route;

// Todas, de todas las plazas, con búsqueda por nombre (administracion-app-E13).
Route::get('lavanderias', [LavanderiasController::class, 'lista'])->name('lavanderias.lista');

// Cada una por su ULID. Una que no existe, y un valor que no es ULID, responden 404 (administracion-app-E12).
Route::get('lavanderias/{lavanderia}', [LavanderiasController::class, 'mostrar'])
    ->whereUlid('lavanderia')
    ->name('lavanderias.mostrar');

// La edición (administracion-app-E14). Con foto, la app la manda en multipart por POST con _method=PATCH.
Route::patch('lavanderias/{lavanderia}', [LavanderiasController::class, 'editar'])
    ->whereUlid('lavanderia')
    ->name('lavanderias.editar');

// Suspender y levantar la suspensión (administracion-app-E15), y dar de baja (administracion-app-E16).
Route::post('lavanderias/{lavanderia}/suspension', [LavanderiasController::class, 'suspender'])
    ->whereUlid('lavanderia')
    ->name('lavanderias.suspension.poner');
Route::delete('lavanderias/{lavanderia}/suspension', [LavanderiasController::class, 'levantarSuspension'])
    ->whereUlid('lavanderia')
    ->name('lavanderias.suspension.levantar');
Route::post('lavanderias/{lavanderia}/baja', [LavanderiasController::class, 'darDeBaja'])
    ->whereUlid('lavanderia')
    ->name('lavanderias.baja');
