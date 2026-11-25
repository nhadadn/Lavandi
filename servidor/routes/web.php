<?php

use App\Http\Controllers\BorradoDeCuentaController;
use App\Http\Controllers\BuscadoresController;
use App\Http\Controllers\Desarrollo\CheckoutDeDesarrolloController;
use App\Http\Controllers\FormularioDeLaPortadaController;
use App\Http\Controllers\ImagenDePrensaController;
use App\Http\Controllers\PortadaController;
use App\Http\Controllers\RecuperacionController;
use App\Http\Controllers\TextosLegalesController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
 * La raíz es la portada de la landing (ADR-0011; openspec/changes/landing/). La salud es /up (base-E01).
 */

/*
 * La página web de la recuperación de la contraseña, a la que lleva el enlace del correo (ADR-0018, decisión 4).
 */
Route::get('/contrasena/restablecer/{token}', [RecuperacionController::class, 'mostrarFormulario'])
    ->name('contrasena.formulario');
Route::post('/contrasena/restablecer', [RecuperacionController::class, 'guardarContrasena'])
    ->name('contrasena.restablecer');

/*
 * La portada pública de la landing, con sus 12 secciones (openspec/changes/landing/, tarea 1.1).
 */
Route::get('/', PortadaController::class)->name('portada');

/*
 * La imagen de un logo de prensa activo de la portada, por el nombre de su archivo, leída del disco privado de fotos
 * (openspec/changes/landing/, tarea 4.2; adenda del 2026-11-24 de ADR-0006). Va con caché pública, así que no abre
 * sesión ni pone cookies: una caché compartida no debe guardar la cookie de nadie.
 */
Route::get('/prensa/{archivo}', ImagenDePrensaController::class)
    ->where('archivo', '[^/]+')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->name('portada.prensa.imagen');

/*
 * Los formularios de la portada: interés de lavanderías y contacto (openspec/changes/landing/, tareas 2.4 y 5.1).
 */
Route::post('/formularios/interes', [FormularioDeLaPortadaController::class, 'interes'])->name('portada.interes');
Route::post('/formularios/contacto', [FormularioDeLaPortadaController::class, 'contacto'])->name('portada.contacto');

/*
 * El aviso de privacidad y los términos y condiciones de EL CLIENTE, cada uno en su dirección fija y pública
 * (openspec/changes/textos-legales/, tarea 1.1). Las direcciones no cambian con una versión nueva (textos-legales-E04):
 * las declaran las tiendas, el pie de la landing y la app.
 */
Route::get('/aviso-de-privacidad', TextosLegalesController::class)
    ->defaults('documento', 'aviso-de-privacidad')
    ->name('textos-legales.aviso-de-privacidad');
Route::get('/terminos', TextosLegalesController::class)
    ->defaults('documento', 'terminos')
    ->name('textos-legales.terminos');

/*
 * Cómo pedir el borrado de la cuenta: desde la app o por el contacto de la configuración (openspec/changes/textos-legales/,
 * tarea 1.4; adenda del 2026-11-24 de ADR-0005). Es la dirección web que pide Google Play: solo informa, no tiene
 * formulario ni borra nada.
 */
Route::get('/borrado-de-cuenta', BorradoDeCuentaController::class)->name('textos-legales.borrado-de-cuenta');

/*
 * El mapa del sitio y el archivo de robots, para los buscadores (openspec/changes/medicion-de-la-landing/, tareas 2.1 y
 * 2.3). Con las direcciones absolutas en el dominio de la configuración. En producción, el archivo de robots declara el
 * mapa del sitio; fuera de ella, cierra el sitio (medicion-E15).
 */
Route::get('/sitemap.xml', [BuscadoresController::class, 'mapaDelSitio'])->name('medicion.mapa-del-sitio');
Route::get('/robots.txt', [BuscadoresController::class, 'robots'])->name('medicion.robots');

/*
 * Herramienta de desarrollo: la página de checkout de la pasarela simulada, para probar el cobro desde la app sin ninguna
 * pasarela real (openspec/changes/pago/design.md, "Decisiones de la implementación"). No es una pantalla del producto:
 * solo se registra en local y en las pruebas; en el entorno de prueba en internet y en producción no existe.
 */
if (app()->environment(['local', 'testing'])) {
    Route::get('/desarrollo/pasarela/checkout/{orden}', [CheckoutDeDesarrolloController::class, 'mostrar'])
        ->name('desarrollo.pasarela.checkout');
    Route::post('/desarrollo/pasarela/checkout/{orden}', [CheckoutDeDesarrolloController::class, 'resultado'])
        ->name('desarrollo.pasarela.resultado');
}
