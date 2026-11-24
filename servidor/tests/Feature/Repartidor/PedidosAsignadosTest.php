<?php

namespace Tests\Feature\Repartidor;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\EstadoPedido;
use App\Models\Repartidor;
use App\Repartidores\EstadoDelRepartidor;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * Los pedidos asignados del repartidor, GET /api/repartidor/pedidos y GET /api/repartidor/pedidos/{id_publico}: solo los
 * que se le ofrecieron o aceptó, solo de su plaza y solo con el pago acreditado (NSV-2026-001-v8.pdf:18:ANEXO A, Sección
 * A.3, Módulo C #2, "Pedidos asignados en tiempo real"; :20, Separación por tipo de usuario; C-188). La app consulta la
 * lista cada 30 s con la pantalla abierta (ADR-0007); el servidor responde lo de ese momento.
 *
 * Quién asigna espera DD-014 (repartidor-E13): la oferta se crea directo en la base con su fábrica (RepartidorDePrueba::
 * ofrecerA()). Qué pedidos ve lo decide el componente de autorización: uno ajeno, de otra plaza o sin pago responde igual
 * que uno que no existe (ADR-0019, decisión 3).
 *
 * Escenarios: repartidor-E10, repartidor-E12 y repartidor-E30; y el lado del servidor de repartidor-E05 y E11 · Spec:
 * openspec/changes/repartidor/specs/repartidor/spec.md · Tarea 1.3
 */
class PedidosAsignadosTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
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

    #[TestDox('repartidor-E10: un repartidor activo con dos pedidos asignados ve los dos, cada uno con su folio, su ventana, su dirección de recogida y de entrega y su lavandería, de la recolección más próxima a la más lejana')]
    public function test_repartidor_e10_ve_sus_dos_pedidos_asignados(): void
    {
        $repartidor = $this->repartidorActivo();
        $cuenta = $this->cuentaDeUsuario();
        $tarde = $this->pedidoConfirmadoEn($this->lavanderia, $cuenta, '12:00');
        $temprano = $this->pedidoConfirmadoEn($this->lavanderia, $cuenta, '11:00');
        $this->ofrecerA($tarde, $repartidor);
        $this->ofrecerA($temprano, $repartidor);

        $respuesta = $this->listaDelRepartidor($repartidor)->assertOk();

        $this->assertSame([$temprano->id_publico, $tarde->id_publico], $this->idsDeLaListaDelRepartidor($respuesta));
        $respuesta
            ->assertJsonPath('pedidos.0.folio', $temprano->folio)
            ->assertJsonPath('pedidos.1.folio', $tarde->folio)
            ->assertJsonPath('pedidos.0.estado', ['codigo' => EstadoPedido::CONFIRMADO, 'nombre' => 'Confirmado'])
            ->assertJsonPath('pedidos.0.asignacion.estado', 'ofrecido')
            ->assertJsonPath('pedidos.0.por_responder', true)
            ->assertJsonPath('pedidos.0.zona_horaria', self::ZONA_HORARIA)
            ->assertJsonPath('pedidos.0.ventana', [
                'inicio' => self::JUEVES_DE_PRUEBA.'T11:00:00-06:00',
                'fin' => self::JUEVES_DE_PRUEBA.'T12:00:00-06:00',
                'fecha' => self::JUEVES_DE_PRUEBA,
                'hora_inicio' => '11:00',
                'hora_fin' => '12:00',
            ])
            ->assertJsonPath('pedidos.0.lavanderia.id_publico', $this->lavanderia->id_publico)
            ->assertJsonPath('pedidos.0.lavanderia.nombre', $this->lavanderia->nombre)
            ->assertJsonPath('pedidos.0.lavanderia.calle', $this->lavanderia->calle);

        foreach (['recogida', 'entrega'] as $tipo) {
            $respuesta
                ->assertJsonPath("pedidos.0.{$tipo}.calle", 'Calle de prueba')
                ->assertJsonPath("pedidos.0.{$tipo}.numero_exterior", '123')
                ->assertJsonPath("pedidos.0.{$tipo}.colonia", 'Colonia de prueba')
                ->assertJsonPath("pedidos.0.{$tipo}.municipio", 'Municipio de prueba')
                ->assertJsonPath("pedidos.0.{$tipo}.referencias", 'Portón de prueba')
                ->assertJsonPath("pedidos.0.{$tipo}.latitud", 10.5)
                ->assertJsonPath("pedidos.0.{$tipo}.longitud", -119.5);
        }

        // Ni los datos de la cuenta que hizo el pedido, ni llaves internas, ni montos.
        $this->assertStringNotContainsString($cuenta->correo, $respuesta->getContent());
        $respuesta->assertJsonMissingPath('pedidos.0.usuario')
            ->assertJsonMissingPath('pedidos.0.id_pedidos')
            ->assertJsonMissingPath('pedidos.0.id_plazas')
            ->assertJsonMissingPath('pedidos.0.monto_estimado');

        // El mismo pedido, por su identificador.
        $this->pedidoComoRepartidor($repartidor, $temprano->id_publico)
            ->assertOk()
            ->assertJsonPath('pedido.id_publico', $temprano->id_publico)
            ->assertJsonPath('pedido.folio', $temprano->folio)
            ->assertJsonPath('pedido.lavanderia.nombre', $this->lavanderia->nombre);
    }

    #[TestDox('repartidor-E10: la lista trae los pedidos en curso que se le ofrecieron o que aceptó, también el que su lavandería ya aceptó o mueve; nunca uno que la lavandería rechazó')]
    public function test_repartidor_e10_la_lista_trae_los_pedidos_en_curso(): void
    {
        $repartidor = $this->repartidorActivo();
        $cuenta = $this->cuentaDeUsuario();
        $aceptado = $this->pedidoAceptadoDe($cuenta);
        $enProceso = $this->pedidoAceptadoDe($cuenta);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::RECOLECTADO);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::EN_PROCESO);
        $rechazado = $this->pedidoConfirmadoDe($cuenta);
        $this->rechazarComoLaLavanderia($rechazado);

        foreach ([$aceptado, $enProceso, $rechazado] as $pedido) {
            $this->ofrecerA($pedido, $repartidor);
        }

        $respuesta = $this->listaDelRepartidor($repartidor)->assertOk();

        $this->assertEqualsCanonicalizing([$aceptado->id_publico, $enProceso->id_publico], $this->idsDeLaListaDelRepartidor($respuesta));
    }

    #[TestDox('repartidor-E11, lado del servidor: un pedido que se le asigna aparece en la siguiente consulta de la lista, sin que el repartidor haga nada más que volver a pedirla')]
    public function test_repartidor_e11_el_pedido_nuevo_aparece_en_la_siguiente_consulta(): void
    {
        $repartidor = $this->repartidorActivo();
        $pedido = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());

        $this->listaDelRepartidor($repartidor)->assertOk()->assertExactJson(['pedidos' => []]);

        $this->ofrecerA($pedido, $repartidor);

        $this->assertSame([$pedido->id_publico], $this->idsDeLaListaDelRepartidor($this->listaDelRepartidor($repartidor)->assertOk()));
    }

    #[TestDox('repartidor-E12: un pedido asignado a otro repartidor y un pedido de otra plaza no aparecen en su lista, y pedirlos por su identificador responde 404 sin ningún dato, igual que uno que no existe')]
    public function test_repartidor_e12_no_ve_pedidos_de_otro_repartidor_ni_de_otra_plaza(): void
    {
        $repartidor = $this->repartidorActivo();
        $otro = $this->repartidorActivo();
        [$otraPlaza, $lavanderiaLejana] = $this->otraPlazaConLavanderia();
        $deLaOtraPlaza = $this->repartidorActivo($otraPlaza);
        $cuenta = $this->cuentaDeUsuario();

        $suyo = $this->pedidoConfirmadoDe($cuenta);
        $delOtro = $this->pedidoConfirmadoDe($cuenta);
        $lejano = $this->pedidoConfirmadoEn($lavanderiaLejana, $cuenta, longitud: -117.5);
        $sinOferta = $this->pedidoConfirmadoDe($cuenta);
        $this->ofrecerA($suyo, $repartidor);
        $this->ofrecerA($delOtro, $otro);
        $this->ofrecerA($lejano, $deLaOtraPlaza);

        $this->assertSame([$suyo->id_publico], $this->idsDeLaListaDelRepartidor($this->listaDelRepartidor($repartidor)->assertOk()));

        foreach ([$delOtro->id_publico, $lejano->id_publico, $sinOferta->id_publico, '01jd8x6m3v2c9q4w5e7r8t0y1z', 'NO-ES-ULID'] as $identificador) {
            $this->pedidoComoRepartidor($repartidor, $identificador)
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe el pedido.']);
        }

        // Cada quien ve lo suyo: el otro repartidor y el de la otra plaza, su pedido.
        $this->assertSame([$delOtro->id_publico], $this->idsDeLaListaDelRepartidor($this->listaDelRepartidor($otro)));
        $this->assertSame([$lejano->id_publico], $this->idsDeLaListaDelRepartidor($this->listaDelRepartidor($deLaOtraPlaza)));
        $this->pedidoComoRepartidor($deLaOtraPlaza, $suyo->id_publico)->assertNotFound();
    }

    #[TestDox('repartidor-E30: un pedido de su plaza que espera su pago, con una oferta para él, no aparece en su lista, pedirlo y aceptarlo responden 404; cuando la pasarela confirma el pago y queda Confirmado, aparece en su lista')]
    public function test_repartidor_e30_sin_pago_acreditado_no_se_le_ofrece(): void
    {
        $repartidor = $this->repartidorActivo();
        $porPagar = $this->pedidoPorPagarEn($this->lavanderia, $this->cuentaDeUsuario());
        $this->assertNull($porPagar->confirmado_en);
        $this->ofrecerA($porPagar, $repartidor);

        $this->listaDelRepartidor($repartidor)->assertOk()->assertExactJson(['pedidos' => []]);
        $this->pedidoComoRepartidor($repartidor, $porPagar->id_publico)->assertNotFound()->assertExactJson(['message' => 'No existe el pedido.']);
        $this->comoRepartidor($repartidor)->postJson("/api/repartidor/pedidos/{$porPagar->id_publico}/aceptar")
            ->assertNotFound()
            ->assertExactJson(['message' => 'No existe el pedido.']);
        $this->assertNull($porPagar->fresh()->id_repartidores);
        $this->assertSame('ofrecido', $porPagar->asignaciones()->sole()->estado);

        // La pasarela simulada acredita el pago y el cobro confirma el pedido (C-188).
        app(CobroSimulado::class)->pagar($porPagar);

        $respuesta = $this->listaDelRepartidor($repartidor)->assertOk();
        $this->assertSame([$porPagar->id_publico], $this->idsDeLaListaDelRepartidor($respuesta));
        $respuesta->assertJsonPath('pedidos.0.estado.codigo', EstadoPedido::CONFIRMADO)
            ->assertJsonPath('pedidos.0.folio', $porPagar->fresh()->folio);
    }

    #[TestDox('repartidor-E05 y E10: el repartidor que la administración da de alta en una plaza ve el pedido que se le asigna en ella')]
    public function test_repartidor_e05_y_e10_el_dado_de_alta_ve_el_pedido_de_su_plaza(): void
    {
        $pendiente = Repartidor::factory()->pendiente()->create();
        $pedido = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());

        app(EstadoDelRepartidor::class)->darDeAlta($pendiente, $this->plaza);
        $this->ofrecerA($pedido, $pendiente->fresh());

        $this->assertSame([$pedido->id_publico], $this->idsDeLaListaDelRepartidor($this->listaDelRepartidor($pendiente)->assertOk()));
    }
}
