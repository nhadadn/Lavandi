<?php

namespace Tests\Feature\Historial;

use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Historial\Soporte\HistorialDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El filtro por fecha de la lista de pedidos del usuario, GET /api/pedidos?desde=AAAA-MM-DD&hasta=AAAA-MM-DD
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #13, "filtro por fecha").
 *
 * La fecha que se filtra es la del resumen del pedido, la de su recolección (Módulo A #9), contada en la hora de la
 * plaza del pedido y no en UTC (design.md, decisión 1; vault/arquitectura/modelo-multi-plaza.md). Los dos días del
 * rango entran; con uno solo, el rango queda abierto del otro lado.
 *
 * Escenarios: historial-E03 y historial-E04 · Spec: openspec/changes/historial-y-calificacion/specs/historial/spec.md ·
 * Tarea 1.2
 */
class FiltroPorFechaTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use HistorialDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    private Pedido $del3;

    private Pedido $del10;

    private Pedido $del20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        // Un usuario con pedidos del 3, del 10 y del 20 de marzo.
        $this->cuenta = $this->cuentaDeUsuario();
        $this->del3 = $this->entregar($this->pedidoConfirmadoEl($this->cuenta, '2027-03-03'));
        $this->del10 = $this->entregar($this->pedidoConfirmadoEl($this->cuenta, '2027-03-10'));
        $this->del20 = $this->pedidoConfirmadoEl($this->cuenta, '2027-03-20');
    }

    #[TestDox('historial-E03: del 10 al 20 de marzo, el usuario ve los pedidos del 10 y del 20 y no ve el del 3')]
    public function test_historial_e03_el_filtro_deja_solo_los_pedidos_del_rango(): void
    {
        $respuesta = $this->pedirHistorial($this->cuenta, ['desde' => '2027-03-10', 'hasta' => '2027-03-20'])->assertOk();

        $this->assertSame([$this->del20->id_publico, $this->del10->id_publico], $this->idsDe($respuesta));
        $this->assertSame(['2027-03-20', '2027-03-10'], array_column($respuesta->json('pedidos'), 'fecha'));
        $this->assertStringNotContainsStringIgnoringCase($this->del3->id_publico, $respuesta->getContent());
        $this->assertStringNotContainsStringIgnoringCase($this->del3->folio, $respuesta->getContent());
    }

    #[TestDox('historial-E03: los dos días del rango entran: del 10 al 10 de marzo, el usuario ve solo el pedido del 10')]
    public function test_historial_e03_los_dos_dias_del_rango_entran(): void
    {
        $this->assertSame(
            [$this->del10->id_publico],
            $this->idsDe($this->pedirHistorial($this->cuenta, ['desde' => '2027-03-10', 'hasta' => '2027-03-10'])->assertOk()),
        );
    }

    #[TestDox('historial-E03: con solo el primer día o solo el último, el rango queda abierto del otro lado')]
    public function test_historial_e03_con_un_solo_extremo_el_rango_queda_abierto_del_otro_lado(): void
    {
        $this->assertSame(
            [$this->del20->id_publico, $this->del10->id_publico],
            $this->idsDe($this->pedirHistorial($this->cuenta, ['desde' => '2027-03-10'])->assertOk()),
        );
        $this->assertSame(
            [$this->del10->id_publico, $this->del3->id_publico],
            $this->idsDe($this->pedirHistorial($this->cuenta, ['hasta' => '2027-03-10'])->assertOk()),
        );
    }

    #[TestDox('historial-E03: los días se cuentan en la hora de la plaza: una recolección a las 19:00 del 20 de marzo entra el 20, aunque en UTC ya sea el 21, y una a las 19:00 del 9 no entra el 10')]
    public function test_historial_e03_los_dias_se_cuentan_en_la_hora_de_la_plaza(): void
    {
        $deNoche = $this->lavanderiaDeNoche();

        // 19:00 en la hora de la plaza (UTC-6) es la 01:00 del día siguiente en UTC.
        $del9DeNoche = $this->pedidoConfirmadoEl($this->cuenta, '2027-03-09', '19:00', $deNoche);
        $del20DeNoche = $this->pedidoConfirmadoEl($this->cuenta, '2027-03-20', '19:00', $deNoche);
        $this->assertSame('2027-03-10', $del9DeNoche->ventana_inicio->utc()->toDateString());
        $this->assertSame('2027-03-21', $del20DeNoche->ventana_inicio->utc()->toDateString());

        $delRango = $this->pedirHistorial($this->cuenta, ['desde' => '2027-03-10', 'hasta' => '2027-03-20'])->assertOk();
        $this->assertSame(
            [$del20DeNoche->id_publico, $this->del20->id_publico, $this->del10->id_publico],
            $this->idsDe($delRango),
        );

        // El del 20 de noche no es del 21, y el del 9 de noche es del 9.
        $this->assertSame([], $this->idsDe($this->pedirHistorial($this->cuenta, ['desde' => '2027-03-21'])->assertOk()));
        $this->assertSame(
            [$del9DeNoche->id_publico],
            $this->idsDe($this->pedirHistorial($this->cuenta, ['desde' => '2027-03-09', 'hasta' => '2027-03-09'])->assertOk()),
        );
    }

    #[TestDox('historial-E03: cada pedido se cuenta en la hora de su propia plaza: una recolección a las 22:00 del 9 de marzo en una plaza de UTC-8 es del 9, aunque en UTC y en la hora de la otra plaza ya sea el 10')]
    public function test_historial_e03_cada_pedido_se_cuenta_en_la_hora_de_su_propia_plaza(): void
    {
        $tijuana = $this->plazaConAgenda('Plaza de prueba 2', zonaHoraria: 'America/Tijuana', diasHaciaAdelante: 3);
        $this->zonaDePrueba($tijuana, 'Centro 2', $this->cuadrado(-118.0, 10.0));
        $lavanderia = $this->lavanderiaConHorario(
            $tijuana,
            array_map(fn (int $dia): array => [$dia, '21:00', '23:00'], range(self::LUNES, self::DOMINGO)),
        );
        $this->servicioDePrueba($lavanderia, 'estandar', self::PRECIO_POR_KILO);
        $recogida = $this->direccionDe($this->cuenta, [
            'id_plazas' => $tijuana->getKey(),
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -117.5],
        ]);

        // 22:00 del 9 de marzo en la plaza de UTC-8 es las 06:00 del 10 en UTC y las 00:00 del 10 en la primera (UTC-6).
        $pedido = $this->pedidoConfirmadoEl($this->cuenta, '2027-03-09', '22:00', $lavanderia, $recogida);
        $this->assertSame('2027-03-10', $pedido->ventana_inicio->utc()->toDateString());
        $this->assertSame('2027-03-10', $pedido->ventana_inicio->setTimezone(self::ZONA_HORARIA)->toDateString());

        $del9 = $this->pedirHistorial($this->cuenta, ['desde' => '2027-03-09', 'hasta' => '2027-03-09'])->assertOk();
        $this->assertSame([$pedido->id_publico], $this->idsDe($del9));
        $del9->assertJsonPath('pedidos.0.fecha', '2027-03-09');

        $this->assertSame(
            [$this->del10->id_publico],
            $this->idsDe($this->pedirHistorial($this->cuenta, ['desde' => '2027-03-10', 'hasta' => '2027-03-10'])->assertOk()),
        );
    }

    #[TestDox('historial-E03: el filtro no abre los pedidos de otro usuario: con el mismo rango, el del otro no aparece')]
    public function test_historial_e03_el_filtro_no_abre_los_pedidos_de_otro_usuario(): void
    {
        $ajeno = $this->pedidoConfirmadoEl($this->cuentaDeUsuario(), '2027-03-15');

        $respuesta = $this->pedirHistorial($this->cuenta, ['desde' => '2027-03-10', 'hasta' => '2027-03-20'])->assertOk();

        $this->assertSame([$this->del20->id_publico, $this->del10->id_publico], $this->idsDe($respuesta));
        $this->assertStringNotContainsStringIgnoringCase($ajeno->id_publico, $respuesta->getContent());
        $this->assertStringNotContainsStringIgnoringCase($ajeno->folio, $respuesta->getContent());
    }

    #[TestDox('historial-E03: una fecha que no es AAAA-MM-DD, o un último día antes del primero, responde un error de validación en su campo, en español')]
    public function test_historial_e03_una_fecha_mal_escrita_o_un_rango_al_reves_responde_un_error_de_validacion(): void
    {
        foreach (['10/03/2027', '2027-02-30', 'marzo', '2027-03-10T00:00:00'] as $fecha) {
            $this->pedirHistorial($this->cuenta, ['desde' => $fecha])
                ->assertUnprocessable()
                ->assertExactJson([
                    'message' => 'La fecha desde va como AAAA-MM-DD.',
                    'errors' => ['desde' => ['La fecha desde va como AAAA-MM-DD.']],
                ]);

            $this->pedirHistorial($this->cuenta, ['hasta' => $fecha])
                ->assertUnprocessable()
                ->assertExactJson([
                    'message' => 'La fecha hasta va como AAAA-MM-DD.',
                    'errors' => ['hasta' => ['La fecha hasta va como AAAA-MM-DD.']],
                ]);
        }

        $this->pedirHistorial($this->cuenta, ['desde' => '2027-03-20', 'hasta' => '2027-03-10'])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'La fecha hasta no puede ser anterior a la fecha desde.',
                'errors' => ['hasta' => ['La fecha hasta no puede ser anterior a la fecha desde.']],
            ]);

        // Con el primer día mal escrito, el error es solo suyo: el último día no se compara contra él.
        $this->pedirHistorial($this->cuenta, ['desde' => 'marzo', 'hasta' => '2027-03-10'])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'La fecha desde va como AAAA-MM-DD.',
                'errors' => ['desde' => ['La fecha desde va como AAAA-MM-DD.']],
            ]);
    }

    #[TestDox('historial-E04: un usuario sin pedidos en abril que filtra del 1 al 30 de abril recibe la lista vacía')]
    public function test_historial_e04_un_rango_sin_pedidos_deja_la_lista_vacia(): void
    {
        $this->pedirHistorial($this->cuenta, ['desde' => '2027-04-01', 'hasta' => '2027-04-30'])
            ->assertOk()
            ->assertExactJson(['pedidos' => []]);

        // Fuera del filtro, sus pedidos siguen ahí.
        $this->assertCount(3, $this->pedirHistorial($this->cuenta)->assertOk()->json('pedidos'));
    }
}
