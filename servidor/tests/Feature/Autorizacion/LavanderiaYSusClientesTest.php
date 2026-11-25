<?php

namespace Tests\Feature\Autorizacion;

use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Autorizacion\Soporte\RutasDelModuloB;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La lavandería solo ve a los usuarios que le pidieron, y solo dentro de sus pedidos: "Un socio lavandería no puede
 * consultar [...] los [datos] de los usuarios que no le han hecho pedidos" (NSV-2026-001-v8.pdf:20:ANEXO A, Sección
 * A.3).
 *
 * Hoy el pedido de la lavandería no trae ningún dato del usuario: cuáles ve, dentro del pedido, lo fijan DD-022 y DD-014
 * (pedidos-lavanderia-E04, tarea 3.1). Cuando E04 los agregue, esta prueba sigue exigiendo que no salgan fuera del pedido
 * ni sean de un usuario que no le pidió.
 *
 * Escenario: autorizacion-E10 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Tarea 2.4
 */
class LavanderiaYSusClientesTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RutasDelModuloB;

    /** Le hizo un pedido a la lavandería. */
    private Usuario $cliente;

    /** Nunca le ha pedido a la lavandería: su pedido es con otra. */
    private Usuario $ajeno;

    private Pedido $delCliente;

    private Pedido $delAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->cliente = $this->cuentaDeUsuario();
        $this->ajeno = $this->cuentaDeUsuario();
        $this->delCliente = $this->pedidoConfirmadoDe($this->cliente);
        $this->delAjeno = $this->pedidoConfirmadoEn($this->otraLavanderia(), $this->ajeno);
    }

    #[TestDox('autorizacion-E10: la lavandería consulta sus pedidos y recibe el del usuario que le pidió, sin ningún dato del usuario que nunca le ha pedido')]
    public function test_autorizacion_e10_sus_pedidos_no_traen_datos_de_quien_no_le_pidio(): void
    {
        $socio = $this->lavanderia->administrador;
        $lista = $this->listaDeLaLavanderia($socio)->assertOk();
        $pedido = $this->pedidoComoLavanderia($socio, $this->delCliente->id_publico)->assertOk();

        $this->assertSame([$this->delCliente->id_publico], $this->idsDeLaLista($lista));
        foreach ([$lista, $pedido] as $respuesta) {
            $this->assertSinDatosDe($this->ajeno, $respuesta->getContent());
            $this->assertStringNotContainsString($this->delAjeno->id_publico, $respuesta->getContent());
        }
    }

    #[TestDox('autorizacion-E10: la lavandería pide por cada ruta del Módulo B el pedido que el otro usuario hizo con otra lavandería: ninguna devuelve un dato suyo')]
    public function test_autorizacion_e10_el_pedido_del_otro_usuario_con_otra_lavanderia_no_le_llega(): void
    {
        foreach ($this->rutasDeLaLavanderiaConPedido() as $nombre => $ruta) {
            $respuesta = $this->llamarConElPedido($this->lavanderia->administrador, $nombre, $ruta, $this->delAjeno->id_publico);

            $respuesta->assertNotFound();
            $this->assertSame(['message' => 'No existe el pedido.'], $respuesta->json(), $nombre);
            $this->assertSinDatosDe($this->ajeno, $respuesta->getContent(), $nombre);
        }
    }

    #[TestDox('autorizacion-E10: la lavandería pide al otro usuario por su id_publico en cada ruta de la API que recibe un identificador: ninguna le devuelve un dato suyo')]
    public function test_autorizacion_e10_pedir_al_otro_usuario_por_su_id_publico_no_devuelve_nada(): void
    {
        $socio = $this->lavanderia->administrador;
        $token = $socio->createToken('app')->plainTextToken;
        $rutas = 0;

        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            if (! Str::startsWith($ruta->uri(), 'api/') || ! str_contains($ruta->uri(), '{')) {
                continue;
            }

            foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
                $rutas++;
                $this->app['auth']->forgetGuards();
                $respuesta = $this->withToken($token)->json(
                    $metodo,
                    '/'.preg_replace('/\{[^}]+\}/', $this->ajeno->id_publico, $ruta->uri()),
                    ['motivo' => 'Motivo de prueba.', 'estado' => 'recolectado'],
                );

                $this->assertContains($respuesta->status(), [403, 404, 422], "{$metodo} {$ruta->uri()}");
                $this->assertSinDatosDe($this->ajeno, $respuesta->getContent(), "{$metodo} {$ruta->uri()}");
            }
        }

        $this->assertGreaterThan(0, $rutas);
    }

    #[TestDox('autorizacion-E10: el pedido del usuario que le pidió no trae sus datos fuera de lo que fije pedidos-lavanderia-E04: hoy, ninguno')]
    public function test_autorizacion_e10_el_pedido_del_cliente_no_trae_datos_del_usuario_todavia(): void
    {
        $respuesta = $this->pedidoComoLavanderia($this->lavanderia->administrador, $this->delCliente->id_publico)->assertOk();

        $this->assertSinDatosDe($this->cliente, $respuesta->getContent());
        $respuesta->assertJsonMissingPath('pedido.usuario');
    }

    private function assertSinDatosDe(Usuario $usuario, string $contenido, string $donde = ''): void
    {
        foreach ([$usuario->correo, $usuario->id_publico] as $dato) {
            $this->assertStringNotContainsStringIgnoringCase($dato, $contenido, $donde);
        }
    }
}
