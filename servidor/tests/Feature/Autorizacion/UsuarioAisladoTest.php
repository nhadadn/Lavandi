<?php

namespace Tests\Feature\Autorizacion;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\Direccion;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Autorizacion\Soporte\BarridoDeRutas;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Un usuario no ve los datos de otro: con su sesión, pide por cada ruta de la API el pedido y la dirección del otro por su
 * id_publico, y ninguna respuesta trae un dato del otro; las que los reciben responden como si no existieran
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, "Cada tipo de usuario accede únicamente a la información que le
 * corresponde"; ADR-0019, decisión 3). Las rutas salen de la tabla de rutas: una nueva entra sola.
 *
 * Reusa lo que ya prueba cada capacidad con su detalle: el pedido ajeno por su id_publico y por su folio
 * (confirmacion-E12, tests/Feature/Confirmacion/AislamientoTest.php) y la dirección ajena en la lista, el detalle y el
 * pedido (direcciones-E14, tests/Feature/Direcciones/AislamientoTest.php). Esta prueba recorre todas las rutas.
 *
 * El cobro del otro usuario se agrega cuando pago cree la tabla pagos y sus rutas: un parámetro nuevo hace fallar el
 * barrido hasta que tenga sus datos aquí.
 *
 * Escenario: autorizacion-E07 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Tarea 2.1
 */
class UsuarioAisladoTest extends TestCase
{
    use AgendaDePrueba;
    use BarridoDeRutas;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $ana;

    private Usuario $beto;

    private Direccion $direccionDeAna;

    private Direccion $direccionDeBeto;

    private Pedido $pedidoDeAna;

