<?php

use App\Http\Controllers\Api\Administracion\IndicadoresController;
use Illuminate\Support\Facades\Route;

/*
 * F-I01 a F-I11, los indicadores del negocio (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5), en la API de la
 * administración: administracion-app-E45 a E55. Las cifras de F-I06 y F-I10, E54, esperan DD-083, DD-020 y DD-013, y
 * lo que espera respuesta en los indicadores que ya tienen cifra, E55, sus DD.
 *
 * Su ruta la ponen las tareas 17.1, 17.2 y 17.3 de openspec/changes/administracion-en-la-app/tasks.md, con el contrato
 * de design.md, sección 4, "Indicadores del A.5". Lo carga el grupo de /api/administracion de routes/api.php, que ya le
 * da auth:sanctum, autorizar:administracion, el prefijo administracion/ y el nombre administracion.: aquí no se repiten.
 */

// Los indicadores de una categoría del A.5 (operaciones, usuarios, socios, financiero o geografico), con el rango y la
// granularidad de la página de indicadores del panel, y en geografico la agrupación del mapa de calor
// (administracion-app-E45 a E53). Otra categoría responde 404 desde el controlador, después de la autorización, para que
// una cuenta sin acceso reciba siempre 403. El contrato está en el controlador.
Route::get('indicadores/{categoria}', IndicadoresController::class)->name('indicadores');
