<?php

namespace Tests\Feature\PedidosLavanderia\Soporte;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

/**
 * Las peticiones de la app del Módulo B, con la sesión de la cuenta de una lavandería: su lista de pedidos activos, un
 * pedido, aceptar, rechazar con motivo y los botones del pedido (openspec/changes/pedidos-de-la-lavanderia/design.md y
 * openspec/changes/estados-del-pedido/design.md, "Contrato de la API"). Se usa junto con AgendaDePrueba,
 * EstadosDePrueba, LavanderiasDePrueba, PedidosDePrueba y PlazasDePrueba.
 *
 * Cada petición olvida la cuenta de la anterior (forgetGuards), como dos peticiones reales al servidor.
 */
trait PedidosDeLaLavanderiaDePrueba
{
    protected function listaDeLaLavanderia(Usuario $cuenta): TestResponse
    {
        return $this->conLaSesionDe($cuenta)->getJson('/api/lavanderia/pedidos');
    }

    protected function pedidoComoLavanderia(Usuario $cuenta, string $pedido): TestResponse
    {
        return $this->conLaSesionDe($cuenta)->getJson("/api/lavanderia/pedidos/{$pedido}");
    }

    protected function aceptarPedido(Usuario $cuenta, string $pedido): TestResponse
    {
        return $this->conLaSesionDe($cuenta)->postJson("/api/lavanderia/pedidos/{$pedido}/aceptar");
    }

    /**
     * @param  mixed  $motivo  Sin motivo (null), la petición no lo lleva.
     */
    protected function rechazarPedido(Usuario $cuenta, string $pedido, mixed $motivo = null): TestResponse
    {
        return $this->conLaSesionDe($cuenta)->postJson(
            "/api/lavanderia/pedidos/{$pedido}/rechazar",
            $motivo === null ? [] : ['motivo' => $motivo],
        );
    }

    /**
     * El botón de la lavandería: el estado al que pasa el pedido (Módulo B #3).
     */
    protected function marcarPedido(Usuario $cuenta, string $pedido, mixed $estado): TestResponse
    {
        return $this->conLaSesionDe($cuenta)->postJson("/api/lavanderia/pedidos/{$pedido}/estado", ['estado' => $estado]);
    }

    /**
     * Otra lavandería activa, con su propia cuenta de socio: en la plaza de prueba de prepararPedido(), o en la que se
     * pase. Abre todos los días de 9:00 a 13:00, con el lavado estándar por kilo, como la de prepararPedido().
     */
    protected function otraLavanderia(?Plaza $plaza = null): Lavanderia
    {
        $lavanderia = $this->lavanderiaConHorario(
            $plaza ?? $this->plaza,
            array_map(fn (int $dia): array => [$dia, '09:00', '13:00'], range(self::LUNES, self::DOMINGO)),
        );
        $this->servicioDePrueba($lavanderia, 'estandar', self::PRECIO_POR_KILO);

        return $lavanderia;
    }

    /**
     * Un pedido de la cuenta a esa lavandería, con la recolección de una hora a esa hora del jueves de prueba, que espera
     * su pago. La recogida es una dirección nueva de la cuenta, en la plaza de la lavandería y en el punto que se pase,
     * que tiene que caer en una zona de esa plaza.
     */
    protected function pedidoPorPagarEn(
        Lavanderia $lavanderia,
        Usuario $cuenta,
        string $hora = '11:00',
        float $latitud = 10.5,
        float $longitud = -119.5,
    ): Pedido {
        $inicio = CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' '.$hora, $lavanderia->plaza->zona_horaria);
        $recogida = $this->direccionDe($cuenta, [
            'id_plazas' => $lavanderia->id_plazas,
            'ubicacion' => ['latitud' => $latitud, 'longitud' => $longitud],
        ]);

        $id = $this->aceptarResumen($cuenta, $this->resumen($recogida, datos: [
            'lavanderia' => $lavanderia->id_publico,
            'ventana' => ['inicio' => $inicio->toIso8601String(), 'fin' => $inicio->addHour()->toIso8601String()],
        ]))->assertCreated()->json('pedido.id_publico');

        return Pedido::query()->where('id_publico', $id)->sole();
    }

    /**
     * El mismo pedido, ya confirmado por su cobro pagado (C-188).
     */
    protected function pedidoConfirmadoEn(
        Lavanderia $lavanderia,
        Usuario $cuenta,
        string $hora = '11:00',
        float $latitud = 10.5,
        float $longitud = -119.5,
    ): Pedido {
        $pedido = $this->pedidoPorPagarEn($lavanderia, $cuenta, $hora, $latitud, $longitud);

        return app(CobroSimulado::class)->pagar($pedido)->fresh();
    }

    /**
     * Los id_publico de la lista de la lavandería, en el orden en que llegaron.
     *
     * @return list<string>
     */
    protected function idsDeLaLista(TestResponse $respuesta): array
    {
        return array_column($respuesta->json('pedidos') ?? [], 'id_publico');
    }

    /**
     * El pedido no cambió: el mismo estado actual y los mismos eventos que antes.
     *
     * @param  list<int>  $eventos  Los id_pedido_eventos de antes, en orden.
     */
    protected function assertPedidoSinCambio(Pedido $pedido, string $estado, array $eventos): void
    {
        $this->assertSame($estado, $pedido->fresh()->estado->codigo);
        $this->assertSame($eventos, $this->eventosDe($pedido));
    }

    /**
     * Los id_pedido_eventos del pedido, en orden.
     *
     * @return list<int>
     */
    protected function eventosDe(Pedido $pedido): array
    {
        return $pedido->eventos()->orderBy('id_pedido_eventos')->pluck('id_pedido_eventos')->all();
    }

    private function conLaSesionDe(Usuario $cuenta): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken);
    }
}
