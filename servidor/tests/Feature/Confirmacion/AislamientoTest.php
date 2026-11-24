<?php

namespace Tests\Feature\Confirmacion;

use App\Autorizacion\Autorizacion;
use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Cada usuario ve solo sus pedidos: el folio, el resumen y la confirmación de un pedido solo los ve la cuenta que lo
 * hizo (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, separación por tipo de usuario; CLAUDE.md §9.5).
 *
 * Quién ve qué pedido lo dice el componente único de autorización. Un pedido ajeno responde igual que uno que no existe,
 * 404 y sin ningún dato (ADR-0019, decisión 3). La API identifica el pedido solo por su id_publico: el folio no abre
 * ningún pedido, ni el propio (design.md, decisión 8).
 *
 * Escenario: confirmacion-E12 · Spec: openspec/changes/resumen-y-confirmacion/specs/confirmacion/spec.md · Tarea 1.6
 */
class AislamientoTest extends TestCase
{
    use AgendaDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Un ULID bien formado, en el alfabeto de Crockford, que no es de ningún pedido. */
    private const ULID_SIN_PEDIDO = '01jd8x6m3v2c9q4w5e7r8t0y1z';

    private const NO_EXISTE = ['message' => 'No existe el pedido.'];

    private Usuario $ana;

    private Usuario $beto;

    private Pedido $pedidoDeAna;

