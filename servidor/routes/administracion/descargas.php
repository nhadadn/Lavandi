<?php

use App\Http\Controllers\Api\Administracion\DescargasController;
use Illuminate\Support\Facades\Route;

/*
 * F-T14, la descarga de los reportes en CSV y en Excel (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5), en la API de la
 * administración: administracion-app-E56 a E63. La de F-I06, F-I10 y las comisiones del periodo, E63, espera DD-083,
 * DD-020 y DD-013.
 *
 * Sus rutas las ponen las tareas 19.2 y 19.3 de openspec/changes/administracion-en-la-app/tasks.md, con el contrato de
 * design.md, sección 4, "Descargas en CSV y Excel". Lo carga el grupo de /api/administracion de routes/api.php, que ya
 * les da auth:sanctum, autorizar:administracion, el prefijo administracion/ y el nombre administracion.: aquí no se
 * repiten.
 */

// El contrato de cada ruta está en el controlador. {descarga:id_publico}: la descarga se nombra por su id_publico, un
// ULID, nunca por el id de exports (ADR-0022, decisión 15; design.md, sección 4). La busca el controlador por ese campo,
// y no el enlace implícito de Laravel, que corre antes que autorizar:administracion: el controlador recibe el valor como
// texto, así que Laravel no la resuelve. Por eso una cuenta de otro rol recibe 403 con cualquier valor, y un valor que no
// es ULID, una descarga que no existe y la de otra cuenta responden el mismo 404 (administracion-app-E62). El campo no
// entra en la dirección: la tabla de rutas las guarda como descargas/{descarga}. Sin where en {formato} a propósito: uno
// que no es csv ni xlsx responde el 404 del controlador, sin datos.
Route::post('descargas', [DescargasController::class, 'pedir'])->name('descargas.pedir');
Route::get('descargas', [DescargasController::class, 'lista'])->name('descargas.lista');
Route::get('descargas/{descarga:id_publico}', [DescargasController::class, 'mostrar'])->name('descargas.mostrar');
Route::get('descargas/{descarga:id_publico}/{formato}', [DescargasController::class, 'archivo'])->name('descargas.archivo');
