<?php

namespace Tests\Feature\Autorizacion;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\Direccion;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\Rol;
use Database\Factories\RepartidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Autorizacion\Soporte\BarridoDeRutas;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * Un repartidor solo ve los pedidos que tiene asignados: no ve el pedido de otro repartidor, ni uno sin asignar, ni la
 * entrega de otro, ni los datos del otro repartidor ni de los usuarios de esos pedidos (NSV-2026-001-v8.pdf:18:ANEXO A,
 * Sección A.3, Módulo C #2, "pedidos asignados"; :20, Separación por tipo de usuario; ADR-0019, decisión 3). Lo decide
 * App\Autorizacion\Autorizacion::pedidosDelRepartidor() y entregasDelRepartidor().
 *
 * Las rutas salen de la tabla de rutas, con el barrido de Soporte\BarridoDeRutas: una ruta nueva entra sola. El detalle de
 * cada ruta del Módulo C lo prueba repartidor (repartidor-E12, E16, E21, E25 y E28); el pedido de otra plaza,
 * repartidor-E12 y el barrido de autorizacion-E14. Quién asigna espera DD-014: la oferta se crea directo en la base
 * (RepartidorDePrueba::ofrecerA()).
 *
 * Escenario: autorizacion-E11 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Tarea 2.5
 */
class RepartidorAsignadoTest extends TestCase
{
    use AgendaDePrueba;
    use BarridoDeRutas;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;

    private Repartidor $primero;

    private Repartidor $segundo;

    /** El pedido que se le ofreció al primero. */
    private Pedido $delPrimero;

    /** El pedido que se le ofreció al segundo y todavía no acepta: aún no es de ningún repartidor. */
    private Pedido $ofrecidoAlSegundo;

    /** El pedido que el segundo aceptó y lleva. */
    private Pedido $delSegundo;

    /** La entrega completada del segundo, en su historial. */
    private Pedido $entregaDelSegundo;

    /** Un pedido de la misma plaza que no se le ofreció a nadie. */
    private Pedido $sinAsignar;

    /** @var array<string, Direccion> La dirección guardada del usuario de cada pedido, por su marca. */
    private array $direcciones = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Los dos repartidores y los cinco pedidos son de la plaza de prepararPedido() y de su lavandería.
        $this->prepararPedido();
        $this->primero = $this->repartidorConDatos('Uno', '6187770001');
        $this->segundo = $this->repartidorConDatos('Dos', '6187770002');

        $this->delPrimero = $this->pedidoConDatos('DelPrimero');
        $this->ofrecerA($this->delPrimero, $this->primero);

        $this->ofrecidoAlSegundo = $this->pedidoConDatos('OfrecidoAlSegundo');
        $this->ofrecerA($this->ofrecidoAlSegundo, $this->segundo);

        $this->delSegundo = $this->pedidoConDatos('DelSegundo');
        $this->repartidorQueLoAcepta($this->delSegundo, $this->segundo);

        $this->entregaDelSegundo = $this->pedidoConDatos('EntregaDelSegundo');
        $this->aceptarComoLaLavanderia($this->entregaDelSegundo);
        $this->repartidorQueLoAcepta($this->entregaDelSegundo, $this->segundo);
        $this->llevarHastaEntregado($this->entregaDelSegundo);

        $this->sinAsignar = $this->pedidoConDatos('SinAsignar');
    }

    #[TestDox('autorizacion-E11: el primer repartidor pide por cada ruta de la API los pedidos del segundo (el ofrecido, el que lleva y su entrega) y el pedido sin asignar: ninguna respuesta trae un dato de esos pedidos ni del segundo repartidor, y las que los reciben responden que no existen')]
    public function test_autorizacion_e11_un_repartidor_no_ve_lo_que_no_tiene_asignado_en_ninguna_ruta(): void
    {
        $this->assertSame([], $this->fugasDelPrimero());
    }

    #[TestDox('autorizacion-E11: la lista del primer repartidor solo trae su pedido asignado, y su historial y sus estadísticas no traen ni cuentan la entrega del segundo; los del segundo sí')]
    public function test_autorizacion_e11_la_lista_y_el_historial_del_primero_solo_traen_lo_suyo(): void
    {
        $this->assertSame([$this->delPrimero->id_publico], $this->idsDeLaListaDelRepartidor($this->listaDelRepartidor($this->primero)->assertOk()));
        $this->historialDelRepartidor($this->primero)->assertOk()->assertExactJson(['pedidos' => []]);
        $this->estadisticasDelRepartidor($this->primero)->assertOk()->assertExactJson(['entregas_del_dia' => 0, 'entregas_de_la_semana' => 0]);

        // El segundo sí ve los suyos: la prueba no pasa en vacío.
        $this->assertEqualsCanonicalizing(
            [$this->ofrecidoAlSegundo->id_publico, $this->delSegundo->id_publico],
            $this->idsDeLaListaDelRepartidor($this->listaDelRepartidor($this->segundo)->assertOk()),
        );
        $this->assertSame(
            [$this->entregaDelSegundo->id_publico],
            array_column($this->historialDelRepartidor($this->segundo)->assertOk()->json('pedidos'), 'id_publico'),
        );
        $this->estadisticasDelRepartidor($this->segundo)->assertOk()->assertExactJson(['entregas_del_dia' => 1, 'entregas_de_la_semana' => 1]);
    }

    #[TestDox('autorizacion-E11: una ruta del Módulo C que devolviera los pedidos sin asignar hace fallar el barrido, que nombra la ruta, el rol y el dato')]
    public function test_autorizacion_e11_una_ruta_con_fuga_hace_fallar_el_barrido(): void
    {
        Route::prefix('api')->middleware(['api', 'auth:sanctum', 'autorizar:repartidor'])
            ->get('repartidor/prueba/fuga', fn () => ['pedidos' => Pedido::query()->whereNull('id_repartidores')->pluck('folio')]);

        $this->assertContains(
            'GET api/repartidor/prueba/fuga · repartidor · sin parámetros: trae el folio del pedido sin asignar',
            $this->fugasDelPrimero(),
        );
    }

    /**
     * Las fugas del primer repartidor en todas las rutas, pidiendo el pedido del segundo, su entrega y el sin asignar.
     *
     * @return list<string>
     */
    private function fugasDelPrimero(): array
    {
        $ajenos = [
            $this->ofrecidoAlSegundo->id_publico => 'el pedido ofrecido al segundo',
            $this->delSegundo->id_publico => 'el pedido del segundo',
            $this->entregaDelSegundo->id_publico => 'la entrega del segundo',
            $this->sinAsignar->id_publico => 'el pedido sin asignar',
        ];
        $datosAjenos = [
            $this->segundo->id_publico => 'el id_publico del segundo repartidor',
            $this->segundo->cuenta->correo => 'el correo del segundo repartidor',
            'RepartidorDosAsignado' => 'el nombre del segundo repartidor',
            '6187770002' => 'el teléfono del segundo repartidor',
        ];

        foreach ([
            'del pedido ofrecido al segundo' => $this->ofrecidoAlSegundo,
            'del pedido del segundo' => $this->delSegundo,
            'de la entrega del segundo' => $this->entregaDelSegundo,
            'del pedido sin asignar' => $this->sinAsignar,
        ] as $de => $pedido) {
            $datosAjenos[$pedido->id_publico] = "el id_publico {$de}";
            $datosAjenos[$pedido->folio] = "el folio {$de}";
        }

        foreach (['OfrecidoAlSegundo', 'DelSegundo', 'EntregaDelSegundo', 'SinAsignar'] as $marca) {
            $datosAjenos["Calle{$marca}Asignado"] = "la calle del usuario de {$marca}";
            $datosAjenos["Usuario{$marca}Asignado"] = "el nombre del usuario de {$marca}";
        }

        return $this->fugasDe(
            $this->primero->cuenta,
            Rol::REPARTIDOR,
            ajenos: [
                'pedido' => $ajenos,
                // Las rutas del cobro son del Módulo A: el del pedido sin asignar.
                'pago' => [$this->sinAsignar->cobro->id_publico => 'el cobro del pedido sin asignar'],
                // Las rutas de la dirección son del Módulo A: la del usuario del pedido sin asignar.
                'direccion' => [$this->direcciones['SinAsignar']->id_publico => 'la dirección del usuario sin asignar'],
            ],
            publicos: ['lavanderia' => $this->lavanderia->id_publico, 'codigo_postal' => '00000'],
            datosAjenos: $datosAjenos,
            datos: ['pedido' => $this->sinAsignar->id_publico],
        );
    }

    /**
     * Un repartidor activo de la plaza de prueba, con su nombre, su teléfono y su correo de prueba.
     */
    private function repartidorConDatos(string $marca, string $telefono): Repartidor
    {
        $cuenta = RepartidorFactory::cuentaDeRepartidor([
            'nombre' => "Repartidor{$marca}Asignado",
            'telefono' => $telefono,
            'correo' => 'repartidor.'.strtolower($marca).'.asignado@ejemplo.com',
        ]);

        return Repartidor::factory()->en($this->plaza)->deLaCuenta($cuenta)->create();
    }

    /**
     * Un pedido confirmado a la lavandería de prueba, de un usuario con su nombre y su dirección de recogida de prueba,
     * pagado con la pasarela simulada.
     */
    private function pedidoConDatos(string $marca): Pedido
    {
        $cuenta = $this->cuentaDeUsuario();
        $cuenta->forceFill(['nombre' => "Usuario{$marca}Asignado"])->save();
        $recogida = $this->direcciones[$marca] = $this->direccionDe($cuenta, ['calle' => "Calle{$marca}Asignado"]);

        $id = $this->aceptarResumen($cuenta, $this->resumen($recogida))->assertCreated()->json('pedido.id_publico');

        return app(CobroSimulado::class)->pagar(Pedido::query()->where('id_publico', $id)->sole())->fresh();
    }
}
