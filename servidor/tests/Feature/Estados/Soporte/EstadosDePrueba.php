<?php

namespace Tests\Feature\Estados\Soporte;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\PedidoEvento;
use App\Models\Repartidor;
use App\Models\Usuario;
use App\Pedidos\CambioDeEstado;
use App\Repartidores\RespuestaDelRepartidor;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas de estados-del-pedido: un pedido confirmado por su cobro pagado, la lavandería
 * que lo acepta y lo mueve con sus botones, y la consulta del usuario. Se usa junto con AgendaDePrueba,
 * LavanderiasDePrueba, PedidosDePrueba y PlazasDePrueba, después de prepararPedido().
 *
 * La lavandería acepta y mueve el pedido con la operación real, App\Pedidos\CambioDeEstado (estados-del-pedido, tarea
 * 2.1): cada cambio guarda el estado actual y su evento en una transacción y publica EstadoDelPedidoCambiado, y uno
 * fuera de la tabla de ADR-0021 se rechaza. Sin aceptar no hay botones: un pedido que se va a mover se acepta primero
 * (pedidoAceptadoDe() o aceptarComoLaLavanderia()).
 *
 * Entregado no es de la lavandería (estados-E13, DD-022): lo marca el repartidor con su botón, la operación real del
 * cambio repartidor (entregarComoElRepartidor()). La oferta al repartidor se crea directo en la base, porque quién
 * asigna espera DD-014, y él la acepta con la operación real.
 */
trait EstadosDePrueba
{
    /**
     * Un pedido de la cuenta, creado al aceptar el resumen y confirmado por su cobro cuando la pasarela simulada
     * acredita el pago (C-188; pago-E18). Su historial queda con dos eventos: por_pagar y confirmado.
     */
    protected function pedidoConfirmadoDe(Usuario $cuenta): Pedido
    {
        $id = $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta)))
            ->assertCreated()
            ->json('pedido.id_publico');

        // La pasarela simulada acredita el pago con su aviso, por el camino del webhook, y el cobro confirma el pedido.
        return app(CobroSimulado::class)->pagar(Pedido::query()->where('id_publico', $id)->sole())->fresh();
    }

    /**
     * El mismo pedido, ya aceptado por su lavandería: listo para sus botones. Su historial queda con tres eventos:
     * por_pagar, confirmado y aceptado.
     */
    protected function pedidoAceptadoDe(Usuario $cuenta): Pedido
    {
        $pedido = $this->pedidoConfirmadoDe($cuenta);
        $this->aceptarComoLaLavanderia($pedido);

        return $pedido->fresh();
    }

    /**
     * La cuenta que administra la lavandería del pedido: la que lo acepta y toca sus botones.
     */
    protected function cuentaDeLaLavanderia(Pedido $pedido): Usuario
    {
        return $pedido->lavanderia->administrador;
    }

    /**
     * La lavandería acepta el pedido, a la hora del reloj (pedidos-lavanderia-E07).
     */
    protected function aceptarComoLaLavanderia(Pedido $pedido): PedidoEvento
    {
        app(CambioDeEstado::class)->aceptar($pedido, $this->cuentaDeLaLavanderia($pedido));

        return $this->ultimoEventoDe($pedido);
    }

    /**
     * La lavandería rechaza el pedido con ese motivo, a la hora del reloj (pedidos-lavanderia-E12).
     */
    protected function rechazarComoLaLavanderia(Pedido $pedido, string $motivo = 'Motivo de prueba.'): PedidoEvento
    {
        app(CambioDeEstado::class)->rechazar($pedido, $this->cuentaDeLaLavanderia($pedido), $motivo);

        return $this->ultimoEventoDe($pedido);
    }

    /**
     * La lavandería toca el botón de ese estado, a la hora del reloj, con la operación real (estados-E03 y E04): el
     * estado actual y su evento, en una transacción, y la publicación del cambio.
     */
    protected function marcarComoLaLavanderia(Pedido $pedido, string $codigo): PedidoEvento
    {
        app(CambioDeEstado::class)->marcar($pedido, $this->cuentaDeLaLavanderia($pedido), $codigo);

        return $this->ultimoEventoDe($pedido);
    }

    /**
     * El repartidor del pedido marca Entregado con su botón, la operación real (Módulo C #3;
     * App\Pedidos\CambioDeEstado::marcarComoRepartidor(), repartidor, tarea 1.5): el estado actual y su evento con la
     * cuenta del repartidor, en una transacción, y la publicación del cambio. Como la lavandería no marca Entregado
     * (DD-022), es la única forma de llegar a él. Exige el pedido en en_entrega, el paso anterior de ADR-0021.
     *
     * El repartidor es el que se pasa, el que ya aceptó el pedido o, si no tiene, uno nuevo de su plaza. Al que todavía no
     * lo aceptó se le ofrece y lo acepta (repartidorQueLoAcepta()).
     */
    protected function entregarComoElRepartidor(Pedido $pedido, ?Repartidor $repartidor = null): PedidoEvento
    {
        $pedido = $pedido->fresh();
        $this->assertSame(EstadoPedido::EN_ENTREGA, $pedido->estado->codigo, 'Entregado va después de en_entrega.');

        $repartidor ??= $pedido->repartidor;
        $repartidor = $repartidor !== null && $repartidor->getKey() === $pedido->id_repartidores
            ? $repartidor
            : $this->repartidorQueLoAcepta($pedido, $repartidor);

        app(CambioDeEstado::class)->marcarComoRepartidor($pedido, $repartidor, EstadoPedido::ENTREGADO);

        return $this->ultimoEventoDe($pedido);
    }

    /**
     * Se le ofrece el pedido a ese repartidor, o a uno nuevo y activo de la plaza del pedido, directo en la base, porque
     * quién asigna espera DD-014 (repartidor-E13); y lo acepta con la operación real (App\Repartidores\
     * RespuestaDelRepartidor; repartidor-E15).
     */
    protected function repartidorQueLoAcepta(Pedido $pedido, ?Repartidor $repartidor = null): Repartidor
    {
        $repartidor ??= Repartidor::factory()->en($pedido->plaza)->create();

        PedidoAsignacion::factory()->para($pedido, $repartidor)->create();
        app(RespuestaDelRepartidor::class)->aceptar($pedido, $repartidor);

        return $repartidor;
    }

    /**
     * La lavandería lleva el pedido aceptado hasta En proceso de entrega, y el repartidor lo entrega: un estado tras otro.
     */
    protected function llevarHastaEntregado(Pedido $pedido): Pedido
    {
        foreach (CambioDeEstado::BOTONES as $codigo) {
            $this->marcarComoLaLavanderia($pedido, $codigo);
        }

        $this->entregarComoElRepartidor($pedido);

        return $pedido->fresh();
    }

    /**
     * El usuario consulta el pedido a la API con su sesión. Olvida la cuenta de la petición anterior (forgetGuards),
     * como dos peticiones reales al servidor.
     */
    protected function consultarPedido(Usuario $cuenta, string $identificador): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->getJson('/api/pedidos/'.$identificador);
    }

    private function ultimoEventoDe(Pedido $pedido): PedidoEvento
    {
        return $pedido->eventos()->orderByDesc('id_pedido_eventos')->firstOrFail();
    }
}
