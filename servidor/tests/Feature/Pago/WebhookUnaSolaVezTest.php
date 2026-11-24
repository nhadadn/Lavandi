<?php

namespace Tests\Feature\Pago;

use App\Models\EventoPasarela;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Cada aviso de la pasarela (webhook) se guarda primero en eventos_pasarela y se procesa una sola vez: si el mismo
 * evento llega otra vez, servidor/ responde 200 y no cambia nada, porque el único (pasarela, id_pasarela_evento) lo
 * impide también en la base (openspec/changes/pago/design.md, decisión 3; ADR-0010, decisión 2).
 *
 * Un aviso cuyo procesamiento falla queda guardado con su error y sin procesar: la respuesta no es 200, la pasarela lo
 * reintenta, y el reintento sí lo procesa. El contenido del aviso se guarda sin datos de tarjeta
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3).
 *
 * Los avisos los manda la pasarela simulada, firmados con su llave de prueba.
 *
 * Escenarios: pago-E10, y pago-E05 en el contenido del aviso · Spec: openspec/changes/pago/specs/pago/spec.md · Tarea 2.1
 */
class WebhookUnaSolaVezTest extends TestCase
{
    use AgendaDePrueba;
    use CobrosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Pago $cobro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->cobro = $this->conCheckout($this->cobroPendiente($this->pedidoPorPagar()));
    }

    #[TestDox('pago-E10: el aviso de la pasarela se guarda en eventos_pasarela, con su pasarela, su identificador, su tipo ya traducido, su contenido y cuándo se recibió y se procesó, y servidor/ responde 200')]
    public function test_pago_e10_el_aviso_se_guarda_y_se_procesa(): void
    {
        $this->elJuevesALas('10:00:00');

        $this->avisar($this->cobro, 'aprobado', 'evento-de-prueba-1')
            ->assertOk()
            ->assertExactJson(['recibido' => true]);

        $evento = EventoPasarela::sole();
        $this->assertSame(Pago::PASARELA_SIMULADA, $evento->pasarela);
        $this->assertSame('evento-de-prueba-1', $evento->id_pasarela_evento);
        $this->assertSame(EventoPasarela::APROBADO, $evento->tipo);
        $this->assertSame($this->cobro->id_pasarela_orden, $evento->contenido['orden']);
        $this->assertTrue($evento->recibido_en->eq(CarbonImmutable::now()));
        $this->assertTrue($evento->procesado_en->eq(CarbonImmutable::now()));
        $this->assertNull($evento->error);
    }

    #[TestDox('pago-E10: si la pasarela vuelve a mandar el mismo evento, servidor/ responde 200, no lo guarda otra vez ni lo procesa otra vez, y no cambia nada')]
    public function test_pago_e10_el_mismo_evento_dos_veces_no_cambia_nada(): void
    {
        $this->elJuevesALas('10:00:00');
        $this->avisar($this->cobro, 'aprobado', 'evento-de-prueba-1')->assertOk();
        $primero = EventoPasarela::sole();
        $cobro = $this->cobro->fresh();
        $pedido = $cobro->pedido->fresh();

        $this->elJuevesALas('10:05:00');
        $this->avisar($this->cobro, 'aprobado', 'evento-de-prueba-1')
            ->assertOk()
            ->assertExactJson(['recibido' => true]);

        $this->assertSame(1, EventoPasarela::count());
        $repetido = EventoPasarela::sole();
        $this->assertTrue($primero->recibido_en->eq($repetido->recibido_en));
        $this->assertTrue($primero->procesado_en->eq($repetido->procesado_en));
        $this->assertSame($primero->updated_at->toIso8601String(), $repetido->updated_at->toIso8601String());

        // Ni el cobro ni el pedido cambiaron con el repetido.
        $this->assertSame($cobro->getAttributes(), $this->cobro->fresh()->getAttributes());
        $this->assertSame($pedido->getAttributes(), $pedido->fresh()->getAttributes());
        $this->assertSame(1, Pago::count());
    }

    #[TestDox('pago-E10: dos avisos distintos de la pasarela se guardan cada uno')]
    public function test_pago_e10_dos_avisos_distintos_se_guardan_cada_uno(): void
    {
        $this->avisar($this->cobro, 'rechazado', 'evento-de-prueba-1')->assertOk();
        $this->avisar($this->cobro, 'aprobado', 'evento-de-prueba-2')->assertOk();

        $this->assertSame(
            ['evento-de-prueba-1' => EventoPasarela::RECHAZADO, 'evento-de-prueba-2' => EventoPasarela::APROBADO],
            EventoPasarela::query()->orderBy('id_eventos_pasarela')->pluck('tipo', 'id_pasarela_evento')->all(),
        );
        $this->assertSame(0, EventoPasarela::query()->whereNull('procesado_en')->count());
    }

    #[TestDox('pago-E10: un aviso de un tipo que el cobro no usa se guarda como otro, se da por procesado y responde 200')]
    public function test_pago_e10_un_aviso_de_otro_tipo_se_guarda_como_otro(): void
    {
        $this->avisar($this->cobro, 'cliente.creado', 'evento-de-prueba-1')->assertOk();

        $evento = EventoPasarela::sole();
        $this->assertSame(EventoPasarela::OTRO, $evento->tipo);
        $this->assertNotNull($evento->procesado_en);
        $this->assertSame(Pago::PENDIENTE, $this->cobro->fresh()->estado);
    }

    #[TestDox('pago-E10: si el procesamiento falla, el aviso queda guardado con su error y sin procesar, la respuesta no es 200, y cuando la pasarela lo reintenta se procesa una vez')]
    public function test_pago_e10_si_el_procesamiento_falla_el_reintento_lo_procesa(): void
    {
        $fallar = true;
        Event::listen('eloquent.saving: '.EventoPasarela::class, function (EventoPasarela $evento) use (&$fallar): void {
            if ($fallar && $evento->isDirty('procesado_en')) {
                throw new RuntimeException('Falla de prueba.');
            }
        });

        $this->avisar($this->cobro, 'aprobado', 'evento-de-prueba-1')->assertServerError();

        $guardado = EventoPasarela::sole();
        $this->assertNull($guardado->procesado_en);
        $this->assertSame('Falla de prueba.', $guardado->error);

        $fallar = false;
        $this->avisar($this->cobro, 'aprobado', 'evento-de-prueba-1')->assertOk();

        $procesado = EventoPasarela::sole();
        $this->assertNotNull($procesado->procesado_en);
        $this->assertNull($procesado->error);
    }

    #[TestDox('pago-E05: el contenido del aviso se guarda sin el número, el vencimiento ni el código de seguridad de la tarjeta; la marca y los últimos cuatro dígitos se conservan')]
    public function test_pago_e05_el_contenido_del_aviso_se_guarda_sin_datos_de_tarjeta(): void
    {
        $aviso = $this->pasarelaSimulada()->avisoSimulado($this->cobro->id_pasarela_orden, 'aprobado', 'evento-de-prueba-1', [
            'metodo_de_pago' => [
                'tipo' => 'tarjeta',
                'marca' => 'visa',
                'ultimos_4' => '4242',
                'last4' => '4242',
                'brand' => 'visa',
                'number' => '4242424242424242',
                'numero' => '4242424242424242',
                'cvc' => '123',
                'cvv' => '123',
                'exp_month' => '12',
                'exp_year' => '2030',
                'expiracion' => '12/30',
                'tarjeta' => ['numero_tarjeta' => '4242424242424242', 'card_number' => '4242424242424242', 'vencimiento' => '12/30'],
            ],
        ]);

        $this->avisoCrudo($aviso['cuerpo'], $aviso['cabeceras'])->assertOk();

        $guardado = EventoPasarela::sole();
        // jsonb no conserva el orden de las llaves.
        $this->assertEquals(
            ['tipo' => 'tarjeta', 'marca' => 'visa', 'ultimos_4' => '4242', 'last4' => '4242', 'brand' => 'visa', 'tarjeta' => []],
            $guardado->contenido['metodo_de_pago'],
        );
        $this->assertStringNotContainsString('4242424242424242', (string) $guardado->getRawOriginal('contenido'));
        $this->assertStringNotContainsString('2030', (string) $guardado->getRawOriginal('contenido'));
    }

    #[TestDox('pago-E10: un aviso que la pasarela no reconoce, sin identificador de evento o que no es JSON, no se guarda y no responde 200')]
    public function test_pago_e10_un_aviso_que_no_se_reconoce_no_se_guarda(): void
    {
        $llave = (string) config('pasarela.simulada.llave');

        foreach (['no es json', '{"tipo":"pago.aprobado"}', '[]'] as $cuerpo) {
            $this->avisoCrudo($cuerpo, [$this->pasarelaSimulada()::CABECERA_DE_FIRMA => hash_hmac('sha256', $cuerpo, $llave)])
                ->assertBadRequest()
                ->assertExactJson(['message' => 'El aviso no se reconoce.']);
        }

        $this->assertSame(0, EventoPasarela::count());
    }
}