    private Pedido $pedidoDeBeto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        // Dos usuarios, cada uno con un pedido confirmado.
        $this->ana = $this->cuentaDeUsuario();
        $this->beto = $this->cuentaDeUsuario();
        $this->pedidoDeAna = $this->pedidoConfirmadoDe($this->ana, ['alias' => 'Casa de Ana']);
        $this->pedidoDeBeto = $this->pedidoConfirmadoDe($this->beto, [
            'alias' => 'Casa de Beto',
            'calle' => 'Calle de Beto',
            'referencias' => 'Referencias de Beto',
        ]);
    }

    #[TestDox('confirmacion-E12: un usuario que pide el pedido del otro por su id_publico recibe lo mismo que si no existiera, sin ningún dato')]
    public function test_confirmacion_e12_el_pedido_ajeno_por_su_id_publico_responde_como_si_no_existiera(): void
    {
        $ajeno = $this->verPedido($this->ana, $this->pedidoDeBeto->id_publico);
        $ajeno->assertNotFound()->assertExactJson(self::NO_EXISTE);
        $this->assertSinDatosDeBeto($ajeno);

        // Igual que uno que no existe, también con el ULID en mayúsculas.
        $this->verPedido($this->ana, self::ULID_SIN_PEDIDO)->assertNotFound()->assertExactJson(self::NO_EXISTE);
        $this->verPedido($this->ana, Str::upper($this->pedidoDeBeto->id_publico))
            ->assertNotFound()
            ->assertExactJson(self::NO_EXISTE);
    }

    #[TestDox('confirmacion-E12: un usuario que pide el pedido del otro por su folio recibe lo mismo que si no existiera, sin ningún dato')]
    public function test_confirmacion_e12_el_pedido_ajeno_por_su_folio_responde_como_si_no_existiera(): void
    {
        $porFolio = $this->verPedido($this->ana, $this->pedidoDeBeto->folio);
        $porFolio->assertNotFound()->assertExactJson(self::NO_EXISTE);
        $this->assertSinDatosDeBeto($porFolio);

        // El folio no es un identificador de la API: tampoco abre el propio.
        $this->verPedido($this->beto, $this->pedidoDeBeto->folio)->assertNotFound()->assertExactJson(self::NO_EXISTE);
    }

    #[TestDox('confirmacion-E12: la cuenta que hizo el pedido sí lo ve, con su folio y su resumen')]
    public function test_confirmacion_e12_la_cuenta_que_hizo_el_pedido_si_lo_ve(): void
    {
        $this->verPedido($this->beto, $this->pedidoDeBeto->id_publico)
            ->assertOk()
            ->assertJsonPath('pedido.id_publico', $this->pedidoDeBeto->id_publico)
            ->assertJsonPath('pedido.folio', $this->pedidoDeBeto->folio)
            ->assertJsonPath('pedido.estado.codigo', 'confirmado')
            ->assertJsonPath('pedido.estado.nombre', 'Confirmado')
            ->assertJsonPath('pedido.lavanderia.nombre', $this->lavanderia->nombre)
            ->assertJsonPath('pedido.servicios.0.nombre', 'Lavado estándar')
            ->assertJsonPath('pedido.ventana.fecha', self::JUEVES_DE_PRUEBA)
            ->assertJsonPath('pedido.ventana.hora_inicio', '11:00')
            ->assertJsonPath('pedido.recogida.alias', 'Casa de Beto')
            ->assertJsonPath('pedido.confirmado_en', $this->pedidoDeBeto->confirmado_en->toIso8601ZuluString());

        $this->verPedido($this->ana, Str::upper($this->pedidoDeAna->id_publico))
            ->assertOk()
            ->assertJsonPath('pedido.recogida.alias', 'Casa de Ana');
    }

    #[TestDox('confirmacion-E12: una cuenta sin el rol de usuario, como la de una lavandería, no ve el pedido; sin sesión, tampoco')]
    public function test_confirmacion_e12_una_cuenta_sin_el_rol_de_usuario_no_ve_el_pedido(): void
    {
        $deLavanderia = Usuario::factory()->conRol(Rol::LAVANDERIA)->create();

        $respuesta = $this->verPedido($deLavanderia, $this->pedidoDeBeto->id_publico);
        $respuesta->assertForbidden();
        $this->assertSinDatosDeBeto($respuesta);

        $this->app['auth']->forgetGuards();
        $sinSesion = $this->withoutToken()->getJson('/api/pedidos/'.$this->pedidoDeBeto->id_publico);
        $sinSesion->assertUnauthorized();
        $this->assertSinDatosDeBeto($sinSesion);
    }

    #[TestDox('confirmacion-E12: el componente de autorización da a cada cuenta solo sus pedidos')]
    public function test_confirmacion_e12_el_componente_de_autorizacion_da_a_cada_cuenta_solo_sus_pedidos(): void
    {
        $autorizacion = app(Autorizacion::class);

        $this->assertSame(
            [$this->pedidoDeAna->getKey()],
            $autorizacion->pedidosVisibles($this->ana)->pluck('id_pedidos')->all(),
        );
        $this->assertSame(
            [$this->pedidoDeBeto->getKey()],
            $autorizacion->pedidosVisibles($this->beto)->pluck('id_pedidos')->all(),
        );
        $this->assertSame([], $autorizacion->pedidosVisibles($this->cuentaDeUsuario())->pluck('id_pedidos')->all());
    }

    /**
     * Un pedido de la cuenta, creado al aceptar el resumen y confirmado por su cobro pagado.
     *
     * @param  array<string, mixed>  $direccion
     */
    private function pedidoConfirmadoDe(Usuario $cuenta, array $direccion): Pedido
    {
        $id = $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta, $direccion)))
            ->assertCreated()
            ->json('pedido.id_publico');

        return app(CobroSimulado::class)->pagar(Pedido::query()->where('id_publico', $id)->sole())->fresh();
    }

    private function verPedido(Usuario $cuenta, string $identificador): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->getJson('/api/pedidos/'.$identificador);
    }

    /**
     * La respuesta no trae nada del pedido de Beto: ni su identificador, ni su folio, ni su dirección.
     */
    private function assertSinDatosDeBeto(TestResponse $respuesta): void
    {
        $contenido = $respuesta->getContent();

        foreach ([$this->pedidoDeBeto->id_publico, $this->pedidoDeBeto->folio, 'Casa de Beto', 'Calle de Beto',
            'Referencias de Beto'] as $dato) {
            $this->assertStringNotContainsStringIgnoringCase($dato, $contenido);
        }
    }
}
