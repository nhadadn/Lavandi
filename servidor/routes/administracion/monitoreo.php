<?php

use App\Http\Controllers\Api\Administracion\MonitoreoController;
use Illuminate\Support\Facades\Route;

/*
 * F-D04, monitoreo de pedidos (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #4), en la API de la
 * administración: administracion-app-E28 a E31. El pedido cancelado, E31, espera DD-016.
 *
 * Su ruta la pone la tarea 9.1 de openspec/changes/administracion-en-la-app/tasks.md, con el contrato de design.md,
 * sección 4, F-D04. Lo carga el grupo de /api/administracion de routes/api.php, que ya le da auth:sanctum,
 * autorizar:administracion, el prefijo administracion/ y el nombre administracion.: aquí no se repiten.
 */

// Los pedidos activos de todas las plazas y sus marcadores, en una sola respuesta, con los marcadores solo de los
// pedidos de la lista. Un cambio de estado justo durante la consulta se corrige en la siguiente: la app la consulta
// cada 30 s (ADR-0007; ADR-0022, decisión 12). El contrato está en el controlador.
Route::get('monitoreo', MonitoreoController::class)->name('monitoreo');
