<?php

namespace App\Http\Controllers\Desarrollo;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Cobro\DireccionDeRegreso;
use App\Cobro\PasarelaSimulada;
use App\Http\Controllers\Controller;
use App\Models\EventoPasarela;
use App\Models\Pago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * La página de checkout de desarrollo, la que abre el checkout de la pasarela simulada (openspec/changes/pago/design.md,
 * "Decisiones de la implementación"). Sirve para probar el cobro desde la app en local sin ninguna pasarela real. No es
 * una pantalla del producto: sus rutas solo se registran en local y en las pruebas (routes/web.php).
 *
 *     GET  /desarrollo/pasarela/checkout/{orden}   el cobro de la orden, con los botones Aprobar y Rechazar
 *     POST /desarrollo/pasarela/checkout/{orden}   resultado=aprobado|rechazado
 *
 * Cada botón manda el aviso simulado por el mismo camino del webhook (App\Cobro\Desarrollo\CobroSimulado::avisar(), que
 * lo entrega a App\Cobro\RecepcionDeAvisos) y después regresa a la app por su dirección de regreso, con el resultado y el
 * cobro (App\Cobro\DireccionDeRegreso). Sin dirección de regreso, la página le pide a la persona regresar a la app.
 */
class CheckoutDeDesarrolloController extends Controller
{
    public function mostrar(string $orden): Response
    {
        return response()->view('desarrollo.checkout', ['cobro' => $this->cobroDeLaOrden($orden)]);
    }

    public function resultado(
        Request $peticion,
        string $orden,
        CobroSimulado $cobroSimulado,
        DireccionDeRegreso $regreso,
    ): RedirectResponse|Response {
        $cobro = $this->cobroDeLaOrden($orden);
        $resultado = $peticion->validate([
            'resultado' => ['required', Rule::in([EventoPasarela::APROBADO, EventoPasarela::RECHAZADO])],
        ])['resultado'];

        $cobroSimulado->avisar($cobro, $resultado);

        $aprobado = $resultado === EventoPasarela::APROBADO;
        $direccion = $aprobado ? $regreso->exito($cobro) : $regreso->error($cobro);

        if ($direccion !== null) {
            return redirect()->away($direccion);
        }

        return response()->view('desarrollo.checkout-listo', ['aprobado' => $aprobado]);
    }

    /**
     * El cobro de esa orden en la pasarela simulada; ninguno, 404.
     */
    private function cobroDeLaOrden(string $orden): Pago
    {
        return Pago::query()
            ->where('pasarela', PasarelaSimulada::CODIGO)
            ->where('id_pasarela_orden', $orden)
            ->with('pedido')
            ->firstOrFail();
    }
}
