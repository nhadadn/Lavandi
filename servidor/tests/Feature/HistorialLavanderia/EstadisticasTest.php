<?php

namespace Tests\Feature\HistorialLavanderia;

use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Historial\Soporte\HistorialDePrueba;
use Tests\Feature\HistorialLavanderia\Soporte\HistorialDeLaLavanderiaDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las estadísticas de la lavandería, GET /api/lavanderia/estadisticas: sus pedidos del día y de la semana, contando solo
 * los suyos (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #5; NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3,
 * Separación por tipo de usuario).
 *
 * Cuenta pedidos completados, los que pasaron a Entregado, por el instante de ese paso (design.md, decisiones 1 y 2). "Del
 * día" y "de la semana" se leen en la zona horaria de la plaza de cada pedido, y la semana va de lunes a domingo
 * (decisión 3). El ingreso y la calificación promedio esperan DD-013, DD-020, DD-012 y DD-052 (E07 y E08).
 *
 * El jueves de prueba es el 3 de diciembre de 2026: su semana va del lunes 30 de noviembre al domingo 6 de diciembre. La
 * plaza de prueba está en UTC-6.
 *
 * Escenarios: historial-lavanderia-E05 e historial-lavanderia-E06 · Spec:
 * openspec/changes/historial-de-la-lavanderia/specs/historial-lavanderia/spec.md · Tarea 1.2
 */
class EstadisticasTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use HistorialDeLaLavanderiaDePrueba;
    use HistorialDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('historial-lavanderia-E05: con 2 pedidos entregados hoy, 3 en días anteriores de la semana en curso y 1 de la semana pasada, la lavandería ve 2 del día y 5 de la semana, contados en la hora de la plaza')]
    public function test_historial_lavanderia_e05_ve_2_del_dia_y_5_de_la_semana(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        // La semana pasada: el domingo 29 a las 22:00 de la plaza, que en UTC ya es el lunes 30.
        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-11-29'), '2026-11-29 22:00');
        // Días anteriores de la semana en curso. El del miércoles a las 23:30 de la plaza en UTC ya es el jueves.
        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-11-30'), '2026-11-30 12:00');
        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-01'), '2026-12-01 12:00');
        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-02'), '2026-12-02 23:30');
        // Hoy. El de las 19:30 de la plaza en UTC ya es el viernes 4.
        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-03'), '2026-12-03 12:30');
        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-03', '12:00'), '2026-12-03 19:30');

        $this->elRelojEn('2026-12-03 20:00');

        $this->estadisticasDeLaLavanderia($this->lavanderia->administrador)
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 2, 'pedidos_de_la_semana' => 5]);
    }

    #[TestDox('historial-lavanderia-E05: la semana va de lunes a domingo en la hora de la plaza: el domingo cuenta en su semana, y el lunes a las 00:30 empieza otra')]
    public function test_historial_lavanderia_e05_la_semana_va_de_lunes_a_domingo(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-11-30'), '2026-11-30 09:30');
        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-06'), '2026-12-06 23:00');

        // El domingo por la noche, los dos son de la semana y uno es del día.
        $this->elRelojEn('2026-12-06 23:30');
        $this->estadisticasDeLaLavanderia($this->lavanderia->administrador)
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 1, 'pedidos_de_la_semana' => 2]);

        // El lunes a las 00:30 de la plaza (06:30 en UTC) empieza la semana: ninguno es de ella.
        $this->elRelojEn('2026-12-07 00:30');
        $this->estadisticasDeLaLavanderia($this->lavanderia->administrador)
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 0, 'pedidos_de_la_semana' => 0]);
    }

    #[TestDox('historial-lavanderia-E05: el día se cuenta en la zona horaria de la plaza de la lavandería, aunque sea otra que la de la plaza de prueba')]
    public function test_historial_lavanderia_e05_el_dia_es_el_de_la_zona_horaria_de_su_plaza(): void
    {
        // Una plaza en UTC-8. Su entrega de las 21:30 del jueves es el viernes en UTC y en la hora de la plaza de prueba.
        $tijuana = $this->plazaConAgenda('Plaza de prueba 2', zonaHoraria: 'America/Tijuana', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($tijuana, 'Centro 2', $this->cuadrado(-118.0, 10.0));
        $lejana = $this->otraLavanderia($tijuana);
        $cuenta = $this->cuentaDeUsuario();
        $recogida = $this->direccionDe($cuenta, [
            'id_plazas' => $tijuana->getKey(),
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -117.5],
        ]);

        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-03', lavanderia: $lejana, recogida: $recogida), '2026-12-03 21:30');

        // A las 23:45 del jueves en su plaza (07:45 del viernes en UTC), la entrega sigue siendo del día.
        $this->travelTo(CarbonImmutable::parse('2026-12-03 23:45', 'America/Tijuana'));

        $this->estadisticasDeLaLavanderia($lejana->administrador)
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 1, 'pedidos_de_la_semana' => 1]);
    }

    #[TestDox('historial-lavanderia-E05: solo cuentan los entregados: no el que va en camino, el rechazado ni el que espera su pago')]
    public function test_historial_lavanderia_e05_solo_cuentan_los_entregados(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-03', '09:00'), '2026-12-03 12:00');
        $this->pedidoPorPagarEl($cuenta, '2026-12-03', '10:00');
        $this->rechazarComoLaLavanderia($this->pedidoConfirmadoEl($cuenta, '2026-12-03', '11:00'));
        $enCamino = $this->pedidoConfirmadoEl($cuenta, '2026-12-03', '12:00');
        $this->aceptarComoLaLavanderia($enCamino);
        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA] as $boton) {
            $this->marcarComoLaLavanderia($enCamino, $boton);
        }

        $this->elRelojEn('2026-12-03 20:00');

        $this->estadisticasDeLaLavanderia($this->lavanderia->administrador)
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 1, 'pedidos_de_la_semana' => 1]);
    }

    #[TestDox('historial-lavanderia-E05: la lavandería sin pedidos entregados ve ceros')]
    public function test_historial_lavanderia_e05_sin_entregados_ve_ceros(): void
    {
        $this->estadisticasDeLaLavanderia($this->lavanderia->administrador)
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 0, 'pedidos_de_la_semana' => 0]);
    }

    #[TestDox('historial-lavanderia-E06: los conteos de la lavandería no incluyen ningún pedido de otra lavandería de su plaza ni de otra plaza entregado hoy; las otras sí ven los suyos')]
    public function test_historial_lavanderia_e06_solo_cuenta_sus_propios_pedidos(): void
    {
        $this->entregarEl($this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario()), '2026-12-03 12:30');

        $this->elRelojEn('2026-12-03 09:30');
        $vecina = $this->otraLavanderia();
        $this->entregarDosVecesEn($vecina, $this->cuentaDeUsuario());

        $this->elRelojEn('2026-12-03 09:30');
        $otraPlaza = $this->plazaDos();
        $lejana = $this->otraLavanderia($otraPlaza);
        $this->entregarEl(
            $this->pedidoConfirmadoEn($lejana, $this->cuentaDeUsuario(), latitud: 10.5, longitud: -117.5),
            '2026-12-03 12:50',
        );

        $this->elRelojEn('2026-12-03 20:00');

        $this->estadisticasDeLaLavanderia($this->lavanderia->administrador)
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 1, 'pedidos_de_la_semana' => 1]);
        $this->estadisticasDeLaLavanderia($vecina->administrador)
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 2, 'pedidos_de_la_semana' => 2]);
        $this->estadisticasDeLaLavanderia($lejana->administrador)
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 1, 'pedidos_de_la_semana' => 1]);
    }

    #[TestDox('historial-lavanderia-E06: sin sesión, o con la cuenta de un usuario, las estadísticas no se entregan')]
    public function test_historial_lavanderia_e06_sin_sesion_o_con_la_cuenta_de_un_usuario_no_se_entregan(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $this->entregarEl($this->pedidoConfirmadoEn($this->lavanderia, $cuenta), '2026-12-03 12:30');

        $this->pedirSinSesion('/api/lavanderia/estadisticas')->assertUnauthorized();
        $this->estadisticasDeLaLavanderia($cuenta)
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta no tiene acceso.')
            ->assertJsonMissingPath('pedidos_del_dia');
    }

    /**
     * Dos pedidos de la cuenta a esa lavandería, entregados el jueves de prueba a las 12:40 y a las 12:45.
     */
    private function entregarDosVecesEn(Lavanderia $lavanderia, Usuario $cuenta): void
    {
        $primero = $this->pedidoConfirmadoEn($lavanderia, $cuenta, '10:00');
        $segundo = $this->pedidoConfirmadoEn($lavanderia, $cuenta, '11:00');

        foreach ([[$primero, '12:40'], [$segundo, '12:45']] as [$pedido, $hora]) {
            /** @var Pedido $pedido */
            $this->entregarEl($pedido, self::JUEVES_DE_PRUEBA.' '.$hora);
        }
    }

    /**
     * Otra plaza de prueba con su agenda y su zona al este de la de prepararPedido(), en el océano Pacífico.
     */
    private function plazaDos(): Plaza
    {
        $plaza = $this->plazaConAgenda('Plaza de prueba 2', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($plaza, 'Centro 2', $this->cuadrado(-118.0, 10.0));

        return $plaza;
    }
}
