<?php

use App\Http\Controllers\Api\Administracion\ComisionesController;
use Illuminate\Support\Facades\Route;

/*
 * F-D05, configuración de comisiones (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #5), en la API de la
 * administración: administracion-app-E32 a E36. La comisión del repartidor, E35, espera DD-019, y la de cada pedido,
 * E36, DD-020 y DD-013: no tienen ruta.
 *
 * Sus rutas son las de la tarea 11.1 de openspec/changes/administracion-en-la-app/tasks.md, con el contrato de design.md,
 * sección 4, F-D05; el detalle está en el controlador. Lo carga el grupo de /api/administracion de routes/api.php, que ya
 * les da auth:sanctum, autorizar:administracion, el prefijo administracion/ y el nombre administracion.: aquí no se
 * repiten.
 */

// El porcentaje global y el de cada lavandería, de todas las plazas (administracion-app-E32).
Route::get('comisiones', [ComisionesController::class, 'mostrar'])->name('comisiones.mostrar');

// Fija el porcentaje global (administracion-app-E33 y E34).
Route::put('comisiones/global', [ComisionesController::class, 'fijarGlobal'])->name('comisiones.global');

// Fija o quita el porcentaje propio de una lavandería, por su ULID (administracion-app-E33 y E34). Sin whereUlid a
// propósito: un valor que no es ULID responde el mismo 404 que una lavandería que no existe.
Route::put('comisiones/lavanderias/{lavanderia}', [ComisionesController::class, 'fijarPropio'])
    ->name('comisiones.lavanderias.fijar');
Route::delete('comisiones/lavanderias/{lavanderia}', [ComisionesController::class, 'quitarPropio'])
    ->name('comisiones.lavanderias.quitar');