    private Pedido $pedidoDeBeto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        // Dos usuarios, cada uno con una dirección guardada y un pedido confirmado a la misma lavandería.
        [$this->ana, $this->direccionDeAna, $this->pedidoDeAna] = $this->usuarioConPedido('Ana', '6189990001');
        [$this->beto, $this->direccionDeBeto, $this->pedidoDeBeto] = $this->usuarioConPedido('Beto', '6189990002');
    }

    #[TestDox('autorizacion-E07: el primer usuario pide por cada ruta de la API, con su id_publico, el pedido y la dirección del segundo: ninguna respuesta trae un dato del segundo, y las que los reciben responden que no existen')]
    public function test_autorizacion_e07_un_usuario_no_ve_los_datos_de_otro_en_ninguna_ruta(): void
    {
        $this->assertSame([], $this->fugasDeAna());
    }

    #[TestDox('autorizacion-E07: el primer usuario sí ve lo suyo: su pedido y su dirección por su id_publico, y su pedido en su lista')]
    public function test_autorizacion_e07_el_usuario_si_ve_lo_suyo(): void
    {
        $this->llamarSinEfecto($this->ana, 'GET', "/api/pedidos/{$this->pedidoDeAna->id_publico}")
            ->assertOk()
            ->assertJsonPath('pedido.folio', $this->pedidoDeAna->folio);
        $this->llamarSinEfecto($this->ana, 'GET', "/api/direcciones/{$this->direccionDeAna->id_publico}")
            ->assertOk()
            ->assertJsonPath('direccion.id_publico', $this->direccionDeAna->id_publico);
        $this->assertSame(
            [$this->pedidoDeAna->id_publico],
            array_column($this->llamarSinEfecto($this->ana, 'GET', '/api/pedidos')->assertOk()->json('pedidos'), 'id_publico'),
        );
    }

    #[TestDox('autorizacion-E07: una ruta que devolviera el pedido del segundo hace fallar el barrido, que nombra la ruta, el rol y el dato')]
    public function test_autorizacion_e07_una_ruta_con_fuga_hace_fallar_el_barrido(): void
    {
        Route::prefix('api')->middleware(['api', 'auth:sanctum', 'autorizar:usuario'])
            ->get('prueba/fuga', fn () => ['pedidos' => Pedido::query()->pluck('folio')]);

        $this->assertContains(
            'GET api/prueba/fuga · usuario · sin parámetros: trae el folio del pedido de Beto',
            $this->fugasDeAna(),
        );
    }

    /**
     * Las fugas de Ana en todas las rutas, pidiendo el pedido y la dirección de Beto.
     *
     * @return list<string>
     */
    private function fugasDeAna(): array
    {
        return $this->fugasDe(
            $this->ana,
            Rol::USUARIO,
            ajenos: [
                'pedido' => [$this->pedidoDeBeto->id_publico => 'el pedido de Beto'],
                // El cobro del pedido de Beto, que consulta "Procesando pago" (pago-E08).
                'pago' => [$this->pedidoDeBeto->cobro->id_publico => 'el cobro del pedido de Beto'],
                'direccion' => [$this->direccionDeBeto->id_publico => 'la dirección de Beto'],
            ],
            // El perfil de las lavanderías es público en el Módulo A (Módulo A #5); el catálogo, de todos.
            publicos: ['lavanderia' => $this->lavanderia->id_publico, 'codigo_postal' => '00000'],
            datosAjenos: [
                $this->pedidoDeBeto->id_publico => 'el id_publico del pedido de Beto',
                $this->pedidoDeBeto->folio => 'el folio del pedido de Beto',
                $this->pedidoDeBeto->cobro->id_publico => 'el id_publico del cobro del pedido de Beto',
                $this->direccionDeBeto->id_publico => 'el id_publico de la dirección de Beto',
                'CalleDeBetoAislado' => 'la calle de Beto',
                'ReferenciasDeBetoAislado' => 'las referencias de Beto',
                'CasaDeBetoAislado' => 'el alias de la dirección de Beto',
                $this->beto->correo => 'el correo de Beto',
                'BetoAislado' => 'el nombre de Beto',
                '6189990002' => 'el teléfono de Beto',
            ],
            // Lo que una ruta podría usar para pedir un dato de Beto en el cuerpo o en la consulta, con un pedido válido.
            datos: [
                'pedido' => $this->pedidoDeBeto->id_publico,
                'direccion' => $this->direccionDeBeto->id_publico,
                'recogida' => $this->direccionDeBeto->id_publico,
                'entrega' => $this->direccionDeBeto->id_publico,
                'lavanderia' => $this->lavanderia->id_publico,
                'servicio' => 'estandar',
                'ventana' => ['inicio' => self::JUEVES_DE_PRUEBA.'T11:00:00-06:00', 'fin' => self::JUEVES_DE_PRUEBA.'T12:00:00-06:00'],
                'latitud' => 10.5,
                'longitud' => -119.5,
                'fecha' => self::JUEVES_DE_PRUEBA,
            ],
        );
    }

    /**
     * Una cuenta de usuario con su nombre y su teléfono, una dirección guardada como favorita y un pedido confirmado
     * desde ella, confirmado por su cobro pagado con la pasarela simulada.
     *
     * @return array{Usuario, Direccion, Pedido}
     */
    private function usuarioConPedido(string $nombre, string $telefono): array
    {
        $cuenta = $this->cuentaDeUsuario();
        $cuenta->forceFill(['nombre' => "{$nombre}Aislado", 'telefono' => $telefono])->save();

        $direccion = $this->direccionDe($cuenta, [
            'alias' => "CasaDe{$nombre}Aislado",
            'es_favorita' => true,
            'calle' => "CalleDe{$nombre}Aislado",
            'referencias' => "ReferenciasDe{$nombre}Aislado",
        ]);

        $id = $this->aceptarResumen($cuenta, $this->resumen($direccion))->assertCreated()->json('pedido.id_publico');
        $pedido = app(CobroSimulado::class)->pagar(Pedido::query()->where('id_publico', $id)->sole())->fresh();

        return [$cuenta->fresh(), $direccion, $pedido];
    }
}
