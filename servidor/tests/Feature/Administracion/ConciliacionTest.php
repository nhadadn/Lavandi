<?php

namespace Tests\Feature\Administracion;

use App\Autorizacion\Autorizacion;
use App\Filament\Pages\Conciliacion;
use App\Finanzas\ResultadoDeConciliacion;
use App\Models\MovimientoPasarela;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Administracion\Soporte\PagosDelPeriodoDePrueba;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La conciliación con la pasarela en los reportes financieros del panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3,
 * Módulo D #6, "conciliación con Conekta"; openspec/changes/administracion/, tareas 4.4 y 4.5).
 *
 * Cuando la administración concilia un periodo, el servidor lee de la pasarela, por la interfaz neutral del cobro, los
 * pagos que acreditó en ese periodo, los guarda en movimientos_pasarela, uno por orden, y los compara con los pagos que
 * registró la plataforma (segunda adenda del 2026-11-22 de ADR-0010, decisiones 6 y 7, que cierran DD-112). La pasarela
 * es la simulada, con sus propios libros: cada aviso de aprobado que manda es un pago que acreditó.
 *
 * Todo es ficticio: los montos, las fechas y las órdenes son de prueba.
 *
 * Escenarios: administracion-E20 y administracion-E21 · Spec: openspec/changes/administracion/specs/administracion/
 * spec.md · Tareas 4.4 y 4.5
 */
class ConciliacionTest extends TestCase
{
    use AgendaDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PagosDelPeriodoDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Marzo de 2027. */
    private const MARZO = ['desde' => '2027-03-01', 'hasta' => '2027-03-31'];

