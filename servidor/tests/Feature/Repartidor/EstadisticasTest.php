<?php

namespace Tests\Feature\Repartidor;

use App\Models\EstadoPedido;
use App\Models\Repartidor;
use App\Repartidores\RespuestaDelRepartidor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Historial\Soporte\HistorialDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * Las estadísticas del repartidor, GET /api/repartidor/estadisticas: cuántas entregas completó en el día y en la semana,
 * contando solo las suyas (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #5).
 *
 * Cuenta las mismas entregas del historial, por el instante en que él marcó Entregado (design.md, lectura 5). "Del día" y
 * "de la semana" se leen en la zona horaria de la plaza, y la semana va de lunes a domingo (lectura 5). El ingreso total
 * acumulado espera DD-019 y DD-013 (repartidor-E29).
 *
 * El jueves de prueba es el 3 de diciembre de 2026: su semana va del lunes 30 de noviembre al domingo 6 de diciembre. La
 * plaza de prueba está en UTC-6.
 *
 * Escenario: repartidor-E28 · Spec: openspec/changes/repartidor/specs/repartidor/spec.md · Tarea 1.8
 */
class EstadisticasTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use HistorialDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('repartidor-E28: con 2 entregas completadas hoy, 3 en días anteriores de la semana en curso y 1 de la semana pasada, el repartidor ve 2 del día y 5 de la semana, contadas en la hora de la plaza')]
    public function test_repartidor_e28_ve_2_del_dia_y_5_de_la_semana(): void
    {
        $repartidor = $this->repartidorActivo();
        $cuenta = $this->cuentaDeUsuario();

        // La semana pasada: el domingo 29 a las 22:00 de la plaza, que en UTC ya es el lunes 30.
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-11-29'), '2026-11-29 22:00');
        // Días anteriores de la semana en curso. El del miércoles a las 23:30 de la plaza en UTC ya es el jueves.
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-11-30'), '2026-11-30 12:00');
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-12-01'), '2026-12-01 12:00');
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-12-02'), '2026-12-02 23:30');
        // Hoy. La de las 19:30 de la plaza en UTC ya es el viernes 4.
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-12-03'), '2026-12-03 12:30');
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-12-03', '12:00'), '2026-12-03 19:30');

        $this->elRelojEn('2026-12-03 20:00');

        $this->estadisticasDelRepartidor($repartidor)
            ->assertOk()
            ->assertExactJson(['entregas_del_dia' => 2, 'entregas_de_la_semana' => 5]);
    }

    #[TestDox('repartidor-E28: solo cuenta las suyas y completadas: ni las de otro repartidor ni la que él sigue llevando')]
    public function test_repartidor_e28_solo_las_suyas_y_completadas(): void
    {
        $repartidor = $this->repartidorActivo();
        $otro = $this->repartidorActivo();
        $cuenta = $this->cuentaDeUsuario();

        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-12-03'), '2026-12-03 13:00');
        $this->entregaDe($otro, $this->pedidoConfirmadoEl($cuenta, '2026-12-03'), '2026-12-03 13:10');
        $this->entregaDe($otro, $this->pedidoConfirmadoEl($cuenta, '2026-12-03', '12:00'), '2026-12-03 13:20');

        $enCurso = $this->pedidoConfirmadoEl($cuenta, '2026-12-03', '12:00');
        $this->aceptarComoLaLavanderia($enCurso);
        $this->ofrecerA($enCurso, $repartidor);
        app(RespuestaDelRepartidor::class)->aceptar($enCurso, $repartidor);
        $this->comoRepartidor($repartidor)->postJson("/api/repartidor/pedidos/{$enCurso->id_publico}/estado", ['estado' => EstadoPedido::RECOLECTADO])
            ->assertOk();

        $this->elRelojEn('2026-12-03 20:00');

        $this->estadisticasDelRepartidor($repartidor)->assertOk()->assertExactJson(['entregas_del_dia' => 1, 'entregas_de_la_semana' => 1]);
        $this->estadisticasDelRepartidor($otro)->assertOk()->assertExactJson(['entregas_del_dia' => 2, 'entregas_de_la_semana' => 2]);
    }

    #[TestDox('repartidor-E28: la semana va de lunes a domingo en la hora de la plaza: el domingo cuenta en su semana, y el lunes a las 00:30 empieza otra')]
    public function test_repartidor_e28_la_semana_va_de_lunes_a_domingo(): void
    {
        $repartidor = $this->repartidorActivo();
        $cuenta = $this->cuentaDeUsuario();

        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-11-30'), '2026-11-30 09:30');
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-12-06'), '2026-12-06 23:00');

        $this->elRelojEn('2026-12-06 23:30');
        $this->estadisticasDelRepartidor($repartidor)
            ->assertOk()
            ->assertExactJson(['entregas_del_dia' => 1, 'entregas_de_la_semana' => 2]);

        // El lunes a las 00:30 de la plaza (06:30 en UTC) empieza la semana: ninguna es de ella.
        $this->elRelojEn('2026-12-07 00:30');
        $this->estadisticasDelRepartidor($repartidor)
            ->assertOk()
            ->assertExactJson(['entregas_del_dia' => 0, 'entregas_de_la_semana' => 0]);
    }

    #[TestDox('repartidor-E28: el día se cuenta en la zona horaria de la plaza del repartidor, aunque sea otra que la de la plaza de prueba')]
    public function test_repartidor_e28_el_dia_es_el_de_la_zona_horaria_de_su_plaza(): void
    {
        // Una plaza en UTC-8. Su entrega de las 21:30 del jueves es el viernes en UTC y en la hora de la plaza de prueba.
        $tijuana = $this->plazaConAgenda('Plaza de prueba 2', zonaHoraria: 'America/Tijuana', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($tijuana, 'Centro 2', $this->cuadrado(-118.0, 10.0));
        $lejana = $this->otraLavanderia($tijuana);
        $repartidor = $this->repartidorActivo($tijuana);
        $cuenta = $this->cuentaDeUsuario();
        $recogida = $this->direccionDe($cuenta, [
            'id_plazas' => $tijuana->getKey(),
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -117.5],
        ]);

        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-12-03', lavanderia: $lejana, recogida: $recogida), '2026-12-03 21:30');

        // A las 23:45 del jueves en su plaza (07:45 del viernes en UTC), la entrega sigue siendo del día.
        $this->travelTo(CarbonImmutable::parse('2026-12-03 23:45', 'America/Tijuana'));

        $this->estadisticasDelRepartidor($repartidor)
            ->assertOk()
            ->assertExactJson(['entregas_del_dia' => 1, 'entregas_de_la_semana' => 1]);
    }

    #[TestDox('repartidor-E28: sin entregas, los dos en 0; un repartidor pendiente o suspendido recibe el rechazo de la API')]
    public function test_repartidor_e28_sin_entregas_y_sin_acceso(): void
    {
        $this->estadisticasDelRepartidor($this->repartidorActivo())
            ->assertOk()
            ->assertExactJson(['entregas_del_dia' => 0, 'entregas_de_la_semana' => 0]);

        $this->estadisticasDelRepartidor(Repartidor::factory()->pendiente()->create())->assertForbidden()->assertJsonPath('estado', Repartidor::PENDIENTE);
        $this->estadisticasDelRepartidor(Repartidor::factory()->en($this->plaza)->suspendido()->create())
            ->assertForbidden()
            ->assertJsonPath('estado', Repartidor::SUSPENDIDO);
    }

    /**
     * El reloj en ese instante, escrito en la hora de la plaza de prueba de prepararPedido().
     */
    private function elRelojEn(string $instante): void
    {
        $this->travelTo(CarbonImmutable::parse($instante, $this->plaza->zona_horaria));
    }
}
