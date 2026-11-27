<?php

namespace Tests\Feature\Pago;

use App\Autorizacion\Autorizacion;
use App\Models\EstadoPedido;
use App\Models\EventoPasarela;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La página de checkout de desarrollo: en local, el checkout de la pasarela simulada lleva aquí, para probar el cobro
 * desde el teléfono sin ninguna pasarela real (openspec/changes/pago/design.md, "Decisiones de la implementación"). No
 * es una pantalla del producto: solo existe en local y en las pruebas.
 *
 * Tiene dos botones, Aprobar y Rechazar. Cada uno manda el aviso simulado de la pasarela por el mismo camino del webhook
 * (App\Cobro\RecepcionDeAvisos) y después regresa a la app por la dirección de regreso de la configuración
 * (PASARELA_REGRESO), con el resultado y el cobro. Regresar no confirma nada: lo confirmó el aviso (pago-E07).
 *
 * Escenarios a los que sirve: pago-E01, pago-E03 y pago-E04, para probarlos a mano desde la app · Tarea: herramienta de
 * desarrollo del cambio pago
 */
class CheckoutDeDesarrolloTest extends TestCase
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

    #[TestDox('pago-E01, herramienta de desarrollo: la URL del checkout de la pasarela simulada lleva a la página de checkout de desarrollo, que muestra el monto del cobro y los botones Aprobar y Rechazar')]
    public function test_la_pagina_de_checkout_de_desarrollo_muestra_el_cobro_y_sus_botones(): void
    {
        $url = $this->pasarelaSimulada()->checkoutVigente($this->cobro->id_pasarela_orden);
        $this->assertSame(route('desarrollo.pasarela.checkout', $this->cobro->id_pasarela_orden), $url);

        $this->get($url)
            ->assertOk()
            ->assertSee('Checkout simulado')
            ->assertSee('$'.self::MONTO_DE_PRUEBA)
            ->assertSee('Aprobar')
            ->assertSee('Rechazar')
            ->assertSee('name="resultado" value="aprobado"', false)
            ->assertSee('name="resultado" value="rechazado"', false)
            ->assertSee('action="'.route('desarrollo.pasarela.resultado', $this->cobro->id_pasarela_orden).'"', false)
            ->assertSee('noindex', false);

        // Abrir la página no cambia nada.
        $this->assertSame(Pago::PENDIENTE, $this->cobro->fresh()->estado);
        $this->assertSame(0, EventoPasarela::count());
    }

    #[TestDox('pago-E03 y pago-E18, herramienta de desarrollo: Aprobar manda el aviso de aprobado por el camino del webhook, que paga el cobro y confirma el pedido, y regresa a la app con el resultado de éxito')]
    public function test_aprobar_manda_el_aviso_de_aprobado_y_regresa_a_la_app(): void
    {
        $this->post(route('desarrollo.pasarela.resultado', $this->cobro->id_pasarela_orden), ['resultado' => 'aprobado'])
            ->assertRedirect(config('pasarela.regreso').'?resultado=exito&pago='.$this->cobro->id_publico);

        $evento = EventoPasarela::sole();
        $this->assertSame(EventoPasarela::APROBADO, $evento->tipo);
        $this->assertSame($this->cobro->id_pasarela_orden, $evento->contenido['orden']);
        $this->assertNotNull($evento->procesado_en);
        $this->assertSame(Pago::PAGADO, $this->cobro->fresh()->estado);
        $this->assertSame(EstadoPedido::CONFIRMADO, $this->pedido->fresh()->estado->codigo);
    }

    #[TestDox('pago-E04, herramienta de desarrollo: Rechazar manda el aviso de rechazo por el camino del webhook, que deja el cobro sin pagar, y regresa a la app con el resultado de error')]
    public function test_rechazar_manda_el_aviso_de_rechazo_y_regresa_a_la_app(): void
    {
        $this->post(route('desarrollo.pasarela.resultado', $this->cobro->id_pasarela_orden), ['resultado' => 'rechazado'])
            ->assertRedirect(config('pasarela.regreso').'?resultado=error&pago='.$this->cobro->id_publico);

        $this->assertSame(EventoPasarela::RECHAZADO, EventoPasarela::sole()->tipo);
        $this->assertSame(Pago::FALLIDO, $this->cobro->fresh()->estado);
        $this->assertNull($this->pedido->fresh()->folio);
    }

    #[TestDox('herramienta de desarrollo: sin dirección de regreso en la configuración, la página pide regresar a la app a mano, con el resultado')]
    public function test_sin_direccion_de_regreso_la_pagina_pide_regresar_a_la_app(): void
    {
        config(['pasarela.regreso' => null]);

        $this->post(route('desarrollo.pasarela.resultado', $this->cobro->id_pasarela_orden), ['resultado' => 'aprobado'])
            ->assertOk()
            ->assertSee('Pago aprobado')
            ->assertSee('Regresa a la app');

        $this->assertSame(Pago::PAGADO, $this->cobro->fresh()->estado);
    }

    #[TestDox('herramienta de desarrollo: un cobro ya pagado no muestra los botones; una orden que no existe responde 404; un resultado que no es aprobado ni rechazado no manda nada')]
    public function test_la_pagina_no_paga_lo_que_no_debe(): void
    {
        $this->get(route('desarrollo.pasarela.checkout', 'orden-que-no-existe'))->assertNotFound();
        $this->post(route('desarrollo.pasarela.resultado', 'orden-que-no-existe'), ['resultado' => 'aprobado'])->assertNotFound();

        $this->post(route('desarrollo.pasarela.resultado', $this->cobro->id_pasarela_orden), ['resultado' => 'pagado'])
            ->assertSessionHasErrors('resultado');
        $this->assertSame(0, EventoPasarela::count());

        $this->avisar($this->cobro, 'aprobado')->assertOk();
        $this->get(route('desarrollo.pasarela.checkout', $this->cobro->id_pasarela_orden))
            ->assertOk()
            ->assertSee('Este cobro ya está pagado.')
            ->assertDontSee('name="resultado"', false);
    }

    #[TestDox('herramienta de desarrollo: en producción y en el entorno de prueba en internet, la página de checkout de desarrollo no existe')]
    public function test_en_produccion_la_pagina_de_desarrollo_no_existe(): void
    {
        $direccion = route('desarrollo.pasarela.checkout', $this->cobro->id_pasarela_orden);

        foreach (['production', 'staging'] as $entorno) {
            $this->cargarLasRutasEn($entorno);

            $this->assertFalse(Route::has('desarrollo.pasarela.checkout'), $entorno);
            $this->assertFalse(Route::has('desarrollo.pasarela.resultado'), $entorno);
            // Tampoco la declara el componente de autorización entre las rutas sin sesión.
            $this->assertSame([], array_filter(
                array_keys(app(Autorizacion::class)->rutasSinSesion()),
                fn (string $ruta): bool => str_contains($ruta, 'desarrollo/'),
            ), $entorno);
            $this->get($direccion)->assertNotFound();
            $this->post($direccion, ['resultado' => 'aprobado'])->assertNotFound();
        }

        $this->assertSame(Pago::PENDIENTE, $this->cobro->fresh()->estado);
        $this->assertSame(0, EventoPasarela::count());
    }

    /**
     * Vuelve a registrar las rutas web como en ese entorno.
     */
    private function cargarLasRutasEn(string $entorno): void
    {
        $this->app->detectEnvironment(fn (): string => $entorno);
        $enrutador = $this->app['router'];
        $enrutador->setRoutes(new RouteCollection);
        $enrutador->middleware('web')->group(base_path('routes/web.php'));
        $enrutador->getRoutes()->refreshNameLookups();
    }
}
