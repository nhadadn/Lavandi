<?php

namespace Tests\Feature\Pago\Soporte;

use App\Cobro\CheckoutDelCobro;
use App\Cobro\PasarelaSimulada;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas del cobro: un pedido en el estado previo al pago, creado como lo crea la app al aceptar
 * el resumen, su cobro pendiente y la pasarela simulada de las pruebas. Se usa junto con AgendaDePrueba,
 * LavanderiasDePrueba, PedidosDePrueba y PlazasDePrueba, después de prepararPedido().
 *
 * El cobro se siembra: en el flujo real lo crea pago-E14 cuando DD-012 fije el monto (openspec/changes/pago/design.md,
 * decisión 1). Su monto, MONTO_DE_PRUEBA, es ficticio.
 */
trait CobrosDePrueba
{
    /** El monto ficticio del cobro de prueba, en pesos. El de la operación lo fija el modelo de cobro (DD-012). */
    protected const MONTO_DE_PRUEBA = '180.00';

    /**
     * Un pedido en el estado previo al pago, de una cuenta de usuario nueva, creado al aceptar el resumen.
     */
    protected function pedidoPorPagar(): Pedido
    {
        $cuenta = $this->cuentaDeUsuario();

        $id = $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta)))
            ->assertCreated()
            ->json('pedido.id_publico');

        return Pedido::query()->where('id_publico', $id)->sole();
    }

    /**
     * El cobro pendiente del pedido, con tarjeta y en la pasarela configurada, todavía sin orden, con lo que se pase
     * encima.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function cobroPendiente(Pedido $pedido, string $monto = self::MONTO_DE_PRUEBA, array $datos = []): Pago
    {
        return Pago::create([
            'id_pedidos' => $pedido->getKey(),
            'tipo' => Pago::COBRO,
            'metodo' => Pago::TARJETA,
            'pasarela' => PasarelaSimulada::CODIGO,
            'monto' => $monto,
            'estado' => Pago::PENDIENTE,
            ...$datos,
        ])->fresh();
    }

    /**
     * La pasarela simulada de las pruebas, la misma instancia que usa el servidor en la prueba: guarda las peticiones
     * que le llegaron.
     */
    protected function pasarelaSimulada(): PasarelaSimulada
    {
        return app(PasarelaSimulada::class);
    }

    /**
     * El cobro con su checkout creado en la pasarela simulada, por el camino real (App\Cobro\CheckoutDelCobro).
     */
    protected function conCheckout(Pago $cobro): Pago
    {
        app(CheckoutDelCobro::class)->abrir($cobro->pedido);

        return $cobro->fresh();
    }

    /**
     * La pasarela simulada manda al webhook su aviso de ese tipo para la orden del cobro, firmado con su llave, como lo
     * mandaría una pasarela real. Sin identificador, el aviso es nuevo.
     *
     * @param  string  $tipo  aprobado, rechazado, vencido o cualquier otro que la pasarela mande
     */
    protected function avisar(Pago $cobro, string $tipo, ?string $idEvento = null): TestResponse
    {
        $aviso = $this->pasarelaSimulada()->avisoSimulado($cobro->id_pasarela_orden, $tipo, $idEvento);

        return $this->avisoCrudo($aviso['cuerpo'], $aviso['cabeceras']);
    }

    /**
     * Llega al webhook una petición con ese cuerpo, tal cual, y esas cabeceras, sin sesión.
     *
     * @param  array<string, string>  $cabeceras
     */
    protected function avisoCrudo(string $cuerpo, array $cabeceras = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withoutToken()->call(
            'POST',
            '/api/pasarela/avisos',
            server: $this->transformHeadersToServerVars(['Content-Type' => 'application/json', ...$cabeceras]),
            content: $cuerpo,
        );
    }
}
