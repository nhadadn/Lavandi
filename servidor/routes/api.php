<?php

use App\Http\Controllers\Api\AvisosDeLaPasarelaController;
use App\Http\Controllers\Api\BorradoDeCuentaController;
use App\Http\Controllers\Api\CalificacionesController;
use App\Http\Controllers\Api\CatalogoDelTerritorioController;
use App\Http\Controllers\Api\CuentaController;
use App\Http\Controllers\Api\DireccionesController;
use App\Http\Controllers\Api\DispositivosController;
use App\Http\Controllers\Api\HistorialDeLaLavanderiaController;
use App\Http\Controllers\Api\HistorialDelRepartidorController;
use App\Http\Controllers\Api\LavanderiaDelSocioController;
use App\Http\Controllers\Api\LavanderiasCercanasController;
use App\Http\Controllers\Api\PagosController;
use App\Http\Controllers\Api\PedidosController;
use App\Http\Controllers\Api\PedidosDeLaLavanderiaController;
use App\Http\Controllers\Api\PedidosDelRepartidorController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\PerfilDeLavanderiaController;
use App\Http\Controllers\Api\RecuperacionController;
use App\Http\Controllers\Api\RegistroController;
use App\Http\Controllers\Api\RepartidorController;
use App\Http\Controllers\Api\ReportesDeSoporteController;
use App\Http\Controllers\Api\SesionConGoogleController;
use App\Http\Controllers\Api\SesionController;
use App\Http\Controllers\Api\SoporteController;
use App\Http\Controllers\Api\VentanasDeLavanderiaController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas de la API para la app, con tokens de Sanctum (ADR-0003 y ADR-0005), bajo el prefijo /api.
 * Cada ruta llega con su cambio de OpenSpec; la de salud es /up (base-E01).
 */

// Públicas: las que abren una sesión (registro-e-inicio-de-sesion).
Route::post('registro', RegistroController::class)->name('registro');
Route::post('sesion', [SesionController::class, 'iniciar'])
    ->middleware('throttle:inicio-de-sesion')
    ->name('sesion.iniciar');
Route::post('sesion/google', SesionConGoogleController::class)->name('sesion.google');
// Pública: el registro del repartidor, que crea su cuenta pendiente de alta (repartidor-E01). El contrato está en el
// controlador.
Route::post('repartidor/registro', [RepartidorController::class, 'registrar'])->name('repartidor.registro');

// Pública: la petición del enlace de recuperación, con su límite por correo y por dirección (registro-E31).
Route::post('contrasena/olvido', RecuperacionController::class)
    ->middleware('throttle:recuperacion')
    ->name('contrasena.olvido');

// Pública: el aviso (webhook) de la pasarela de pago, que no lleva sesión porque lo manda la pasarela (pago). El contrato
// está en el controlador; todo aviso pasa por App\Cobro\RecepcionDeAvisos.
Route::post('pasarela/avisos', AvisosDeLaPasarelaController::class)->name('pasarela.avisos');

// Con sesión, y por el componente único de autorización (registro-E14). Una ruta nueva sin las dos no pasa la prueba
// tests/Feature/Registro/AutorizacionTest.php.
// La cuenta y el cierre de la sesión, de toda cuenta con un rol de la app: con el rol, la app sabe a qué módulo entra
// (registro-E33). La administración entra por el panel.
Route::middleware(['auth:sanctum', 'autorizar:usuario,lavanderia,repartidor'])->group(function () {
    Route::get('cuenta', CuentaController::class)->name('cuenta');
    Route::delete('sesion', [SesionController::class, 'cerrar'])->name('sesion.cerrar');

    // El catálogo del territorio para la dirección asistida (direcciones, C-238): la del usuario y la de la lavandería
    // que se registra (alta-lavanderias-E01). El contrato está en el controlador.
    // Sin where en el código postal a propósito: uno que no son cinco números responde 422, no 404 (direcciones-E20).
    Route::get('catalogos/municipios', [CatalogoDelTerritorioController::class, 'municipios'])
        ->name('catalogos.municipios');
    Route::get('codigos-postales/{codigo_postal}/colonias', [CatalogoDelTerritorioController::class, 'colonias'])
        ->name('codigos-postales.colonias');
});