    /** Los roles que no son de administración. */
    private const OTROS_ROLES = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('administracion-E20: con tres pedidos con pago confirmado en el periodo y los mismos tres movimientos en la pasarela simulada, la administración concilia el periodo y el reporte marca los tres como conciliados')]
    public function test_administracion_e20_periodo_sin_diferencias(): void
    {
        $pedidos = [
            $this->pagadoEl('2027-03-05 10:00:00', '150.00'),
            $this->pagadoEl('2027-03-12 10:00:00', '200.00'),
            $this->pagadoEl('2027-03-20 10:00:00', '250.00'),
        ];
        $this->comoAdministracion();

        $filas = $this->filas($this->conciliar(self::MARZO));

        $this->assertCount(3, $filas);

        foreach ($pedidos as $pedido) {
            $cobro = $this->cobroDe($pedido);
            $fila = $filas["simulada|{$cobro->id_pasarela_orden}"];

            $this->assertSame(ResultadoDeConciliacion::Conciliado, $fila['resultado']);
            $this->assertSame($pedido->folio, $fila['folio']);
            $this->assertSame($this->plaza->nombre, $fila['plaza']);
            $this->assertSame((string) $cobro->monto, $fila['monto_plataforma']);
            $this->assertSame((string) $cobro->monto, $fila['monto_pasarela']);
        }

        // De la más antigua a la más nueva, con la fecha del pago en la hora de la plaza.
        $this->assertSame(['05/03/2027 10:00', '12/03/2027 10:00', '20/03/2027 10:00'], array_column(array_values($filas), 'fecha'));
    }

    #[TestDox('administracion-E20: la página muestra el resultado de cada uno con su texto y el resumen del periodo')]
    public function test_administracion_e20_la_pagina_dice_el_resultado_con_su_texto(): void
    {
        $pedido = $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $this->comoAdministracion();

        $this->conciliar(self::MARZO)
            ->assertSee('Del 01/03/2027 al 31/03/2027')
            ->assertSee('Conciliado: 1.')
            ->assertSee($pedido->folio)
            ->assertSee($this->cobroDe($pedido)->id_pasarela_orden)
            ->assertSee('$150.00')
            ->assertTableColumnFormattedStateSet('resultado', 'Conciliado', "simulada|{$this->cobroDe($pedido)->id_pasarela_orden}");
    }

    #[TestDox('administracion-E20: los movimientos se leen de la pasarela, con la llave de la configuración, solo cuando la administración concilia; se guardan una fila por orden y leer dos veces el periodo no repite ninguno')]
    public function test_administracion_e20_se_leen_al_conciliar_y_no_se_repiten(): void
    {
        $pedidos = [$this->pagadoEl('2027-03-05 10:00:00', '150.00'), $this->pagadoEl('2027-03-12 10:00:00', '200.00')];

        // Pagar no escribe nada en movimientos_pasarela: los movimientos son de la pasarela hasta que se concilia.
        $this->assertSame(0, MovimientoPasarela::query()->count());
        $this->assertSame([], $this->lecturasDeLaPasarela());

        $this->comoAdministracion();
        $pagina = $this->conciliar(self::MARZO);

        $lecturas = $this->lecturasDeLaPasarela();
        $this->assertCount(1, $lecturas);
        $this->assertSame('Bearer '.config('pasarela.simulada.llave'), $lecturas[0]['autorizacion']);

        $this->assertSame(2, MovimientoPasarela::query()->count());

        foreach ($pedidos as $pedido) {
            $cobro = $this->cobroDe($pedido);
            $movimiento = MovimientoPasarela::query()->where('id_pasarela_orden', $cobro->id_pasarela_orden)->sole();

            $this->assertSame('simulada', $movimiento->pasarela);
            $this->assertSame((string) $cobro->monto, $movimiento->monto);
            $this->assertNull($movimiento->comision);
            $this->assertTrue($cobro->pagado_en->equalTo($movimiento->pagado_en));
        }

        // Otra vez el mismo periodo: la pasarela se vuelve a leer, y los movimientos no se repiten.
        $pagina->callAction('conciliar', data: self::MARZO)->assertHasNoActionErrors();

        $this->assertCount(2, $this->lecturasDeLaPasarela());
        $this->assertSame(2, MovimientoPasarela::query()->count());
        $this->assertCount(2, $this->filas($pagina));
    }

    #[TestDox('administracion-E20: la comisión que trae la pasarela se guarda con su movimiento, sin mostrarla: quién la absorbe espera DD-013 (administracion-E22)')]
    public function test_administracion_e20_la_comision_se_guarda_sin_mostrarla(): void
    {
        $cobro = $this->cobroDe($this->pagadoEl('2027-03-05 10:00:00', '150.00'));
        $this->simulada()->acreditar($cobro->id_pasarela_orden, '150.00', $cobro->pagado_en, comision: '6.30');
        $this->comoAdministracion();

        $this->conciliar(self::MARZO)->assertDontSee('6.30');

        $this->assertSame('6.30', MovimientoPasarela::query()->sole()->comision);
    }

    #[TestDox('administracion-E20: la administración abre la conciliación en el grupo de reportes financieros; antes de conciliar, la tabla no muestra nada ni lee la pasarela')]
    public function test_administracion_e20_antes_de_conciliar_no_se_muestra_nada(): void
    {
        $pedido = $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $administracion = $this->comoAdministracion();

        $this->assertTrue(app(Autorizacion::class)->puedeVerLosReportesFinancieros($administracion));
        $this->get('/admin/conciliacion')->assertOk()->assertSee('Conciliación con la pasarela')->assertDontSee($pedido->folio);
        $this->get('/admin')->assertOk()->assertSee('Conciliación');

        $pagina = Livewire::test(Conciliacion::class)
            ->assertSee('Elige un periodo y concilia para leer los pagos de la pasarela.');
        $this->assertSame([], $this->filas($pagina));
        $this->assertSame([], $this->lecturasDeLaPasarela());
    }

    #[TestDox('administracion-E20: sin pasarela disponible, la conciliación lo dice y no guarda nada')]
    public function test_administracion_e20_sin_pasarela_disponible_no_se_concilia(): void
    {
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $this->comoAdministracion();
        config(['pasarela.simulada.llave' => '']);

        $pagina = Livewire::test(Conciliacion::class)
            ->callAction('conciliar', data: self::MARZO)
            ->assertNotified('No se pudo leer la pasarela');
        $this->assertSame([], $this->filas($pagina));

        $this->assertSame(0, MovimientoPasarela::query()->count());
    }

    #[TestDox('administracion-E20: un periodo al revés se rechaza con su mensaje y no lee la pasarela')]
    public function test_administracion_e20_un_periodo_al_reves_se_rechaza(): void
    {
        $this->comoAdministracion();

        Livewire::test(Conciliacion::class)
            ->callAction('conciliar', data: ['desde' => '2027-03-31', 'hasta' => '2027-03-01'])
            ->assertHasActionErrors(['hasta' => 'after_or_equal']);

        $this->assertSame([], $this->lecturasDeLaPasarela());
    }

    #[TestDox('administracion-E20: solo se concilia con la acción, que pregunta al componente de autorización: el navegador no puede llamar directo al método que lee la pasarela')]
    public function test_administracion_e20_solo_se_concilia_con_la_accion(): void
    {
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $this->comoAdministracion();

        try {
            Livewire::test(Conciliacion::class)->call('conciliar', self::MARZO);
            $this->fail('El método que lee la pasarela no debe poder llamarse desde el navegador.');
        } catch (MethodNotFoundException) {
            $this->assertSame([], $this->lecturasDeLaPasarela());
            $this->assertSame(0, MovimientoPasarela::query()->count());
        }
    }

    #[TestDox('administracion-E20: una cuenta de usuario, de socio lavandería o de socio repartidor no abre la conciliación ni puede conciliar, y no ve ningún pago')]
    public function test_administracion_e20_otra_cuenta_no_concilia(): void
    {
        $pedido = $this->pagadoEl('2027-03-05 10:00:00', '150.00');

        foreach (self::OTROS_ROLES as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();
            $this->actingAs($cuenta, 'web');

            $this->assertFalse(app(Autorizacion::class)->puedeVerLosReportesFinancieros($cuenta), $rol);
            $respuesta = $this->get('/admin/conciliacion')->assertForbidden();
            $this->assertStringNotContainsString($pedido->folio, $respuesta->getContent());
            Livewire::test(Conciliacion::class)->assertForbidden();
        }

        $this->assertSame([], $this->lecturasDeLaPasarela());
        $this->assertSame(0, MovimientoPasarela::query()->count());
    }

    #[TestDox('administracion-E21: con un movimiento de la pasarela sin pedido, un pedido pagado sin movimiento y un pedido cuyo monto no coincide con su movimiento (simulado), la administración concilia el periodo y el reporte señala cada uno con su tipo de diferencia')]
    public function test_administracion_e21_periodo_con_diferencias(): void
    {
        $conciliado = $this->cobroDe($this->pagadoEl('2027-03-05 10:00:00', '100.00'));

        // La pasarela acreditó un pago cuya orden no es de ningún pedido de la plataforma.
        $this->simulada()->acreditar('orden-de-otro-sistema', '80.00', CarbonImmutable::parse('2027-03-10 16:00:00', 'UTC'));

        // La plataforma tiene el pedido pagado y la pasarela no tiene su movimiento.
        $sinMovimiento = $this->cobroDe($this->pagadoEl('2027-03-12 10:00:00', '120.00'));
        $this->simulada()->quitarPago($sinMovimiento->id_pasarela_orden);

        // La misma orden, con 150 en la plataforma y 175 en la pasarela.
        $otroMonto = $this->cobroDe($this->pagadoEl('2027-03-20 10:00:00', '150.00'));
        $this->simulada()->acreditar($otroMonto->id_pasarela_orden, '175.00', $otroMonto->pagado_en);

        $this->comoAdministracion();
        $pagina = $this->conciliar(self::MARZO);
        $filas = $this->filas($pagina);

        $this->assertCount(4, $filas);
        $this->assertSame(ResultadoDeConciliacion::Conciliado, $filas["simulada|{$conciliado->id_pasarela_orden}"]['resultado']);

        $this->assertSame([
            'resultado' => ResultadoDeConciliacion::MovimientoSinPedido,
            'orden' => 'orden-de-otro-sistema',
            'folio' => null,
            'plaza' => null,
            'fecha' => '10/03/2027 16:00',
            'monto_plataforma' => null,
            'monto_pasarela' => '80.00',
        ], $filas['simulada|orden-de-otro-sistema']);

        $this->assertSame([
            'resultado' => ResultadoDeConciliacion::PedidoSinMovimiento,
            'orden' => $sinMovimiento->id_pasarela_orden,
            'folio' => $sinMovimiento->pedido->folio,
            'plaza' => $this->plaza->nombre,
            'fecha' => '12/03/2027 10:00',
            'monto_plataforma' => '120.00',
            'monto_pasarela' => null,
        ], $filas["simulada|{$sinMovimiento->id_pasarela_orden}"]);

        $this->assertSame([
            'resultado' => ResultadoDeConciliacion::MontoDistinto,
            'orden' => $otroMonto->id_pasarela_orden,
            'folio' => $otroMonto->pedido->folio,
            'plaza' => $this->plaza->nombre,
            'fecha' => '20/03/2027 10:00',
            'monto_plataforma' => '150.00',
            'monto_pasarela' => '175.00',
        ], $filas["simulada|{$otroMonto->id_pasarela_orden}"]);

        // Cada diferencia con su texto, no solo con su color, y el resumen del periodo.
        $pagina->assertSee('Conciliado: 1. Movimiento sin pedido: 1. Pedido pagado sin movimiento: 1. Monto que no coincide: 1.')
            ->assertTableColumnFormattedStateSet('resultado', 'Movimiento sin pedido', 'simulada|orden-de-otro-sistema')
            ->assertTableColumnFormattedStateSet('resultado', 'Pedido pagado sin movimiento', "simulada|{$sinMovimiento->id_pasarela_orden}")
            ->assertTableColumnFormattedStateSet('resultado', 'Monto que no coincide', "simulada|{$otroMonto->id_pasarela_orden}")
            ->assertSee(['$150.00', '$175.00', '$80.00', 'Sin pedido']);
    }

    #[TestDox('administracion-E21: un movimiento cuya orden es de un cobro que la plataforma no tiene como pagado, porque su aviso no llegó, es un movimiento sin pedido: no hay pedido pagado con el que cuadre')]
    public function test_administracion_e21_el_movimiento_de_un_cobro_sin_pagar_es_un_movimiento_sin_pedido(): void
    {
        $sinAviso = $this->ordenSinConfirmarEl('2027-03-08 10:00:00', '90.00');
        $this->simulada()->acreditar($sinAviso->id_pasarela_orden, '90.00', CarbonImmutable::parse('2027-03-08 16:05:00', 'UTC'));
        $this->comoAdministracion();

        $fila = $this->filas($this->conciliar(self::MARZO))["simulada|{$sinAviso->id_pasarela_orden}"];

        $this->assertSame(ResultadoDeConciliacion::MovimientoSinPedido, $fila['resultado']);
        $this->assertNull($fila['folio']);
        $this->assertNull($fila['plaza']);
        $this->assertSame('90.00', $fila['monto_pasarela']);
    }

    #[TestDox('administracion-E21: lo de fuera del periodo no se señala: ni un pago de abril, ni un movimiento sin pedido de abril')]
    public function test_administracion_e21_lo_de_fuera_del_periodo_no_aparece(): void
    {
        $marzo = $this->cobroDe($this->pagadoEl('2027-03-05 10:00:00', '100.00'));
        $abril = $this->cobroDe($this->pagadoEl('2027-04-02 10:00:00', '100.00'));
        $this->simulada()->quitarPago($abril->id_pasarela_orden);
        $this->simulada()->acreditar('orden-de-abril', '80.00', CarbonImmutable::parse('2027-04-02 12:00:00', 'UTC'));
        $this->comoAdministracion();

        $this->assertSame(["simulada|{$marzo->id_pasarela_orden}"], array_keys($this->filas($this->conciliar(self::MARZO))));
    }

    #[TestDox('administracion-E21: un pago cuadra con su movimiento aunque la pasarela lo fechara un poco antes de que llegara su aviso: cuenta en el periodo del pago, sin diferencias en ninguno de los dos')]
    public function test_administracion_e21_un_pago_cuadra_aunque_la_pasarela_lo_feche_antes(): void
    {
        // El aviso llega a las 00:00:30 del 1 de abril en la hora de la plaza; la pasarela fechó el pago 40 s antes.
        $cobro = $this->cobroDe($this->pagadoEl('2027-04-01 00:00:30', '100.00'));
        $this->simulada()->acreditar($cobro->id_pasarela_orden, '100.00', CarbonImmutable::parse('2027-03-31 23:59:50', self::ZONA_HORARIA));
        $this->comoAdministracion();

        $this->assertSame([], $this->filas($this->conciliar(self::MARZO)));

        $abril = $this->filas($this->conciliar(['desde' => '2027-04-01', 'hasta' => '2027-04-30']));
        $this->assertSame([ResultadoDeConciliacion::Conciliado], array_column(array_values($abril), 'resultado'));
    }

    /**
     * La administración concilia el periodo en la página.
     *
     * @param  array{desde: string, hasta: string}  $periodo
     */
    private function conciliar(array $periodo): Testable
    {
        return Livewire::test(Conciliacion::class)
            ->callAction('conciliar', data: $periodo)
            ->assertHasNoActionErrors();
    }

    /**
     * Las filas de la tabla de la página, por su clave, sin la que les agrega Filament.
     *
     * @return array<string, array<string, mixed>>
     */
    private function filas(Testable $pagina): array
    {
        return $pagina->instance()->getTableRecords()
            ->map(fn (array $fila): array => array_diff_key($fila, ['__key' => true]))
            ->all();
    }

    /**
     * Las veces que el servidor le pidió a la pasarela simulada sus pagos del periodo.
     *
     * @return list<array<string, string>>
     */
    private function lecturasDeLaPasarela(): array
    {
        return array_values(array_filter(
            $this->simulada()->peticiones(),
            fn (array $peticion): bool => $peticion['operacion'] === 'pagos_del_periodo',
        ));
    }
}
