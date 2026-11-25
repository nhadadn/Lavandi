<?php

namespace Tests\Feature\Historial;

use App\Models\Calificacion;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Historial\Soporte\HistorialDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La calificación del servicio, POST /api/pedidos/{id_publico}/calificacion: de 1 a 5 estrellas con un comentario
 * opcional, solo sobre un pedido propio, guardada como calificación de la lavandería del pedido
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14; NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación
 * por tipo de usuario). El historial muestra que el pedido ya está calificado.
 *
 * Todos los escenarios parten de un pedido Entregado, el caso que vale con cualquier respuesta a BR-25
 * (openspec/changes/historial-y-calificacion/design.md, decisión 3). Desde qué estado y hasta cuándo se califica, y si una
 * calificación se cambia, siguen bloqueados (historial-E10, BR-25 y DD-107); a quién se califica, también (historial-E11,
 * DD-052). Las dos pruebas marcadas como "lectura provisional" fijan lo que hace el servidor mientras tanto (design.md,
 * "La calificación: lectura provisional"): no son historial-E10, y la tarea 3.2 las reemplaza.
 *
 * Escenarios: historial-E06, historial-E07, historial-E08, historial-E09 e historial-E12 · Spec:
 * openspec/changes/historial-y-calificacion/specs/historial/spec.md · Tarea 1.3
 */
class CalificacionTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use HistorialDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->cuenta = $this->cuentaDeUsuario();
    }

    #[TestDox('historial-E06: el usuario califica su pedido entregado con 4 estrellas y sin comentario: queda guardada así, y el historial muestra el pedido calificado')]
    public function test_historial_e06_califica_sin_comentario_y_el_historial_lo_muestra_calificado(): void
    {
        $pedido = $this->entregado();

        // Antes de calificar, el historial lo muestra sin calificación.
        $this->pedirHistorial($this->cuenta)->assertOk()->assertJsonPath('pedidos.0.calificacion', null);

        $this->calificar($this->cuenta, $pedido->id_publico, ['estrellas' => 4])
            ->assertCreated()
            ->assertExactJson(['calificacion' => ['estrellas' => 4, 'comentario' => null]]);

        $calificacion = Calificacion::query()->sole();
        $this->assertSame(4, $calificacion->estrellas);
        $this->assertNull($calificacion->comentario);

        $this->pedirHistorial($this->cuenta)
            ->assertOk()
            ->assertJsonPath('pedidos.0.id_publico', $pedido->id_publico)
            ->assertJsonPath('pedidos.0.calificacion', ['estrellas' => 4, 'comentario' => null]);
    }

    #[TestDox('historial-E06: un comentario vacío o solo con espacios cuenta como sin comentario')]
    public function test_historial_e06_un_comentario_vacio_cuenta_como_sin_comentario(): void
    {
        foreach (['', '   ', null] as $vacio) {
            $pedido = $this->entregado();

            $this->calificar($this->cuenta, $pedido->id_publico, ['estrellas' => 3, 'comentario' => $vacio])
                ->assertCreated()
                ->assertExactJson(['calificacion' => ['estrellas' => 3, 'comentario' => null]]);

            $this->assertNull($pedido->calificaciones()->sole()->comentario);
        }
    }

    #[TestDox('historial-E07: el usuario califica su pedido entregado con 5 estrellas y un comentario: queda guardado tal como lo escribió')]
    public function test_historial_e07_califica_con_comentario_tal_como_lo_escribio(): void
    {
        $pedido = $this->entregado();
        $comentario = "La ropa llegó limpia y bien doblada.\n\nEl repartidor, puntual: ¡gracias!  Volveré a pedir 😊";

        $this->calificar($this->cuenta, $pedido->id_publico, ['estrellas' => 5, 'comentario' => $comentario])
            ->assertCreated()
            ->assertExactJson(['calificacion' => ['estrellas' => 5, 'comentario' => $comentario]]);

        $calificacion = Calificacion::query()->sole();
        $this->assertSame(5, $calificacion->estrellas);
        $this->assertSame($comentario, $calificacion->comentario);

        $this->pedirHistorial($this->cuenta)
            ->assertOk()
            ->assertJsonPath('pedidos.0.calificacion', ['estrellas' => 5, 'comentario' => $comentario]);
    }

    #[TestDox('historial-E08: con 0 estrellas, con 6 o sin estrellas, la API la rechaza con el error en su campo y no guarda ninguna calificación')]
    public function test_historial_e08_una_calificacion_fuera_de_la_escala_se_rechaza(): void
    {
        $pedido = $this->entregado();
        $fueraDeLaEscala = 'Las estrellas van de 1 a 5.';
        $sinEstrellas = 'Elige de 1 a 5 estrellas.';
        $noEsEntero = 'Las estrellas van como un número entero de 1 a 5.';

        $casos = [
            [['estrellas' => 0], $fueraDeLaEscala],
            [['estrellas' => 6], $fueraDeLaEscala],
            [['estrellas' => -1, 'comentario' => 'Comentario de prueba.'], $fueraDeLaEscala],
            [[], $sinEstrellas],
            [['estrellas' => null], $sinEstrellas],
            [['comentario' => 'Comentario de prueba.'], $sinEstrellas],
            [['estrellas' => 4.5], $noEsEntero],
            [['estrellas' => 'cuatro'], $noEsEntero],
        ];

        foreach ($casos as [$cuerpo, $mensaje]) {
            $this->calificar($this->cuenta, $pedido->id_publico, $cuerpo)
                ->assertUnprocessable()
                ->assertExactJson(['message' => $mensaje, 'errors' => ['estrellas' => [$mensaje]]]);
        }

        $this->assertSame(0, Calificacion::query()->count());
    }

    #[TestDox('historial-E08: un comentario que no es texto, o más largo que el límite técnico, se rechaza en su campo y no guarda nada')]
    public function test_historial_e08_un_comentario_que_no_es_texto_o_demasiado_largo_se_rechaza(): void
    {
        $pedido = $this->entregado();
        $maximo = (int) config('pedidos.comentario_de_calificacion_maximo');

        $this->calificar($this->cuenta, $pedido->id_publico, ['estrellas' => 4, 'comentario' => ['no', 'es', 'texto']])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'El comentario va en texto.',
                'errors' => ['comentario' => ['El comentario va en texto.']],
            ]);

        $largo = "El comentario no puede pasar de {$maximo} caracteres.";
        $this->calificar($this->cuenta, $pedido->id_publico, ['estrellas' => 4, 'comentario' => str_repeat('a', $maximo + 1)])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $largo, 'errors' => ['comentario' => [$largo]]]);

        $this->assertSame(0, Calificacion::query()->count());

        // Justo en el límite, entra.
        $this->calificar($this->cuenta, $pedido->id_publico, ['estrellas' => 4, 'comentario' => str_repeat('a', $maximo)])
            ->assertCreated();
    }

    #[TestDox('historial-E09: el pedido entregado de otro usuario no se califica: responde igual que uno que no existe y no se guarda ninguna calificación')]
    public function test_historial_e09_no_se_califica_el_pedido_de_otro_usuario(): void
    {
        $ajeno = $this->entregado($this->cuentaDeUsuario());
        $noExiste = ['message' => 'No existe el pedido.'];

        $this->calificar($this->cuenta, $ajeno->id_publico, ['estrellas' => 5])
            ->assertNotFound()
            ->assertExactJson($noExiste);

        // Igual que un ULID que no es de ningún pedido, y que un valor que no es ULID, como el folio del pedido ajeno.
        $this->calificar($this->cuenta, Str::lower((string) Str::ulid()), ['estrellas' => 5])
            ->assertNotFound()
            ->assertExactJson($noExiste);
        $this->calificar($this->cuenta, $ajeno->folio, ['estrellas' => 5])
            ->assertNotFound()
            ->assertExactJson($noExiste);

        $this->assertSame(0, Calificacion::query()->count());
        $this->assertSame(0, $ajeno->calificaciones()->count());
    }

    #[TestDox('historial-E09: el pedido de otro usuario responde que no existe antes de revisar el cuerpo: sin estrellas o fuera de la escala, también 404 y no 422')]
    public function test_historial_e09_el_pedido_de_otro_usuario_responde_404_antes_de_revisar_el_cuerpo(): void
    {
        $ajeno = $this->entregado($this->cuentaDeUsuario());

        foreach ([[], ['estrellas' => 9], ['estrellas' => 5, 'comentario' => ['no', 'es', 'texto']]] as $cuerpo) {
            $this->calificar($this->cuenta, $ajeno->id_publico, $cuerpo)
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe el pedido.']);
        }

        $this->assertSame(0, Calificacion::query()->count());
    }

    #[TestDox('historial-E09: sin sesión responde 401, y una cuenta sin el rol de usuario, como la de la lavandería del pedido, 403; ninguna guarda calificación')]
    public function test_historial_e09_sin_sesion_o_sin_el_rol_de_usuario_no_se_califica(): void
    {
        $pedido = $this->entregado();

        $this->app['auth']->forgetGuards();
        $this->withoutToken()
            ->postJson("/api/pedidos/{$pedido->id_publico}/calificacion", ['estrellas' => 5])
            ->assertUnauthorized();

        $this->calificar($this->cuentaDeLaLavanderia($pedido), $pedido->id_publico, ['estrellas' => 5])
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta no tiene acceso.');

        $this->calificar(Usuario::factory()->conRol(Rol::REPARTIDOR)->create(), $pedido->id_publico, ['estrellas' => 5])
            ->assertForbidden();

        $this->assertSame(0, Calificacion::query()->count());
    }

    #[TestDox('historial-E12: la calificación del servicio queda con el pedido, con su lavandería, en su plaza y de la cuenta que lo hizo, y sin ningún repartidor')]
    public function test_historial_e12_la_calificacion_queda_con_la_lavanderia_del_pedido(): void
    {
        // Otra lavandería de la misma plaza: la calificación no se va a ella.
        $otra = $this->lavanderiaDeNoche();
        $pedido = $this->entregado();
        $this->assertNotNull($pedido->id_repartidores, 'El pedido lo entregó un repartidor.');

        $this->calificar($this->cuenta, Str::upper($pedido->id_publico), ['estrellas' => 4])->assertCreated();

        $calificacion = Calificacion::query()->sole();
        $this->assertSame($pedido->getKey(), $calificacion->id_pedidos);
        $this->assertSame($this->lavanderia->getKey(), $calificacion->id_lavanderias);
        $this->assertSame($pedido->id_lavanderias, $calificacion->id_lavanderias);
        $this->assertSame($pedido->id_plazas, $calificacion->id_plazas);
        $this->assertSame($this->cuenta->getKey(), $calificacion->id_usuarios);
        $this->assertNull($calificacion->id_repartidores);
        $this->assertSame(0, $otra->calificaciones()->count());
        $this->assertSame(1, $this->lavanderia->calificaciones()->count());
    }

    #[TestDox('historial-E06, lectura provisional mientras BR-25 no responda: un pedido propio que todavía no está Entregado responde 409 y no guarda nada')]
    public function test_historial_e06_lectura_provisional_un_pedido_sin_entregar_no_se_califica_todavia(): void
    {
        $porPagar = $this->pedidoPorPagarEl($this->cuenta, self::JUEVES_DE_PRUEBA);
        $confirmado = $this->pedidoConfirmadoEl($this->cuenta, self::JUEVES_DE_PRUEBA);
        $enProceso = $this->pedidoConfirmadoEl($this->cuenta, self::JUEVES_DE_PRUEBA);
        $this->aceptarComoLaLavanderia($enProceso);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::RECOLECTADO);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::EN_PROCESO);
        $rechazado = $this->pedidoConfirmadoEl($this->cuenta, self::JUEVES_DE_PRUEBA);
        $this->rechazarComoLaLavanderia($rechazado);

        foreach ([$porPagar, $confirmado, $enProceso, $rechazado] as $pedido) {
            $this->calificar($this->cuenta, $pedido->id_publico, ['estrellas' => 4])
                ->assertConflict()
                ->assertExactJson(['message' => 'Este pedido todavía no se puede calificar.']);
        }

        $this->assertSame(0, Calificacion::query()->count());
    }

    #[TestDox('historial-E06, lectura provisional mientras BR-25 no responda: un pedido ya calificado responde 409 y su calificación queda como estaba')]
    public function test_historial_e06_lectura_provisional_un_pedido_ya_calificado_no_se_vuelve_a_calificar(): void
    {
        $pedido = $this->entregado();
        $this->calificar($this->cuenta, $pedido->id_publico, ['estrellas' => 4, 'comentario' => 'Primera.'])
            ->assertCreated();

        $this->calificar($this->cuenta, $pedido->id_publico, ['estrellas' => 1, 'comentario' => 'Segunda.'])
            ->assertConflict()
            ->assertExactJson(['message' => 'Este pedido ya está calificado.']);

        $calificacion = Calificacion::query()->sole();
        $this->assertSame(4, $calificacion->estrellas);
        $this->assertSame('Primera.', $calificacion->comentario);
    }

    /**
     * Un pedido de la cuenta, de la cuenta de la prueba por omisión, confirmado y llevado hasta Entregado por su
     * lavandería y su repartidor.
     */
    private function entregado(?Usuario $cuenta = null): Pedido
    {
        return $this->entregar($this->pedidoConfirmadoEl($cuenta ?? $this->cuenta, self::JUEVES_DE_PRUEBA));
    }

    /**
     * La app manda la calificación del pedido con la sesión de la cuenta. Olvida la cuenta de la petición anterior
     * (forgetGuards), como dos peticiones reales al servidor.
     *
     * @param  array<string, mixed>  $cuerpo
     */
    private function calificar(Usuario $cuenta, string $pedido, array $cuerpo): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)
            ->postJson("/api/pedidos/{$pedido}/calificacion", $cuerpo);
    }
}
