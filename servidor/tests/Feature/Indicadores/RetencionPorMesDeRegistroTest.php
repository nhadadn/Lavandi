<?php

namespace Tests\Feature\Indicadores;

use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Indicadores\RetencionTabla;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Los usuarios que vuelven a pedir, por mes de registro (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría
 * Usuarios; NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5, retención): una fila por mes, con cuántos usuarios de ese
 * mes hicieron su primer pedido y qué porcentaje de ellos volvió a pedir, hasta hoy (openspec/changes/indicadores/
 * design.md, lectura 3).
 *
 * El mes de registro es el de usuarios.created_at (pregunta 7), leído en la zona horaria de referencia de lo que no
 * tiene plaza (app.zona_horaria_sin_plaza), porque la cuenta no tiene plaza (pregunta 8; indicadores-E24). Cuenta como
 * pedido el que llegó a Confirmado (lectura 1).
 *
 * Escenarios: indicadores-E08 e indicadores-E24 · Spec: openspec/changes/indicadores/specs/indicadores/spec.md ·
 * Tareas 2.3 y 6.4
 */
class RetencionPorMesDeRegistroTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('indicadores-E08: de cuatro usuarios de enero, dos pidieron y uno de esos dos volvió a pedir: enero da 2 con primer pedido y 50 %')]
    public function test_indicadores_e08_enero_da_2_con_primer_pedido_y_50_por_ciento(): void
    {
        $this->sembrarElEscenario();
        $this->comoAdministracion();

        $this->widget(RetencionTabla::class, $this->filtros('2026-01-01', '2026-02-28'))
            ->assertTableColumnStateSet('mes', 'enero 2026', '2026-01-01')
            ->assertTableColumnStateSet('con_primer_pedido', 2, '2026-01-01')
            ->assertTableColumnStateSet('porcentaje', 50.0, '2026-01-01')
            ->assertTableColumnFormattedStateSet('porcentaje', '50 %', '2026-01-01');
    }

    #[TestDox('indicadores-E08: un mes cuyos usuarios no han pedido da 0 y no muestra porcentaje')]
    public function test_indicadores_e08_un_mes_sin_pedidos_da_0_y_no_muestra_porcentaje(): void
    {
        $this->sembrarElEscenario();
        $this->comoAdministracion();

        $tabla = $this->widget(RetencionTabla::class, $this->filtros('2026-01-01', '2026-02-28'));

        $this->assertSame(['2026-01-01', '2026-02-01'], $this->clavesDeLaTabla($tabla));
        $tabla->assertTableColumnStateSet('mes', 'febrero 2026', '2026-02-01')
            ->assertTableColumnStateSet('con_primer_pedido', 0, '2026-02-01')
            ->assertTableColumnStateSet('porcentaje', null, '2026-02-01')
            ->assertTableColumnFormattedStateNotSet('porcentaje', '0 %', '2026-02-01');
    }

    #[TestDox('indicadores-E08: el pedido que nunca se pagó no es primer pedido, y volver a pedir cuenta hasta hoy, fuera del rango')]
    public function test_indicadores_e08_el_pedido_sin_pagar_no_cuenta_y_volver_a_pedir_cuenta_hasta_hoy(): void
    {
        $this->travelTo('2026-03-10 12:00:00');
        $soloSinPagar = Usuario::factory()->conRol('usuario')->create();
        $volvioEnDiciembre = Usuario::factory()->conRol('usuario')->create();

        $this->pedidoSinPagarEl('2026-03-12 10:00:00', $soloSinPagar);
        $this->pedidoConfirmadoEl('2026-03-12 11:00:00', $volvioEnDiciembre);
        $this->pedidoConfirmadoEl('2026-12-01 10:00:00', $volvioEnDiciembre);
        $this->comoAdministracion();

        // Solo marzo en el rango: el segundo pedido, de diciembre, cuenta igual.
        $this->widget(RetencionTabla::class, $this->filtros('2026-03-01', '2026-03-31'))
            ->assertTableColumnStateSet('con_primer_pedido', 1, '2026-03-01')
            ->assertTableColumnStateSet('porcentaje', 100.0, '2026-03-01');
    }

    #[TestDox('indicadores-E08: el porcentaje se redondea a un decimal, y la tabla está en la página de indicadores')]
    public function test_indicadores_e08_el_porcentaje_se_redondea_a_un_decimal_y_esta_en_la_pagina(): void
    {
        $this->travelTo('2026-04-05 12:00:00');
        $cuentas = Usuario::factory()->count(3)->conRol('usuario')->create();

        foreach ($cuentas as $indice => $cuenta) {
            $this->pedidoConfirmadoEl('2026-04-1'.$indice.' 10:00:00', $cuenta);
        }

        $this->pedidoConfirmadoEl('2026-04-20 10:00:00', $cuentas->first());
        $this->comoAdministracion();

        $this->widget(RetencionTabla::class, $this->filtros('2026-04-01', '2026-04-30'))
            ->assertSee('Usuarios que vuelven a pedir')
            ->assertTableColumnStateSet('con_primer_pedido', 3, '2026-04-01')
            ->assertTableColumnStateSet('porcentaje', 33.3, '2026-04-01')
            ->assertTableColumnFormattedStateSet('porcentaje', '33.3 %', '2026-04-01');

        $this->assertContains(RetencionTabla::class, (new Indicadores)->getWidgets());
    }

    #[TestDox('indicadores-E24: con la zona horaria de referencia de la configuración en UTC-6, una cuenta registrada a las 19:00 del 31 de marzo en esa zona, ya 1 de abril en UTC, que hizo su primer pedido cuenta en marzo')]
    public function test_indicadores_e24_la_cuenta_registrada_la_noche_del_31_de_marzo_cuenta_en_marzo(): void
    {
        config(['app.zona_horaria_sin_plaza' => self::ZONA_HORARIA]);
        $this->travelTo(CarbonImmutable::parse('2027-03-31 19:00:00', self::ZONA_HORARIA));
        $cuenta = Usuario::factory()->conRol('usuario')->create();
        $this->assertSame('2027-04-01 01:00', $cuenta->created_at->utc()->format('Y-m-d H:i'));

        $this->pedidoConfirmadoEl('2027-04-02 10:00:00', $cuenta);
        $this->comoAdministracion();

        $this->widget(RetencionTabla::class, $this->filtros('2027-03-01', '2027-04-30'))
            ->assertTableColumnStateSet('con_primer_pedido', 1, '2027-03-01')
            ->assertTableColumnStateSet('con_primer_pedido', 0, '2027-04-01');
    }

    /**
     * Cuatro usuarios registrados en enero de 2026, de los cuales dos hicieron un pedido y uno de esos dos hizo otro, y
     * dos usuarios registrados en febrero que no han pedido.
     */
    private function sembrarElEscenario(): void
    {
        $this->travelTo('2026-01-10 12:00:00');
        [$volvio, $unaVez] = Usuario::factory()->count(4)->conRol('usuario')->create()->all();

        $this->travelTo('2026-02-10 12:00:00');
        Usuario::factory()->count(2)->conRol('usuario')->create();

        $this->pedidoConfirmadoEl('2026-01-20 10:00:00', $volvio);
        $this->pedidoConfirmadoEl('2026-02-05 10:00:00', $volvio);
        $this->pedidoConfirmadoEl('2026-01-25 10:00:00', $unaVez);
    }
}
