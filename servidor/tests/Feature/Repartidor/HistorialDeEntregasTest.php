<?php

namespace Tests\Feature\Repartidor;

use App\Models\EstadoPedido;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use App\Repartidores\RespuestaDelRepartidor;
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
 * El historial de entregas del repartidor, GET /api/repartidor/historial: sus entregas completadas, cada una con su folio
 * y la fecha en que la entregó, solo las suyas (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #4; :20, Separación
 * por tipo de usuario).
 *
 * Una entrega es completada cuando el repartidor marca Entregado (design.md, lectura 5): cuenta el pedido en Entregado
 * cuyo paso a Entregado marcó él. La fecha es la de ese paso, en la zona horaria de la plaza. El monto y la calificación
 * recibida esperan DD-019 y DD-013 (repartidor-E26) y DD-052 (repartidor-E27).
 *
 * El jueves de prueba es el 3 de diciembre de 2026; la plaza de prueba está en UTC-6.
 *
 * Escenario: repartidor-E25 · Spec: openspec/changes/repartidor/specs/repartidor/spec.md · Tarea 1.7
 */
class HistorialDeEntregasTest extends TestCase
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

    #[TestDox('repartidor-E25: con dos entregas completadas y una en curso, y otro repartidor con una completada, el primero ve sus dos entregas con su folio y la fecha en que las entregó, de la más reciente a la más antigua; no ve la que sigue en curso ni la del otro')]
    public function test_repartidor_e25_ve_sus_entregas_completadas(): void
    {
        $repartidor = $this->repartidorActivo();
        $otro = $this->repartidorActivo();
        $cuenta = $this->cuentaDeUsuario();

        $martes = $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-12-01'), '2026-12-01 13:00');
        // A las 19:30 del jueves en la plaza, que en UTC ya es el viernes 4.
        $jueves = $this->entregaDe($repartidor, $this->pedidoConfirmadoEl($cuenta, '2026-12-03'), '2026-12-03 19:30');
        $delOtro = $this->entregaDe($otro, $this->pedidoConfirmadoEl($cuenta, '2026-12-02'), '2026-12-02 12:00');

        $enCurso = $this->pedidoConfirmadoEl($cuenta, '2026-12-03', '12:00');
        $this->aceptarComoLaLavanderia($enCurso);
        $this->ofrecerA($enCurso, $repartidor);
        app(RespuestaDelRepartidor::class)->aceptar($enCurso, $repartidor);
        $this->comoRepartidor($repartidor)->postJson("/api/repartidor/pedidos/{$enCurso->id_publico}/estado", ['estado' => EstadoPedido::RECOLECTADO])
            ->assertOk();

        $this->historialDelRepartidor($repartidor)
            ->assertOk()
            ->assertExactJson(['pedidos' => [
                [
                    'id_publico' => $jueves->id_publico,
                    'folio' => $jueves->folio,
                    'entregado_en' => '2026-12-04T01:30:00Z',
                    'fecha' => '2026-12-03',
                ],
                [
                    'id_publico' => $martes->id_publico,
                    'folio' => $martes->folio,
                    'entregado_en' => '2026-12-01T19:00:00Z',
                    'fecha' => '2026-12-01',
                ],
            ]]);

        // El otro, solo la suya.
        $this->historialDelRepartidor($otro)
            ->assertOk()
            ->assertJsonCount(1, 'pedidos')
            ->assertJsonPath('pedidos.0.id_publico', $delOtro->id_publico);

        // La entrega del otro tampoco se le entrega por su identificador.
        $this->pedidoComoRepartidor($repartidor, $delOtro->id_publico)->assertNotFound();
        $this->pedidoComoRepartidor($repartidor, $jueves->id_publico)
            ->assertOk()
            ->assertJsonPath('pedido.estado.codigo', EstadoPedido::ENTREGADO);
    }

    #[TestDox('repartidor-E25: sin entregas completadas, el historial llega vacío; sin el rol de repartidor, o pendiente, la API lo rechaza')]
    public function test_repartidor_e25_vacio_y_sin_acceso(): void
    {
        $this->historialDelRepartidor($this->repartidorActivo())->assertOk()->assertExactJson(['pedidos' => []]);

        foreach ([Rol::USUARIO, Rol::LAVANDERIA] as $rol) {
            $this->comoRepartidor(Usuario::factory()->conRol($rol)->create())->getJson('/api/repartidor/historial')
                ->assertForbidden()
                ->assertJsonMissingPath('pedidos');
        }

        $this->historialDelRepartidor(Repartidor::factory()->pendiente()->create())
            ->assertForbidden()
            ->assertJsonPath('estado', Repartidor::PENDIENTE);
    }
}