Route::middleware(['auth:sanctum', 'autorizar:usuario'])->group(function () {

    // Las lavanderías cercanas a un punto (lavanderias-cercanas).
    Route::get('lavanderias/cercanas', LavanderiasCercanasController::class)->name('lavanderias.cercanas');

    // El perfil de una lavandería, por su ULID (perfil-de-lavanderia). Un valor que no es ULID responde 404.
    Route::get('lavanderias/{lavanderia}', PerfilDeLavanderiaController::class)
        ->whereUlid('lavanderia')
        ->name('lavanderias.perfil');

    // Las ventanas de una hora de una lavandería para una fecha (agenda-de-recoleccion).
    Route::get('lavanderias/{lavanderia}/ventanas', VentanasDeLavanderiaController::class)
        ->whereUlid('lavanderia')
        ->name('lavanderias.ventanas');

    // Las direcciones de recogida y de entrega de la cuenta (direcciones). El contrato está en el controlador.
    Route::post('direcciones', [DireccionesController::class, 'guardar'])->name('direcciones.guardar');
    Route::get('direcciones/favoritas', [DireccionesController::class, 'favoritas'])->name('direcciones.favoritas');
    Route::post('direcciones/recogida-y-entrega', [DireccionesController::class, 'recogidaYEntrega'])
        ->name('direcciones.recogida-y-entrega');
    // Una dirección de la cuenta, por su ULID. La de otra cuenta responde igual que una que no existe (direcciones-E14).
    Route::get('direcciones/{direccion}', [DireccionesController::class, 'mostrar'])
        ->whereUlid('direccion')
        ->name('direcciones.mostrar');

    // Los pedidos de la cuenta (resumen-y-confirmacion). El contrato está en el controlador. Ninguna ruta confirma un
    // pedido: lo confirma el cobro cuando la pasarela confirma el pago (C-188; confirmacion-E07).
    Route::post('pedidos', [PedidosController::class, 'crear'])->name('pedidos.crear');
    // El historial de la cuenta, con el filtro por fecha (historial-y-calificacion): solo sus pedidos confirmados.
    Route::get('pedidos', [PedidosController::class, 'lista'])->name('pedidos.lista');
    // Un pedido de la cuenta, por su ULID. Sin whereUlid a propósito: un valor que no es ULID, como un folio, responde
    // el mismo 404 que un pedido ajeno o que no existe (confirmacion-E12).
    Route::get('pedidos/{pedido}', [PedidosController::class, 'mostrar'])->name('pedidos.mostrar');
    // La calificación del servicio de un pedido de la cuenta (historial-y-calificacion). El contrato está en el
    // controlador. Sin whereUlid por lo mismo: uno ajeno, uno que no existe y un folio responden el mismo 404.
    Route::post('pedidos/{pedido}/calificacion', CalificacionesController::class)->name('pedidos.calificacion');

    // El perfil de la cuenta de la sesión, y ninguna otra (perfil-y-soporte). El contrato está en el controlador.
    Route::get('perfil', [PerfilController::class, 'mostrar'])->name('perfil.mostrar');
    Route::patch('perfil', [PerfilController::class, 'cambiar'])->name('perfil.cambiar');
    // La foto de perfil va en multipart, así que es POST: PHP solo lee los archivos de un POST.
    Route::post('perfil/foto', [PerfilController::class, 'subirFoto'])->name('perfil.foto');

    // Soporte: el número de WhatsApp de la configuración y el formulario de reporte (perfil-y-soporte). El contrato está
    // en cada controlador.
    Route::get('soporte', SoporteController::class)->name('soporte');
    Route::post('soporte/reportes', ReportesDeSoporteController::class)->name('soporte.reportes');

    // Borrar la cuenta de la sesión desde el perfil (perfil-y-soporte; ADR-0005). El contrato está en el controlador.
    Route::delete('cuenta', BorradoDeCuentaController::class)->name('cuenta.borrar');

    // El token de Firebase del teléfono que recibe los avisos de la cuenta (avisos). El contrato está en el
    // controlador; se borra al cerrar la sesión, con DELETE sesion.
    Route::post('dispositivos', [DispositivosController::class, 'registrar'])->name('dispositivos.registrar');

    // El cobro del pedido (pago). El contrato está en el controlador y en openspec/changes/pago/design.md. Ninguna de
    // estas rutas da un cobro por pagado: solo el aviso auténtico de la pasarela (pago-E07).
    Route::post('pagos/checkout', [PagosController::class, 'checkout'])->name('pagos.checkout');
    // El cobro que consulta "Procesando pago". Sin whereUlid a propósito: un valor que no es ULID responde el mismo 404
    // que un cobro ajeno.
    Route::get('pagos/{pago}', [PagosController::class, 'mostrar'])->name('pagos.mostrar');
});

