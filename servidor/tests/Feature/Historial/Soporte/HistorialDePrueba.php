<?php

namespace Tests\Feature\Historial\Soporte;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\Direccion;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas del historial: pedidos con su recolección en el día y la hora que se pasan, una
 * lavandería que recolecta de noche y la consulta del historial con la sesión de la cuenta. Se usa junto con
 * AgendaDePrueba, EstadosDePrueba, LavanderiasDePrueba, PedidosDePrueba y PlazasDePrueba, después de prepararPedido().
 *
 * Cada pedido nace por la misma ruta que en la app: el reloj se fija al empezar ese día en la hora de la plaza, el
 * usuario acepta el resumen con una ventana de ese día y lo paga con la pasarela simulada, cuyo cobro lo confirma (C-188). Las
 * fechas son de prueba.
 */
trait HistorialDePrueba
{
    /**
     * Un pedido de la cuenta que espera su pago: nació al aceptar el resumen, con la recolección de una hora en ese día
     * y a esa hora de la plaza de la lavandería. Por omisión, en la lavandería de prepararPedido() y con una dirección
     * nueva de la cuenta en su zona; con una lavandería de otra plaza, la recogida va en una zona de esa plaza.
     */
    protected function pedidoPorPagarEl(
        Usuario $cuenta,
        string $fecha,
        string $hora = '11:00',
        ?Lavanderia $lavanderia = null,
        ?Direccion $recogida = null,
    ): Pedido {
        $lavanderia ??= $this->lavanderia;
        $zona = $lavanderia->plaza->zona_horaria;

        // El reloj, al empezar ese día en la plaza: toda ventana del día está por delante.
        $this->travelTo(CarbonImmutable::parse($fecha.' 00:00:00', $zona));

        $inicio = CarbonImmutable::parse($fecha.' '.$hora, $zona);

        $id = $this->aceptarResumen($cuenta, $this->resumen($recogida ?? $this->direccionDe($cuenta), datos: [
            'lavanderia' => $lavanderia->id_publico,
            'ventana' => [
                'inicio' => $inicio->toIso8601String(),
                'fin' => $inicio->addHour()->toIso8601String(),
            ],
        ]))->assertCreated()->json('pedido.id_publico');

        return Pedido::query()->where('id_publico', $id)->sole();
    }

    /**
     * El mismo pedido, ya confirmado por su cobro pagado: con su folio y en Confirmado.
     */
    protected function pedidoConfirmadoEl(
        Usuario $cuenta,
        string $fecha,
        string $hora = '11:00',
        ?Lavanderia $lavanderia = null,
        ?Direccion $recogida = null,
    ): Pedido {
        $pedido = $this->pedidoPorPagarEl($cuenta, $fecha, $hora, $lavanderia, $recogida);

        return app(CobroSimulado::class)->pagar($pedido)->fresh();
    }

    /**
     * El pedido va de Confirmado a Entregado, un estado tras otro: la lavandería lo acepta y lo lleva con sus botones
     * hasta En proceso de entrega, y el repartidor lo entrega (EstadosDePrueba::llevarHastaEntregado()).
     */
    protected function entregar(Pedido $pedido): Pedido
    {
        $this->aceptarComoLaLavanderia($pedido);

        return $this->llevarHastaEntregado($pedido);
    }

    /**
     * Una lavandería activa de la plaza de prueba que recolecta de 18:00 a 22:00 todos los días, con el lavado estándar:
     * sus ventanas de las 18:00 en adelante caen, en UTC, en el día siguiente.
     */
    protected function lavanderiaDeNoche(): Lavanderia
    {
        $lavanderia = $this->lavanderiaConHorario(
            $this->plaza,
            array_map(fn (int $dia): array => [$dia, '18:00', '22:00'], range(self::LUNES, self::DOMINGO)),
        );
        $this->servicioDePrueba($lavanderia, 'estandar', self::PRECIO_POR_KILO);

        return $lavanderia;
    }

    /**
     * El usuario pide su historial a la API, con los parámetros que se pasan.
     *
     * @param  array<string, mixed>  $parametros
     */
    protected function pedirHistorial(Usuario $cuenta, array $parametros = []): TestResponse
    {
        return $this->pedirComoUsuario('/api/pedidos', $parametros, $cuenta);
    }

    /**
     * Los id_publico de la lista, en el orden en que llegaron.
     *
     * @return list<string>
     */
    protected function idsDe(TestResponse $respuesta): array
    {
        return array_column($respuesta->json('pedidos'), 'id_publico');
    }
}
