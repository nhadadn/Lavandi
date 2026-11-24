<?php

namespace Tests\Feature\Pago;

use App\Models\EstadoPedido;
use App\Models\EventoPasarela;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El pago se confirma solo en el servidor: ninguna petición de la app, ni su regreso desde el checkout, marca un cobro
 * como pagado; solo un aviso auténtico de la pasarela (NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.4, "verifica los
 * pagos"; ADR-0010, decisión 1).
 *
 * Con la pasarela simulada, un aviso es auténtico si trae la firma de su llave sobre el cuerpo exacto. La autenticidad
 * de los avisos de Conekta, por confirmar en su sandbox, es la tarea 3.1 (pago-E09).
 *
 * Escenario: pago-E07 · Spec: openspec/changes/pago/specs/pago/spec.md · Tarea 2.6
 */
class SoloElWebhookConfirmaTest extends TestCase
{
    use AgendaDePrueba;
    use CobrosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Pedido $pedido;

    private Pago $cobro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->pedido = $this->pedidoPorPagar();
        $this->cobro = $this->conCheckout($this->cobroPendiente($this->pedido));
    }

    #[TestDox('pago-E07: la app vuelve del checkout por la dirección de éxito y lo dice, o manda que ya pagó, y el cobro sigue pendiente y el pedido sin confirmar')]
    public function test_pago_e07_volver_del_checkout_no_confirma_el_pago(): void
    {
        $token = $this->pedido->usuario->createToken('app')->plainTextToken;
        $yaPague = [
            'pedido' => $this->pedido->id_publico,
            'metodo' => Pago::TARJETA,
            'resultado' => 'exito',
            'estado' => Pago::PAGADO,
            'pagado' => true,
            'pagado_en' => '2026-12-03T15:30:00Z',
            'id_pasarela_orden' => $this->cobro->id_pasarela_orden,
        ];

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/pagos/checkout', $yaPague)
            ->assertOk()
            ->assertJsonPath('pago.estado', Pago::PENDIENTE);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/pagos/{$this->cobro->id_publico}?".http_build_query($yaPague))
            ->assertOk()
            ->assertJsonPath('pago.estado', Pago::PENDIENTE);

        foreach ([
            ['POST', "/api/pagos/{$this->cobro->id_publico}"],
            ['PATCH', "/api/pagos/{$this->cobro->id_publico}"],
            ['PUT', "/api/pagos/{$this->cobro->id_publico}"],
            ['POST', "/api/pagos/{$this->cobro->id_publico}/confirmar"],
            ['POST', "/api/pagos/{$this->cobro->id_publico}/regreso"],
            ['POST', '/api/pagos/regreso'],
            ['POST', "/api/pedidos/{$this->pedido->id_publico}/pago"],
        ] as [$metodo, $direccion]) {
            $this->app['auth']->forgetGuards();
            $respuesta = $this->withToken($token)->json($metodo, $direccion, $yaPague);

            $this->assertContains($respuesta->status(), [404, 405], "{$metodo} {$direccion}");
        }

        $this->assertSame(Pago::PENDIENTE, $this->cobro->fresh()->estado);
        $this->assertNull($this->cobro->fresh()->pagado_en);
        $this->assertSame(EstadoPedido::POR_PAGAR, $this->pedido->fresh()->estado->codigo);
        $this->assertNull($this->pedido->fresh()->folio);
    }

    #[TestDox('pago-E07: las rutas del cobro para la app son pedir el checkout y consultar el cobro; ninguna otra')]
    public function test_pago_e07_las_rutas_del_cobro_para_la_app_solo_piden_el_checkout_y_consultan(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (Ruta $ruta): bool => str_starts_with($ruta->uri(), 'api/pagos'))
            ->flatMap(fn (Ruta $ruta): array => array_map(
                fn (string $metodo): string => "{$metodo} {$ruta->uri()}",
                array_diff($ruta->methods(), ['HEAD']),
            ))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['GET api/pagos/{pago}', 'POST api/pagos/checkout'], $rutas);
    }

    #[TestDox('pago-E07: un aviso que dice que el cobro se pagó, sin la firma de la pasarela, con otra firma o con la firma de otro cuerpo, se rechaza sin responder 200, no se guarda y el cobro sigue pendiente')]
    public function test_pago_e07_un_aviso_que_no_es_autentico_no_paga_el_cobro(): void
    {
        $aviso = $this->pasarelaSimulada()->avisoSimulado($this->cobro->id_pasarela_orden, 'aprobado');
        $cabecera = $this->pasarelaSimulada()::CABECERA_DE_FIRMA;
        $otroCuerpo = str_replace('pago.aprobado', 'pago.rechazado', $aviso['cuerpo']);

        foreach ([
            [],
            [$cabecera => ''],
            [$cabecera => hash_hmac('sha256', $aviso['cuerpo'], 'otra-llave')],
            [$cabecera => hash_hmac('sha256', $otroCuerpo, (string) config('pasarela.simulada.llave'))],
            ['Authorization' => 'Bearer '.$this->pedido->usuario->createToken('app')->plainTextToken],
        ] as $cabeceras) {
            $this->avisoCrudo($aviso['cuerpo'], $cabeceras)
                ->assertUnauthorized()
                ->assertExactJson(['message' => 'El aviso no viene de la pasarela.']);
        }

        $this->assertSame(0, EventoPasarela::count());
        $this->assertSame(Pago::PENDIENTE, $this->cobro->fresh()->estado);
        $this->assertNull($this->pedido->fresh()->folio);
    }

    #[TestDox('pago-E07: solo el aviso auténtico de la pasarela marca el cobro como pagado')]
    public function test_pago_e07_solo_el_aviso_autentico_marca_el_cobro_como_pagado(): void
    {
        $aviso = $this->pasarelaSimulada()->avisoSimulado($this->cobro->id_pasarela_orden, 'aprobado');

        $this->avisoCrudo($aviso['cuerpo'])->assertUnauthorized();
        $this->assertSame(Pago::PENDIENTE, $this->cobro->fresh()->estado);

        $this->avisoCrudo($aviso['cuerpo'], $aviso['cabeceras'])->assertOk();
        $this->assertSame(Pago::PAGADO, $this->cobro->fresh()->estado);
        $this->assertSame(EstadoPedido::CONFIRMADO, $this->pedido->fresh()->estado->codigo);
    }
}