// El Módulo B del socio lavandería: su lavandería (alta-de-lavanderias). Con sesión y por el componente único de
// autorización con el rol de socio lavandería; la tenencia la decide Autorizacion::lavanderiasDeLaCuenta(). El contrato
// está en el controlador.
Route::middleware(['auth:sanctum', 'autorizar:lavanderia'])->prefix('socio')->group(function () {
    Route::post('lavanderias', [LavanderiaDelSocioController::class, 'registrar'])->name('socio.lavanderias.registrar');
    Route::get('lavanderias', [LavanderiaDelSocioController::class, 'lista'])->name('socio.lavanderias.lista');
    // Su lavandería, por su ULID. Una ajena responde igual que una que no existe (alta-lavanderias-E20).
    Route::get('lavanderias/{lavanderia}', [LavanderiaDelSocioController::class, 'mostrar'])
        ->whereUlid('lavanderia')
        ->name('socio.lavanderias.mostrar');
    // Con foto, la app la manda en multipart por POST con _method=PATCH.
    Route::patch('lavanderias/{lavanderia}', [LavanderiaDelSocioController::class, 'editar'])
        ->whereUlid('lavanderia')
        ->name('socio.lavanderias.editar');
});

// Con sesión de socio lavandería, el Módulo B (pedidos-de-la-lavanderia y estados-del-pedido). El contrato está en el
// controlador. Sin whereUlid a propósito: un valor que no es ULID responde el mismo 404 que un pedido ajeno.
Route::middleware(['auth:sanctum', 'autorizar:lavanderia'])->prefix('lavanderia')->group(function () {
    // La lista en tiempo real de sus pedidos activos; la app la consulta cada 30 s (ADR-0007).
    Route::get('pedidos', [PedidosDeLaLavanderiaController::class, 'lista'])->name('lavanderia.pedidos.lista');
    Route::get('pedidos/{pedido}', [PedidosDeLaLavanderiaController::class, 'mostrar'])->name('lavanderia.pedidos.mostrar');
    Route::post('pedidos/{pedido}/aceptar', [PedidosDeLaLavanderiaController::class, 'aceptar'])
        ->name('lavanderia.pedidos.aceptar');
    Route::post('pedidos/{pedido}/rechazar', [PedidosDeLaLavanderiaController::class, 'rechazar'])
        ->name('lavanderia.pedidos.rechazar');
    // Los botones Recolectado, En proceso, Listo y En proceso de entrega (estados-del-pedido).
    Route::post('pedidos/{pedido}/estado', [PedidosDeLaLavanderiaController::class, 'marcar'])
        ->name('lavanderia.pedidos.estado');
    // Sus pedidos completados (historial-de-la-lavanderia). El contrato está en el controlador.
    Route::get('historial', [HistorialDeLaLavanderiaController::class, 'historial'])->name('lavanderia.historial');
    // Sus pedidos completados del día y de la semana, en la hora de la plaza (historial-de-la-lavanderia).
    Route::get('estadisticas', [HistorialDeLaLavanderiaController::class, 'estadisticas'])
        ->name('lavanderia.estadisticas');
});

// El Módulo C del socio repartidor (repartidor). El contrato está en openspec/changes/repartidor/design.md, "Contrato de
// la API", y en cada controlador. Con sesión y por el componente único de autorización con el rol de repartidor.
Route::middleware(['auth:sanctum', 'autorizar:repartidor'])->prefix('repartidor')->group(function () {
    // Su perfil y su estado, también mientras espera su alta o está suspendido (repartidor-E01 y E08).
    Route::get('perfil', [RepartidorController::class, 'perfil'])->name('repartidor.perfil');

    // Lo demás, solo si el repartidor opera: activo y con su plaza (repartidor-E03 y E06). Sin whereUlid a propósito:
    // un valor que no es ULID responde el mismo 404 que un pedido ajeno.
    Route::middleware('repartidor-opera')->group(function () {
        Route::get('pedidos', [PedidosDelRepartidorController::class, 'lista'])->name('repartidor.pedidos.lista');
        Route::get('pedidos/{pedido}', [PedidosDelRepartidorController::class, 'mostrar'])
            ->name('repartidor.pedidos.mostrar');
        Route::post('pedidos/{pedido}/aceptar', [PedidosDelRepartidorController::class, 'aceptar'])
            ->name('repartidor.pedidos.aceptar');
        Route::post('pedidos/{pedido}/rechazar', [PedidosDelRepartidorController::class, 'rechazar'])
            ->name('repartidor.pedidos.rechazar');
        // Los botones Recolectado, En tránsito y Entregado.
        Route::post('pedidos/{pedido}/estado', [PedidosDelRepartidorController::class, 'marcar'])
            ->name('repartidor.pedidos.estado');
        // Sus entregas completadas (repartidor-E25).
        Route::get('historial', [HistorialDelRepartidorController::class, 'historial'])->name('repartidor.historial');
        // Sus entregas del día y de la semana, en la hora de su plaza (repartidor-E28).
        Route::get('estadisticas', [HistorialDelRepartidorController::class, 'estadisticas'])
            ->name('repartidor.estadisticas');
    });
});
