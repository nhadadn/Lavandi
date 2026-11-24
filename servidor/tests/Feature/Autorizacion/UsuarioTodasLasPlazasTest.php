<?php

namespace Tests\Feature\Autorizacion;

use App\Autorizacion\Autorizacion;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La plaza no restringe al usuario: ve sus pedidos de todas las plazas (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3,
 * "Cada tipo de usuario accede únicamente a la información que le corresponde": lo suyo, de cualquier plaza). La cuenta
 * del usuario no tiene plaza; la tienen su dirección y su pedido (design.md, decisión 3). La plaza separa a los socios
 * (autorizacion-E12).
 *
 * Escenario: autorizacion-E08 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Tarea 2.2
 */
class UsuarioTodasLasPlazasTest extends TestCase
{
    use AgendaDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    private Plaza $plazaDos;

    private Pedido $enLaPlazaUno;

    private Pedido $enLaPlazaDos;

    protected function setUp(): void
    {
        parent::setUp();

        // La plaza P1 es la de prepararPedido(); la P2, otra plaza de prueba con su zona al este, en el océano Pacífico.
        $this->prepararPedido();
        $this->plazaDos = $this->plazaConAgenda('Plaza de prueba 2', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($this->plazaDos, 'Centro 2', $this->cuadrado(-118.0, 10.0));

        $this->cuenta = $this->cuentaDeUsuario();
        $this->enLaPlazaUno = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuenta, '11:00');
        $this->enLaPlazaDos = $this->pedidoConfirmadoEn(
            $this->otraLavanderia($this->plazaDos),
            $this->cuenta,
            '12:00',
            latitud: 10.5,
            longitud: -117.5,
        );
    }

    #[TestDox('autorizacion-E08: un usuario con un pedido en la plaza P1 y otro en la P2 consulta sus pedidos y recibe los dos')]
    public function test_autorizacion_e08_el_usuario_recibe_sus_pedidos_de_las_dos_plazas(): void
    {
        $this->assertSame($this->plaza->getKey(), $this->enLaPlazaUno->id_plazas);
        $this->assertSame($this->plazaDos->getKey(), $this->enLaPlazaDos->id_plazas);

        $lista = $this->comoLaCuenta('/api/pedidos')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$this->enLaPlazaUno->id_publico, $this->enLaPlazaDos->id_publico],
            array_column($lista->json('pedidos'), 'id_publico'),
        );
    }

    #[TestDox('autorizacion-E08: el usuario abre por su id_publico cada uno de sus pedidos, el de la plaza P1 y el de la P2')]
    public function test_autorizacion_e08_el_usuario_abre_cada_uno_de_sus_pedidos(): void
    {
        foreach ([$this->enLaPlazaUno, $this->enLaPlazaDos] as $pedido) {
            $this->comoLaCuenta("/api/pedidos/{$pedido->id_publico}")
                ->assertOk()
                ->assertJsonPath('pedido.id_publico', $pedido->id_publico)
                ->assertJsonPath('pedido.folio', $pedido->folio);
        }
    }

    #[TestDox('autorizacion-E08: el componente de autorización le da al usuario sus pedidos de las dos plazas, y a otro usuario ninguno')]
    public function test_autorizacion_e08_el_componente_le_da_sus_pedidos_de_las_dos_plazas(): void
    {
        $autorizacion = app(Autorizacion::class);

        $this->assertEqualsCanonicalizing(
            [$this->enLaPlazaUno->getKey(), $this->enLaPlazaDos->getKey()],
            $autorizacion->pedidosVisibles($this->cuenta)->pluck('id_pedidos')->all(),
        );
        $this->assertSame(0, $autorizacion->pedidosVisibles($this->cuentaDeUsuario())->count());
    }

    private function comoLaCuenta(string $direccion): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->cuenta->createToken('app')->plainTextToken)->getJson($direccion);
    }
}
