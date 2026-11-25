<?php

namespace App\Cobro\Desarrollo;

use App\Cobro\CheckoutDelCobro;
use App\Cobro\PasarelaNoDisponible;
use App\Cobro\Pasarelas;
use App\Cobro\PasarelaSimulada;
use App\Cobro\RecepcionDeAvisos;
use App\Models\EventoPasarela;
use App\Models\Pago;
use App\Models\Pedido;

/**
 * El cobro de un pedido por la pasarela simulada, para las herramientas de desarrollo, el sembrado de local y las
 * pruebas de otros cambios que necesitan un pedido pagado (openspec/changes/pago/design.md, "Decisiones de la
 * implementación").
 *
 * Va por el camino real: el checkout lo abre App\Cobro\CheckoutDelCobro y el aviso lo recibe App\Cobro\RecepcionDeAvisos,
 * el mismo del webhook, firmado por la pasarela simulada. Solo prepara directo lo que todavía no tiene camino: el cobro
 * pendiente, que en el flujo real creará pago-E14 cuando DD-012 fije el monto.
 *
 * Solo sirve con la pasarela simulada, que solo existe en local y en las pruebas (App\Cobro\Pasarelas): en cualquier
 * otro entorno, o con otra pasarela configurada, no hace nada y responde que el pago no está disponible.
 */
final class CobroSimulado
{
    /**
     * El monto ficticio del cobro de desarrollo, en pesos. El de la operación lo fija el modelo de cobro de EL CLIENTE
     * (pago-E14, DD-012).
     */
    public const MONTO_DE_PRUEBA = '100.00';

    public function __construct(
        private readonly Pasarelas $pasarelas,
        private readonly CheckoutDelCobro $checkouts,
        private readonly RecepcionDeAvisos $recepcion,
    ) {}

    /**
     * El pedido, pagado por la pasarela simulada y por eso confirmado: con su cobro pendiente si no lo tenía, su checkout
     * y el aviso de aprobado.
     *
     * @throws PasarelaNoDisponible si la pasarela configurada no es la simulada
     */
    public function pagar(Pedido $pedido, string $monto = self::MONTO_DE_PRUEBA): Pedido
    {
        $cobro = $pedido->cobro()->first() ?? $this->prepararCobro($pedido, $monto);

        $this->avisar($cobro, EventoPasarela::APROBADO);

        return $pedido->fresh();
    }

    /**
     * El cobro pendiente del pedido, con tarjeta, por el monto dado, en la pasarela simulada.
     *
     * @throws PasarelaNoDisponible si la pasarela configurada no es la simulada
     */
    public function prepararCobro(Pedido $pedido, string $monto = self::MONTO_DE_PRUEBA): Pago
    {
        $this->simulada();

        return Pago::create([
            'id_pedidos' => $pedido->getKey(),
            'tipo' => Pago::COBRO,
            'metodo' => Pago::TARJETA,
            'pasarela' => PasarelaSimulada::CODIGO,
            'monto' => $monto,
            'estado' => Pago::PENDIENTE,
        ])->fresh();
    }

    /**
     * La pasarela simulada manda su aviso de ese resultado para la orden del cobro, por el mismo camino del webhook. Si
     * el cobro todavía no tiene checkout, lo abre antes, como lo pide la app.
     *
     * @param  string  $resultado  EventoPasarela::APROBADO o EventoPasarela::RECHAZADO
     *
     * @throws PasarelaNoDisponible si la pasarela configurada no es la simulada
     */
    public function avisar(Pago $cobro, string $resultado): EventoPasarela
    {
        $simulada = $this->simulada();

        if ($cobro->id_pasarela_orden === null) {
            $cobro = $this->checkouts->abrir($cobro->pedido)->cobro;
        }

        $aviso = $simulada->avisoSimulado($cobro->id_pasarela_orden, $resultado);

        return $this->recepcion->recibir($aviso['cuerpo'], array_change_key_case($aviso['cabeceras']));
    }

    /**
     * @throws PasarelaNoDisponible si la pasarela configurada no es la simulada
     */
    private function simulada(): PasarelaSimulada
    {
        $pasarela = $this->pasarelas->configurada();

        if (! $pasarela instanceof PasarelaSimulada) {
            throw new PasarelaNoDisponible('El cobro simulado necesita la pasarela simulada (PASARELA=simulada).');
        }

        return $pasarela;
    }
}
