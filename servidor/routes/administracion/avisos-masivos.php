<?php

/*
 * F-D07, avisos masivos (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7), en la API de la administración:
 * administracion-app-E42 a E44. El aviso a un segmento, E44, espera DD-113.
 *
 * Sus rutas son las de la tarea 15.2 de openspec/changes/administracion-en-la-app/tasks.md, con el contrato de design.md,
 * sección 4, F-D07; el detalle está en el controlador. Lo carga el grupo de /api/administracion de routes/api.php, que
 * ya les da auth:sanctum, autorizar:administracion, el prefijo administracion/ y el nombre administracion.: aquí no se
 * repiten.
 */

use App\Http\Controllers\Api\Administracion\AvisosMasivosController;
use Illuminate\Support\Facades\Route;

// Los avisos enviados, del más reciente al más antiguo (administracion-app-E43).
Route::get('avisos-masivos', [AvisosMasivosController::class, 'lista'])->name('avisos-masivos.lista');
// Manda un aviso global, que sale en cola a todos los teléfonos registrados (administracion-app-E42).
Route::post('avisos-masivos', [AvisosMasivosController::class, 'crear'])->name('avisos-masivos.crear');
