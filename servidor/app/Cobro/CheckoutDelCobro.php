<?php

namespace App\Cobro;

use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;

/**
 * El checkout del cobro de un pedido (pago-E01; ADR-0010, decisión 1): crea en la pasarela la orden por el monto del
 * cobro, el que guarda servidor/ y nunca uno que mande la app, y la liga al cobro por su id_pasarela_orden. Si el cobro
 * ya tiene un checkout vigente en la pasarela configurada, devuelve ese mismo en lugar de crear otro.
 *
 * Abrir el checkout no da nada por pagado: el cobro sigue pendiente hasta el aviso auténtico de la pasarela (pago-E07).
 * Un cobro fallido, cuyo último intento se rechazó, se reabre: vuelve a pendiente, con el mismo checkout si sigue
 * vigente, para volver a intentar el pago del mismo cobro (pago-E04).
 *
 * Bloquea el cobro mientras decide, así que dos peticiones a la vez del mismo pedido no crean dos órdenes. Quién puede
 * pedirlo lo decide antes el componente de autorización: aquí llega el pedido de la cuenta.
 */
final class CheckoutDelCobro
{
    public function __construct(private readonly Pasarelas $pasarelas) {}

    /**
     * @throws CobroQueNoSePaga si el pedido no tiene cobro, ya está pagado o ya no se puede pagar
     * @throws PasarelaNoDisponible si no hay pasarela en este entorno, le falta su llave o no responde
     */
    public function abrir(Pedido $pedido): Checkout
    {
        return DB::transaction(function () use ($pedido): Checkout {
            $cobro = Pago::query()
                ->where('id_pedidos', $pedido->getKey())
                ->where('tipo', Pago::COBRO)
                ->lockForUpdate()
                ->first();

            $this->revisar($cobro);

            $pasarela = $this->pasarelas->configurada();
            $url = $this->checkoutVigente($pasarela, $cobro);

            if ($url === null) {
                $orden = $pasarela->crearOrden($cobro);
                $cobro->forceFill(['pasarela' => $pasarela->codigo(), 'id_pasarela_orden' => $orden->id]);
                $url = $orden->urlDelCheckout;
            }

            // Un intento nuevo después de un rechazo.
            $cobro->forceFill(['estado' => Pago::PENDIENTE])->save();

            return new Checkout($cobro, $url);
        });
    }

    /**
     * @phpstan-assert Pago $cobro
     *
     * @throws CobroQueNoSePaga
     */
    private function revisar(?Pago $cobro): void
    {
        if ($cobro === null) {
            throw CobroQueNoSePaga::sinCobro();
        }

        if ($cobro->estado === Pago::PAGADO) {
            throw CobroQueNoSePaga::yaPagado();
        }

        if (! in_array($cobro->estado, [Pago::PENDIENTE, Pago::FALLIDO], true)) {
            throw CobroQueNoSePaga::yaNoSePaga();
        }
    }

    /**
     * La URL del checkout que el cobro ya tiene, si sigue vigente en la pasarela que cobra en este entorno.
     */
    private function checkoutVigente(Pasarela $pasarela, Pago $cobro): ?string
    {
        if ($cobro->id_pasarela_orden === null || $cobro->pasarela !== $pasarela->codigo()) {
            return null;
        }

        return $pasarela->checkoutVigente($cobro->id_pasarela_orden);
    }
}
