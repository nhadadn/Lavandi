<?php

namespace Tests\Feature\Autorizacion;

use App\Autorizacion\Autorizacion;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\ReporteSoporte;
use App\Models\Rol;
use App\Models\Usuario;
use App\Soporte\RecepcionDeReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Autorizacion\Soporte\BarridoDeRutas;
use Tests\Feature\Autorizacion\Soporte\DosPlazasDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El barrido contra fugas: con datos sembrados en dos plazas, la sesión de cada rol recorre todas las rutas de la API
 * pidiendo datos que no le corresponden, y ninguna respuesta trae datos de otro socio, de otro usuario ni de otra plaza;
 * si alguna los trae, la prueba falla y nombra la ruta, el rol y el dato (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3;
 * :10, Cláusula X, "medidas de seguridad técnicas razonables"; SEG-3; vault/arquitectura/c3-api.md, la prueba
 * anti-fuga).
 *
 * El sembrado es Soporte\DosPlazasDePrueba: cuatro cadenas que no se cruzan, dos por plaza. Cada rol entra con la cuenta
 * de la cadena UnoA (o la de administración) y pide, en cada parámetro de cada ruta y en el cuerpo, los pedidos, las
 * direcciones y las lavanderías de las otras tres cadenas (Soporte\BarridoDeRutas). Lo que el contrato hace público, el
 * perfil de las lavanderías en el Módulo A (Módulo A #5), no cuenta como ajeno para el usuario; la plaza no restringe al
 * usuario (autorizacion-E08), y sí a los socios (autorizacion-E12).
 *
 * Las rutas salen de la tabla de rutas: una ruta nueva entra sola, y una con un parámetro que el barrido no conoce hace
 * fallar la prueba. Corre en su propio paso de la integración continua (SEG-3).
 *
 * Al sembrado se agrega el reporte de Soporte de una cuenta sin plaza, que se guarda sin ella (perfil-usuario-E17): no
 * es de ninguna plaza, así que ninguna ruta se lo da a nadie, y del componente solo lo recibe la administración, que es
 * una para todas las plazas. No hay una administración por plaza (autorizacion-por-rol, Non-Goals): los roles que llevan
 * plaza son los dos socios, y a ellos tampoco.
 *
 * Escenarios: autorizacion-E14 y perfil-usuario-E17 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/
 * spec.md · Tarea 4.1
 */
#[Group('barrido-contra-fugas')]
class BarridoContraFugasTest extends TestCase
{
    use AgendaDePrueba;
    use BarridoDeRutas;
    use DosPlazasDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** La cadena con la que entra cada rol de la app. */
    private const PROPIA = 'UnoA';

    /** La descripción del reporte sin plaza, para buscarla en las respuestas. */
    private const REPORTE_SIN_PLAZA = 'BarridoReporteSinPlaza';

    /** La cuenta sin pedidos ni direcciones que manda el reporte sin plaza. */
    private Usuario $sinPlaza;

    private ReporteSoporte $reporteSinPlaza;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sembrarDosPlazas();

        $this->sinPlaza = $this->cuentaDeUsuario();
        $this->sinPlaza->forceFill(['correo' => 'barrido.sin.plaza@ejemplo.com', 'nombre' => 'BarridoCuentaSinPlaza'])->save();
        $this->reporteSinPlaza = app(RecepcionDeReporte::class)->recibir($this->sinPlaza, self::REPORTE_SIN_PLAZA, null);
    }

    #[TestDox('autorizacion-E14: con dos plazas, y dos lavanderías, dos repartidores y dos usuarios en cada una, la sesión de cada rol recorre todas las rutas de la API pidiendo lo que no le corresponde: ninguna respuesta trae datos de otro socio, de otro usuario ni de otra plaza')]
    public function test_autorizacion_e14_ninguna_ruta_le_da_a_ningun_rol_datos_que_no_le_corresponden(): void
    {
        $fugas = [];

        foreach ([Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR, Rol::ADMINISTRACION] as $rol) {
            $fugas = [...$fugas, ...$this->fugasDelRol($rol)];
        }

        $this->assertSame([], $fugas);
    }

    #[TestDox('autorizacion-E14: en el sembrado, cada rol sí ve lo suyo: el usuario sus pedidos, la lavandería y el repartidor su pedido en curso y su entrega; el barrido no pasa en vacío')]
    public function test_autorizacion_e14_cada_rol_si_ve_lo_suyo(): void
    {
        $propia = $this->cadenas[self::PROPIA];

        $this->assertEqualsCanonicalizing(
            [$propia['enCurso']->id_publico, $propia['entregado']->id_publico],
            $this->idsDe($propia['usuario'], '/api/pedidos'),
        );
        $this->assertSame([$propia['enCurso']->id_publico], $this->idsDe($propia['lavanderia']->administrador, '/api/lavanderia/pedidos'));
        $this->assertSame([$propia['entregado']->id_publico], $this->idsDe($propia['lavanderia']->administrador, '/api/lavanderia/historial'));
        $this->assertSame([$propia['enCurso']->id_publico], $this->idsDe($propia['repartidor']->cuenta, '/api/repartidor/pedidos'));
        $this->assertSame([$propia['entregado']->id_publico], $this->idsDe($propia['repartidor']->cuenta, '/api/repartidor/historial'));
    }

    #[TestDox('autorizacion-E14 y perfil-usuario-E17: el reporte de una cuenta sin plaza se guarda sin ella; el componente se lo da solo a la administración, que ve todas las plazas, y a ningún otro rol, tampoco a los socios de una plaza')]
    public function test_autorizacion_e14_y_perfil_usuario_e17_el_reporte_sin_plaza_solo_lo_ve_la_administracion(): void
    {
        $this->assertNull($this->reporteSinPlaza->id_plazas);
        $autorizacion = app(Autorizacion::class);
        $propia = $this->cadenas[self::PROPIA];

        $this->assertTrue($autorizacion->reportesDeSoporteDelPanel($this->administracion)->whereKey($this->reporteSinPlaza->getKey())->exists());

        foreach ([
            'usuario' => $propia['usuario'],
            'quien lo mandó' => $this->sinPlaza,
            'lavanderia' => $propia['lavanderia']->administrador,
            'repartidor' => $propia['repartidor']->cuenta,
        ] as $quien => $cuenta) {
            $this->assertSame(0, $autorizacion->reportesDeSoporteDelPanel($cuenta)->count(), $quien);
        }

        $bloqueada = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['estado' => Usuario::BLOQUEADO]);
        $this->assertSame(0, $autorizacion->reportesDeSoporteDelPanel($bloqueada)->count(), 'Una cuenta bloqueada no ve nada.');
    }

    #[TestDox('autorizacion-E14: una ruta del Módulo B que trajera datos de otro socio y de otra plaza hace fallar el barrido, que nombra la ruta, el rol y el dato')]
    public function test_autorizacion_e14_una_ruta_con_fuga_hace_fallar_el_barrido_y_la_nombra(): void
    {
        Route::prefix('api')->middleware(['api', 'auth:sanctum', 'autorizar:lavanderia'])
            ->get('lavanderia/prueba/fuga', fn () => [
                'lavanderias' => Lavanderia::query()->with('administrador')->get()->pluck('administrador.correo'),
                'pedidos' => Pedido::query()->pluck('folio'),
            ]);

        $fugas = $this->fugasDelRol(Rol::LAVANDERIA);

        $this->assertContains('GET api/lavanderia/prueba/fuga · lavanderia · sin parámetros: trae el correo del socio de la lavandería UnoB', $fugas);
        $this->assertContains('GET api/lavanderia/prueba/fuga · lavanderia · sin parámetros: trae el folio del pedido en curso DosB', $fugas);
    }

    /**
     * Las fugas del rol en todas las rutas: entra con la cuenta de la cadena propia, o con la de administración, y pide
     * lo de las otras tres cadenas.
     *
     * @return list<string>
     */
    private function fugasDelRol(string $rol): array
    {
        $propia = $this->cadenas[self::PROPIA];
        $ajenas = array_diff(array_keys(self::CADENAS), [self::PROPIA]);
        $esUsuario = $rol === Rol::USUARIO;

        $cuenta = match ($rol) {
            Rol::USUARIO => $propia['usuario'],
            Rol::LAVANDERIA => $propia['lavanderia']->administrador,
            Rol::REPARTIDOR => $propia['repartidor']->cuenta,
            default => $this->administracion,
        };

        $pedidos = $direcciones = $lavanderias = $pagos = [];
        $datosAjenos = [];

        foreach ($ajenas as $marca) {
            $cadena = $this->cadenas[$marca];
            $pedidos[$cadena['enCurso']->id_publico] = "el pedido en curso {$marca}";
            $pedidos[$cadena['entregado']->id_publico] = "el pedido entregado {$marca}";
            // El cobro de cada pedido, que el usuario consulta en "Procesando pago" (pago-E08).
            $pagos[$cadena['enCurso']->cobro->id_publico] = "el cobro del pedido en curso {$marca}";
            $pagos[$cadena['entregado']->cobro->id_publico] = "el cobro del pedido entregado {$marca}";
            $direcciones[$cadena['direccion']->id_publico] = "la dirección del usuario {$marca}";
            $lavanderias[$cadena['lavanderia']->id_publico] = "la lavandería {$marca}";
            // Al usuario, el perfil de las lavanderías es público; a los socios, una lavandería ajena no. Se juntan con +,
            // que conserva las llaves: un teléfono es una llave entera, y ... la renumeraría.
            $datosAjenos += $this->datosDeLaCadena($marca, conLoPublico: ! $esUsuario);
        }

        $datosAjenos[$this->administracion->correo] = 'el correo de la administración';
        // El reporte sin plaza de otra cuenta no es de nadie más (perfil-usuario-E17).
        $datosAjenos[self::REPORTE_SIN_PLAZA] = 'el reporte sin plaza de otra cuenta';
        $datosAjenos[$this->sinPlaza->correo] = 'el correo de la cuenta sin plaza';

        // La plaza separa a los socios: la otra plaza es ajena para ellos, no para el usuario (autorizacion-E08).
        if (! $esUsuario) {
            $datosAjenos[$this->plazaDos->nombre] = 'la plaza Dos';
        }

        // La administración no tiene módulo en la app: todo lo de la app le es ajeno.
        if ($rol === Rol::ADMINISTRACION) {
            $datosAjenos += $this->datosDeLaCadena(self::PROPIA);
        }

        $otraPlaza = $this->cadenas['DosB'];

        return $this->fugasDe(
            $cuenta,
            $rol,
            ajenos: [
                'pedido' => $pedidos,
                'pago' => $pagos,
                'direccion' => $direcciones,
                ...($esUsuario ? [] : ['lavanderia' => $lavanderias]),
            ],
            // Al usuario, la lavandería de otra plaza, que es pública en el Módulo A; el catálogo es de todos.
            publicos: [
                ...($esUsuario ? ['lavanderia' => $otraPlaza['lavanderia']->id_publico] : []),
                'codigo_postal' => '00000',
            ],
            datosAjenos: $datosAjenos,
            // En el cuerpo y en la consulta, lo de otras cadenas, con un pedido que pasa la validación y un punto de la
            // otra plaza.
            datos: [
                'pedido' => $otraPlaza['enCurso']->id_publico,
                'direccion' => $this->cadenas['UnoB']['direccion']->id_publico,
                'recogida' => $this->cadenas['UnoB']['direccion']->id_publico,
                'entrega' => $otraPlaza['direccion']->id_publico,
                'lavanderia' => $propia['lavanderia']->id_publico,
                'servicio' => 'estandar',
                'ventana' => ['inicio' => self::JUEVES_DE_PRUEBA.'T11:00:00-06:00', 'fin' => self::JUEVES_DE_PRUEBA.'T12:00:00-06:00'],
                'latitud' => 10.5,
                'longitud' => -117.5,
                'fecha' => self::JUEVES_DE_PRUEBA,
            ],
        );
    }

    /**
     * Los id_publico de los pedidos que la cuenta recibe en esa ruta.
     *
     * @return list<string>
     */
    private function idsDe(Usuario $cuenta, string $ruta): array
    {
        return array_column($this->llamarSinEfecto($cuenta, 'GET', $ruta)->assertOk()->json('pedidos'), 'id_publico');
    }
}
