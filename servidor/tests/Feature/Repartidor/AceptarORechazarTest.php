<?php

namespace Tests\Feature\Repartidor;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\Repartidor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * Aceptar o rechazar con motivo el pedido asignado, POST /api/repartidor/pedidos/{id_publico}/aceptar y .../rechazar
 * (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #2, "Aceptar o rechazar con motivo").
 *
 * La respuesta vive en pedido_asignaciones: aceptar deja la oferta aceptada con su hora y escribe pedidos.id_repartidores;
 * rechazar la deja rechazada con su motivo y su hora, y el pedido deja de estar asignado a él. Ninguna de las dos cambia
 * el estado del pedido. Qué pasa después con el pedido rechazado espera DD-014 (repartidor-E18). Quién asigna también
 * (repartidor-E13): la oferta se crea directo en la base con su fábrica.
 *
 * Escenarios: repartidor-E15, repartidor-E16 y repartidor-E17 · Spec: openspec/changes/repartidor/specs/repartidor/spec.md
 * · Tarea 1.4
 */
class AceptarORechazarTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;

    private Repartidor $repartidor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->repartidor = $this->repartidorActivo();
    }

    #[TestDox('repartidor-E15: el repartidor acepta un pedido asignado y todavía sin aceptar; la oferta queda aceptada con su hora, el pedido queda suyo y su estado no cambia')]
    public function test_repartidor_e15_acepta_el_pedido(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $oferta = $this->ofrecerA($pedido, $this->repartidor);
        $eventos = $this->eventosDe($pedido);
        $this->travelTo(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' 10:15', self::ZONA_HORARIA));

        $this->aceptar($this->repartidor, $pedido)
            ->assertOk()
            ->assertJsonPath('pedido.id_publico', $pedido->id_publico)
            ->assertJsonPath('pedido.asignacion.estado', PedidoAsignacion::ACEPTADO)
            ->assertJsonPath('pedido.asignacion.respondido_en', '2026-12-03T16:15:00Z')
            ->assertJsonPath('pedido.por_responder', false)
            ->assertJsonPath('pedido.estado.codigo', EstadoPedido::ACEPTADO);

        $oferta->refresh();
        $this->assertSame(PedidoAsignacion::ACEPTADO, $oferta->estado);
        $this->assertSame('2026-12-03T16:15:00Z', $oferta->respondido_en->utc()->toIso8601ZuluString());
        $this->assertNull($oferta->motivo);
        $this->assertSame($this->repartidor->getKey(), $pedido->fresh()->id_repartidores);
        $this->assertPedidoSinCambio($pedido, EstadoPedido::ACEPTADO, $eventos);

        // Sigue en su lista, ya aceptado.
        $this->listaDelRepartidor($this->repartidor)
            ->assertOk()
            ->assertJsonPath('pedidos.0.id_publico', $pedido->id_publico)
            ->assertJsonPath('pedidos.0.asignacion.estado', PedidoAsignacion::ACEPTADO);
    }

    #[TestDox('repartidor-E15: la oferta se acepta también si la lavandería todavía no responde el pedido; el pedido sigue Confirmado')]
    public function test_repartidor_e15_acepta_un_pedido_que_la_lavanderia_no_ha_respondido(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->ofrecerA($pedido, $this->repartidor);

        $this->aceptar($this->repartidor, $pedido)->assertOk()->assertJsonPath('pedido.asignacion.estado', PedidoAsignacion::ACEPTADO);

        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->fresh()->estado->codigo);
        $this->assertSame($this->repartidor->getKey(), $pedido->fresh()->id_repartidores);
    }

    #[TestDox('repartidor-E15: una oferta ya aceptada o rechazada no se vuelve a responder: responde 409 con el pedido como está, y nada cambia')]
    public function test_repartidor_e15_una_oferta_respondida_no_se_vuelve_a_responder(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $oferta = $this->ofrecerA($pedido, $this->repartidor);
        $this->aceptar($this->repartidor, $pedido)->assertOk();
        $respondida = $oferta->fresh()->respondido_en;

        $this->aceptar($this->repartidor, $pedido)
            ->assertConflict()
            ->assertJsonPath('message', 'La oferta ya fue respondida.')
            ->assertJsonPath('pedido.asignacion.estado', PedidoAsignacion::ACEPTADO);
        $this->rechazar($this->repartidor, $pedido, 'Ya no puedo.')
            ->assertConflict()
            ->assertJsonPath('message', 'La oferta ya fue respondida.');

        $oferta->refresh();
        $this->assertSame(PedidoAsignacion::ACEPTADO, $oferta->estado);
        $this->assertNull($oferta->motivo);
        $this->assertEquals($respondida, $oferta->respondido_en);
        $this->assertSame(1, $pedido->asignaciones()->count());
    }

    #[TestDox('repartidor-E15: cuando un repartidor acepta, el pedido es suyo: a otro repartidor con una oferta del mismo pedido deja de aparecerle, y no lo puede aceptar ni rechazar')]
    public function test_repartidor_e15_el_pedido_aceptado_es_de_quien_lo_acepto(): void
    {
        $otro = $this->repartidorActivo();
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $this->ofrecerA($pedido, $this->repartidor);
        $ofertaDelOtro = $this->ofrecerA($pedido, $otro);

        $this->aceptar($this->repartidor, $pedido)->assertOk();

        $this->listaDelRepartidor($otro)->assertOk()->assertExactJson(['pedidos' => []]);
        $this->pedidoComoRepartidor($otro, $pedido->id_publico)->assertNotFound();
        $this->aceptar($otro, $pedido)->assertNotFound()->assertExactJson(['message' => 'No existe el pedido.']);
        $this->rechazar($otro, $pedido, 'Motivo de prueba.')->assertNotFound();

        $this->assertSame(PedidoAsignacion::OFRECIDO, $ofertaDelOtro->fresh()->estado);
        $this->assertSame($this->repartidor->getKey(), $pedido->fresh()->id_repartidores);
    }

    #[TestDox('repartidor-E16: el repartidor rechaza con su motivo un pedido asignado y sin aceptar; el rechazo queda con su motivo, su hora y el repartidor, y el pedido deja de estar asignado a él')]
    public function test_repartidor_e16_rechaza_con_motivo(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $oferta = $this->ofrecerA($pedido, $this->repartidor);
        $eventos = $this->eventosDe($pedido);
        $this->travelTo(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' 10:20', self::ZONA_HORARIA));

        $this->rechazar($this->repartidor, $pedido, '  Mi moto está en el taller.  ')
            ->assertOk()
            ->assertExactJson(['asignacion' => [
                'pedido' => $pedido->id_publico,
                'estado' => PedidoAsignacion::RECHAZADO,
                'motivo' => 'Mi moto está en el taller.',
                'respondido_en' => '2026-12-03T16:20:00Z',
            ]]);

        $oferta->refresh();
        $this->assertSame(PedidoAsignacion::RECHAZADO, $oferta->estado);
        $this->assertSame('Mi moto está en el taller.', $oferta->motivo);
        $this->assertSame('2026-12-03T16:20:00Z', $oferta->respondido_en->utc()->toIso8601ZuluString());
        $this->assertTrue($this->repartidor->is($oferta->repartidor));

        // Deja de estar asignado a él: ni en su lista, ni por su identificador, ni para aceptarlo.
        $this->assertNull($pedido->fresh()->id_repartidores);
        $this->listaDelRepartidor($this->repartidor)->assertOk()->assertExactJson(['pedidos' => []]);
        $this->pedidoComoRepartidor($this->repartidor, $pedido->id_publico)->assertNotFound();
        $this->aceptar($this->repartidor, $pedido)->assertNotFound();

        // El pedido no cambia: qué pasa con él espera DD-014 (repartidor-E18).
        $this->assertPedidoSinCambio($pedido, EstadoPedido::ACEPTADO, $eventos);
    }

    #[TestDox('repartidor-E17: un rechazo sin motivo, vacío, de puros espacios o más largo que el máximo se rechaza con 422, y el pedido sigue asignado a él')]
    public function test_repartidor_e17_un_rechazo_sin_motivo_no_se_acepta(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $oferta = $this->ofrecerA($pedido, $this->repartidor);
        config(['pedidos.motivo_de_rechazo_maximo' => 20]);

        foreach ([null, '', '   ', ['no', 'es', 'texto']] as $motivo) {
            $this->rechazar($this->repartidor, $pedido, $motivo)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['motivo' => 'Escribe el motivo del rechazo.']);
        }

        $this->rechazar($this->repartidor, $pedido, str_repeat('a', 21))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo' => 'El motivo no puede pasar de 20 caracteres.']);

        $this->assertSame(PedidoAsignacion::OFRECIDO, $oferta->fresh()->estado);
        $this->assertNull($oferta->fresh()->motivo);
        $this->assertSame([$pedido->id_publico], $this->idsDeLaListaDelRepartidor($this->listaDelRepartidor($this->repartidor)));
    }

    #[TestDox('repartidor-E15 y E16: aceptar o rechazar un pedido que no se le ofreció, de otra plaza o que no existe responde 404 sin datos, y nada cambia')]
    public function test_repartidor_e15_y_e16_lo_ajeno_responde_404(): void
    {
        $ajeno = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $ofertaAjena = $this->ofrecerA($ajeno, $this->repartidorActivo());

        foreach ([$ajeno->id_publico, '01jd8x6m3v2c9q4w5e7r8t0y1z', 'NO-ES-ULID'] as $identificador) {
            $this->comoRepartidor($this->repartidor)->postJson("/api/repartidor/pedidos/{$identificador}/aceptar")
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe el pedido.']);
            $this->comoRepartidor($this->repartidor)->postJson("/api/repartidor/pedidos/{$identificador}/rechazar", ['motivo' => 'Motivo de prueba.'])
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe el pedido.']);
        }

        $this->assertSame(PedidoAsignacion::OFRECIDO, $ofertaAjena->fresh()->estado);
        $this->assertNull($ajeno->fresh()->id_repartidores);
    }

    private function aceptar(Repartidor $repartidor, Pedido $pedido): TestResponse
    {
        return $this->comoRepartidor($repartidor)->postJson("/api/repartidor/pedidos/{$pedido->id_publico}/aceptar");
    }

    /**
     * @param  mixed  $motivo  Sin motivo (null), la petición no lo lleva.
     */
    private function rechazar(Repartidor $repartidor, Pedido $pedido, mixed $motivo): TestResponse
    {
        return $this->comoRepartidor($repartidor)->postJson(
            "/api/repartidor/pedidos/{$pedido->id_publico}/rechazar",
            $motivo === null ? [] : ['motivo' => $motivo],
        );
    }
}
