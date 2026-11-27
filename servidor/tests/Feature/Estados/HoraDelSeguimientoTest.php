<?php

namespace Tests\Feature\Estados;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La hora de cada estado en el seguimiento: cada estado cumplido, y el actual, trae la hora en que el pedido llegó a
 * él, en la hora de la plaza; los pendientes, ninguna (decisión 25 del acta del 2026-11-20, DD-135 punto 4).
 *
 * El reloj de PedidosDePrueba está en la hora de la plaza de prueba (UTC-6): las 09:30 del jueves de prueba son las
 * 15:30 en UTC.
 *
 * Escenario: estados-E24 · Spec: openspec/changes/estados-del-pedido/specs/estados/spec.md
 */
class HoraDelSeguimientoTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->cuenta = $this->cuentaDeUsuario();
        $this->pedido = $this->pedidoConfirmadoDe($this->cuenta);
    }

    #[TestDox('estados-E24: el pedido recién confirmado trae la hora de Confirmado, en la hora de la plaza, y los cuatro pendientes sin hora')]
    public function test_estados_e24_el_pedido_recien_confirmado_trae_la_hora_de_confirmado(): void
    {
        $this->assertSame(
            [
                'confirmado' => self::JUEVES_DE_PRUEBA.'T09:30:00-06:00',
                'recolectado' => null,
                'en_proceso' => null,
                'listo' => null,
                'entregado' => null,
            ],
            $this->horas(),
        );
    }

    #[TestDox('estados-E24: cada estado al que llega el pedido trae la hora en que llegó, y los anteriores conservan la suya')]
    public function test_estados_e24_cada_estado_cumplido_conserva_su_hora(): void
    {
        // La lavandería lo acepta a las 09:45: el usuario lo sigue viendo en Confirmado, con la hora de la confirmación.
        $this->elJuevesALas('09:45:00');
        $this->aceptarComoLaLavanderia($this->pedido);

        $this->elJuevesALas('10:05:00');
        $this->marcarComoLaLavanderia($this->pedido, EstadoPedido::RECOLECTADO);

        $this->elJuevesALas('11:40:00');
        $this->marcarComoLaLavanderia($this->pedido, EstadoPedido::EN_PROCESO);

        $this->assertSame(
            [
                'confirmado' => self::JUEVES_DE_PRUEBA.'T09:30:00-06:00',
                'recolectado' => self::JUEVES_DE_PRUEBA.'T10:05:00-06:00',
                'en_proceso' => self::JUEVES_DE_PRUEBA.'T11:40:00-06:00',
                'listo' => null,
                'entregado' => null,
            ],
            $this->horas(),
        );
    }

    #[TestDox('estados-E24: un pedido que espera su pago no trae hora en ninguno de los cinco')]
    public function test_estados_e24_un_pedido_por_pagar_no_trae_horas(): void
    {
        $porPagar = $this->aceptarResumen($this->cuenta, $this->resumen($this->direccionDe($this->cuenta)))
            ->assertCreated()
            ->json('pedido.id_publico');

        $this->assertSame([null, null, null, null, null], array_values($this->horas($porPagar)));
    }

    /**
     * La hora de cada uno de los cinco, por su código, como la entrega la consulta del usuario.
     *
     * @return array<string, string|null>
     */
    private function horas(?string $identificador = null): array
    {
        $seguimiento = $this->consultarPedido($this->cuenta, $identificador ?? $this->pedido->id_publico)
            ->assertOk()
            ->json('pedido.seguimiento');

        return array_column($seguimiento, 'ocurrido_en', 'codigo');
    }
}
