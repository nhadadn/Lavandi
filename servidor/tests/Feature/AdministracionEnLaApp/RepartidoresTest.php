<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Repartidores\Pages\EditRepartidor;
use App\Filament\Resources\Repartidores\Pages\ListRepartidores;
use App\Identidad\FotoDePerfil;
use App\Models\EstadoPedido;
use App\Models\Plaza;
use App\Models\Repartidor;
use App\Models\Usuario;
use App\Repartidores\EdicionDeRepartidor;
use App\Repartidores\EstadoDelRepartidor;
use App\Repartidores\PlazasDelAlta;
use App\Validacion\CorreoSinCuenta;
use Database\Factories\RepartidorFactory;
use Database\Factories\UsuarioFactory;
use Illuminate\Contracts\Filesystem\Filesystem as Disco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\ContratosDeLaApp;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\DiscoLocalDeFotos;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * La gestión de repartidores en la API de la administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2,
 * "Alta, baja, suspensión y edición de socios repartidores"; :18:Módulo C #1): la lista, la ficha, las plazas del alta,
 * el alta en una plaza, la suspensión y su levantamiento, la baja y la edición, cada una con la misma clase de dominio
 * que usa el panel (ADR-0022, decisión 9). La respuesta de cada ruta se compara con lo que da esa clase para los mismos
 * datos (administracion-app-E11), y cada ruta la abre solo la administración con su token (administracion-app-E06 y
 * E07). Los tokens son reales y van con withToken() (SesionesDeLaApp).
 *
 * Las reglas son las del panel: repartidor-E05 a E08 y E31 (tests/Feature/Repartidor/GestionEnElPanelTest.php, que sigue
 * sin cambios).
 *
 * Escenarios: administracion-app-E06, E07, E11, E12 y E19 a E23 · Spec:
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md · Tareas 5.1 a 5.4
 */
class RepartidoresTest extends TestCase
{
    use AgendaDePrueba;
    use ContratosDeLaApp;
    use DiscoLocalDeFotos;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;
    use SesionesDeLaApp;

    /** Lo único que trae el 404 de un repartidor que no existe (ADR-0019, decisión 3). */
    private const NO_EXISTE = ['message' => 'No existe el repartidor.'];

    private Disco $disco;

    private Usuario $administracion;

    /** El token de administración de la cuenta, como el que recibe al iniciar sesión. */
    private string $token;

    private Plaza $plazaUno;

    private Plaza $plazaDos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disco = $this->usarDiscoLocalDeFotos();
        // La URL firmada de la foto vence a una hora: con el reloj quieto, la de la respuesta y la de la clase de dominio
        // son la misma.
        $this->freezeSecond();
        $this->plazaUno = $this->plazaDePrueba('Plaza Uno');
        $this->plazaDos = $this->plazaDePrueba('Plaza Dos');
        $this->administracion = $this->cuentaDeAdministracion();
        $this->token = $this->tokenDeLaApp($this->administracion);
    }

    #[TestDox('administracion-app-E19: la lista trae a todos los repartidores, de las dos plazas y en cualquier estado, también los pendientes sin plaza y los de baja, cada uno con su nombre, su estado y su plaza, o sin plaza')]
    public function test_administracion_app_e19_la_lista_trae_a_todos_los_repartidores(): void
    {
        $repartidores = $this->repartidoresEnCadaEstado();

        $lista = $this->comoLaAdministracion('GET', '/api/administracion/repartidores')->assertOk()->json('repartidores');

        $this->assertCount(count($repartidores), $lista);
        $porId = array_column($lista, null, 'id_publico');

        // Cada uno con su nombre, su estado y su plaza, y nada más: el resto está en su ficha.
        foreach ($repartidores as $quien => $repartidor) {
            $this->assertArrayHasKey($repartidor->id_publico, $porId, "Falta {$quien} en la lista.");
            $this->assertSame([
                'id_publico' => $repartidor->id_publico,
                'nombre' => $repartidor->cuenta->nombre,
                'estado' => $repartidor->estado,
                'plaza' => $repartidor->plaza === null ? null : ['nombre' => $repartidor->plaza->nombre],
            ], $porId[$repartidor->id_publico], $quien);
        }

        $this->assertNull($porId[$repartidores['el pendiente']->id_publico]['plaza']);
        $this->assertSame(['nombre' => 'Plaza Dos'], $porId[$repartidores['el suspendido']->id_publico]['plaza']);
    }

    #[TestDox('administracion-app-E11: la lista da los mismos repartidores que Autorizacion::repartidoresDelPanel(), la consulta del panel, cada uno con su nombre, su estado y su plaza')]
    public function test_administracion_app_e11_la_lista_da_lo_mismo_que_el_panel(): void
    {
        $this->repartidoresEnCadaEstado();

        $lista = $this->comoLaAdministracion('GET', '/api/administracion/repartidores')->assertOk()->json('repartidores');

        $delPanel = app(Autorizacion::class)->repartidoresDelPanel($this->administracion)->get();
        $this->assertEqualsCanonicalizing($delPanel->pluck('id_publico')->all(), array_column($lista, 'id_publico'));

        foreach ($lista as $fila) {
            $this->assertSame($this->resumenEsperado($delPanel->firstWhere('id_publico', $fila['id_publico'])), $fila);
        }
    }

    #[TestDox('administracion-app-E19: la ficha trae su nombre, su teléfono, su correo, su foto, su tipo de vehículo, su estado y su plaza, y solo las transiciones que su estado permite, en el orden de los botones del panel')]
    public function test_administracion_app_e19_la_ficha_trae_sus_datos_y_solo_las_transiciones_que_su_estado_permite(): void
    {
        $cuenta = RepartidorFactory::cuentaDeRepartidor(['correo' => 'ficha@ejemplo.com', 'nombre' => 'Repartidor de la ficha']);
        app(FotoDePerfil::class)->poner($cuenta, $this->fotoDePrueba());
        $conFoto = Repartidor::factory()->en($this->plazaUno)->deLaCuenta($cuenta)->create(['tipo_vehiculo' => 'Bicicleta']);

        $ficha = $this->comoLaAdministracion('GET', "/api/administracion/repartidores/{$conFoto->id_publico}")
            ->assertOk()
            ->json('repartidor');

        $this->assertSame($conFoto->id_publico, $ficha['id_publico']);
        $this->assertSame('Repartidor de la ficha', $ficha['nombre']);
        $this->assertSame('6180000000', $ficha['telefono']);
        $this->assertSame('ficha@ejemplo.com', $ficha['correo']);
        $this->assertStringContainsString('signature=', $ficha['foto']['url']);
        $this->assertSame('Bicicleta', $ficha['tipo_vehiculo']);
        $this->assertSame(Repartidor::ACTIVO, $ficha['estado']);
        $this->assertSame(['nombre' => 'Plaza Uno'], $ficha['plaza']);

        // Solo las transiciones que su estado permite, en el orden de los botones del panel (repartidor-E05 a E07 y
        // E31).
        $esperadas = [
            'el pendiente' => ['dar_de_alta', 'dar_de_baja'],
            'el activo' => ['suspender', 'dar_de_baja'],
            'el suspendido' => ['levantar_suspension', 'dar_de_baja'],
            'el de baja' => [],
            'el pendiente de baja' => [],
        ];

        foreach ($this->repartidoresEnCadaEstado() as $quien => $repartidor) {
            $this->comoLaAdministracion('GET', "/api/administracion/repartidores/{$repartidor->id_publico}")
                ->assertOk()
                ->assertJsonPath('repartidor.transiciones', $esperadas[$quien]);
        }
    }

    #[TestDox('administracion-app-E11: la ficha de cada repartidor es la que dan, para los mismos datos, su cuenta, App\Identidad\FotoDePerfil y los puede…() de App\Repartidores\EstadoDelRepartidor')]
    public function test_administracion_app_e11_la_ficha_da_lo_mismo_que_las_clases_del_panel(): void
    {
        $cuenta = RepartidorFactory::cuentaDeRepartidor();
        app(FotoDePerfil::class)->poner($cuenta, $this->fotoDePrueba());
        $repartidores = [...$this->repartidoresEnCadaEstado(), 'el de la foto' => Repartidor::factory()->en($this->plazaDos)->deLaCuenta($cuenta)->create()];

        foreach ($repartidores as $quien => $repartidor) {
            $this->assertSame(
                ['repartidor' => $this->fichaEsperada($repartidor)],
                $this->comoLaAdministracion('GET', "/api/administracion/repartidores/{$repartidor->id_publico}")->assertOk()->json(),
                $quien,
            );
        }
    }

    #[TestDox('administracion-app-E20 y administracion-app-E11: las plazas del alta son las activas de Autorizacion::plazasDelPanel(), las mismas que ofrece el panel; una plaza que no está activa no sale')]
    public function test_administracion_app_e20_las_plazas_del_alta_son_las_del_panel(): void
    {
        $this->plazaDePrueba('Plaza cerrada', activa: false);

        $plazas = $this->comoLaAdministracion('GET', '/api/administracion/plazas')->assertOk()->json('plazas');

        $this->assertSame([['nombre' => 'Plaza Dos'], ['nombre' => 'Plaza Uno']], $plazas);
        $this->assertSame(
            app(PlazasDelAlta::class)->de($this->administracion)->orderBy('nombre')->pluck('nombre')->all(),
            array_column($plazas, 'nombre'),
        );
    }

    #[TestDox('administracion-app-E20: la administración da de alta en una plaza al repartidor pendiente; queda activo en esa plaza, con la hora del alta, y ya opera, con la regla de repartidor-E05')]
    public function test_administracion_app_e20_da_de_alta_en_una_plaza(): void
    {
        $pendiente = Repartidor::factory()->pendiente()->create();
        $this->comoRepartidor($pendiente)->getJson('/api/repartidor/pedidos')->assertForbidden();

        $respuesta = $this->comoLaAdministracion('POST', "/api/administracion/repartidores/{$pendiente->id_publico}/alta", ['plaza' => 'Plaza Dos'])
            ->assertOk();

        $pendiente->refresh();
        $this->assertSame(Repartidor::ACTIVO, $pendiente->estado);
        $this->assertSame($this->plazaDos->getKey(), $pendiente->id_plazas);
        $this->assertSame(now()->utc()->toIso8601ZuluString(), $pendiente->aprobado_en->utc()->toIso8601ZuluString());
        // La respuesta es la ficha nueva: ya no ofrece el alta.
        $this->assertSame(['repartidor' => $this->fichaEsperada($pendiente)], $respuesta->json());
        $respuesta->assertJsonPath('repartidor.transiciones', ['suspender', 'dar_de_baja']);

        $this->comoRepartidor($pendiente)->getJson('/api/repartidor/perfil')
            ->assertOk()
            ->assertJsonPath('repartidor.puede_operar', true)
            ->assertJsonPath('repartidor.plaza.nombre', 'Plaza Dos');
        $this->comoRepartidor($pendiente)->getJson('/api/repartidor/pedidos')->assertOk();
    }

    #[TestDox('administracion-app-E20: sin plaza, con una que no existe o con una que no está activa, el alta responde 422 en plaza y nada cambia; el panel tampoco da de alta en una plaza que no está activa')]
    public function test_administracion_app_e20_sin_plaza_el_alta_responde_422(): void
    {
        $pendiente = Repartidor::factory()->pendiente()->create();
        $cerrada = $this->plazaDePrueba('Plaza cerrada', activa: false);
        $url = "/api/administracion/repartidores/{$pendiente->id_publico}/alta";
        $antes = $this->fotoDeLaBase();

        foreach ([[], ['plaza' => null], ['plaza' => ''], ['plaza' => 'Plaza que no existe'], ['plaza' => 'Plaza cerrada'], ['plaza' => ['Plaza Uno']]] as $cuerpo) {
            $this->comoLaAdministracion('POST', $url, $cuerpo)
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors(['plaza']);
        }

        $this->comoLaAdministracion('POST', $url)->assertJsonPath('errors.plaza.0', PlazasDelAlta::SIN_PLAZA);
        $this->comoLaAdministracion('POST', $url, ['plaza' => 'Plaza cerrada'])->assertJsonPath('errors.plaza.0', PlazasDelAlta::NO_ES_DEL_ALTA);
        $this->assertSame($antes, $this->fotoDeLaBase());

        // En el panel, la misma plaza cerrada no está entre las opciones del alta.
        $this->comoAdministracion();
        Livewire::test(ListRepartidores::class)
            ->callTableAction('darDeAlta', $pendiente, data: ['plaza' => $cerrada->getKey()])
            ->assertHasTableActionErrors(['plaza']);
        $this->assertSame(Repartidor::PENDIENTE, $pendiente->fresh()->estado);
    }

    #[TestDox('administracion-app-E21: la administración suspende al repartidor activo y ya no opera; al levantar la suspensión vuelve a estar activo en su misma plaza y recupera su acceso, con la regla de repartidor-E06')]
    public function test_administracion_app_e21_suspende_y_levanta_la_suspension(): void
    {
        $activo = Repartidor::factory()->en($this->plazaUno)->create();
        $this->comoRepartidor($activo)->getJson('/api/repartidor/pedidos')->assertOk();

        $respuesta = $this->comoLaAdministracion('POST', "/api/administracion/repartidores/{$activo->id_publico}/suspension")->assertOk();

        $activo->refresh();
        $this->assertSame(Repartidor::SUSPENDIDO, $activo->estado);
        $this->assertSame(['repartidor' => $this->fichaEsperada($activo)], $respuesta->json());
        $respuesta->assertJsonPath('repartidor.transiciones', ['levantar_suspension', 'dar_de_baja']);
        $this->comoRepartidor($activo)->getJson('/api/repartidor/pedidos')->assertForbidden();
        $this->comoRepartidor($activo)->getJson('/api/repartidor/perfil')->assertOk()->assertJsonPath('repartidor.puede_operar', false);

        $respuesta = $this->comoLaAdministracion('DELETE', "/api/administracion/repartidores/{$activo->id_publico}/suspension")->assertOk();

        $activo->refresh();
        $this->assertSame(Repartidor::ACTIVO, $activo->estado);
        $this->assertSame($this->plazaUno->getKey(), $activo->id_plazas);
        $this->assertSame(['repartidor' => $this->fichaEsperada($activo)], $respuesta->json());
        $this->comoRepartidor($activo)->getJson('/api/repartidor/pedidos')->assertOk();
    }

    #[TestDox('administracion-app-E22: la administración da de baja al repartidor activo con entregas completadas, con la regla de repartidor-E07, y al pendiente que nunca se dio de alta, con la de repartidor-E31; sus fichas ya no ofrecen ninguna acción de estado')]
    public function test_administracion_app_e22_da_de_baja_al_activo_y_al_pendiente(): void
    {
        $this->prepararPedido();
        // prepararPedido() mueve el reloj: el token de la administración se pide otra vez, con el reloj nuevo.
        $this->token = $this->tokenDeLaApp($this->administracion);
        $cuenta = RepartidorFactory::cuentaDeRepartidor(['correo' => 'baja@ejemplo.com']);
        $activo = Repartidor::factory()->en($this->plaza)->deLaCuenta($cuenta)->create();
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $this->repartidorQueLoAcepta($pedido, $activo);
        $entrega = $this->llevarHastaEntregado($pedido);
        $this->assertSame(EstadoPedido::ENTREGADO, $entrega->estado->codigo);
        $tokenDelActivo = $this->tokenDeLaApp($cuenta);
        $pendiente = Repartidor::factory()->pendiente()->create();

        foreach (['activo' => $activo, 'pendiente' => $pendiente] as $quien => $repartidor) {
            $this->comoLaAdministracion('POST', "/api/administracion/repartidores/{$repartidor->id_publico}/baja")
                ->assertOk()
                ->assertJsonPath('repartidor.estado', Repartidor::BAJA)
                ->assertJsonPath('repartidor.transiciones', []);

            $deBaja = Repartidor::withTrashed()->whereKey($repartidor->getKey())->sole();
            $this->assertSame(Repartidor::BAJA, $deBaja->estado, $quien);
            $this->assertNotNull($deBaja->deleted_at, $quien);
            $this->assertSame(0, $deBaja->cuenta->tokens()->count(), $quien);
            $this->comoLaAdministracion('GET', "/api/administracion/repartidores/{$repartidor->id_publico}")
                ->assertOk()
                ->assertJsonPath('repartidor', $this->fichaEsperada($deBaja));
        }

        // El activo ya no entra, ni con su sesión abierta ni con su contraseña, y su entrega se conserva (repartidor-E07).
        $this->llamarConToken($tokenDelActivo, 'GET', '/api/repartidor/perfil')->assertUnauthorized();
        $this->llamarConToken(null, 'POST', '/api/sesion', ['correo' => 'baja@ejemplo.com', 'password' => UsuarioFactory::CONTRASENA])
            ->assertUnprocessable()
            ->assertJsonMissingPath('token');
        $this->assertSame(EstadoPedido::ENTREGADO, $entrega->fresh()->estado->codigo);
        $this->assertSame($activo->getKey(), $entrega->fresh()->id_repartidores);
        $this->assertNotNull(Usuario::query()->whereKey($cuenta->getKey())->first());
        // El pendiente queda de baja sin plaza: nunca tuvo alta (repartidor-E31).
        $this->assertNull(Repartidor::withTrashed()->whereKey($pendiente->getKey())->sole()->id_plazas);
    }

    #[TestDox('administracion-app-E23: la administración cambia el nombre, el teléfono, el correo y el tipo de vehículo del repartidor en JSON, y la foto en multipart, con la regla de repartidor-E08; el repartidor lo ve en su perfil y su contraseña no cambia')]
    public function test_administracion_app_e23_edita_al_repartidor(): void
    {
        $cuenta = RepartidorFactory::cuentaDeRepartidor(['correo' => 'antes@ejemplo.com', 'nombre' => 'Nombre de antes']);
        $repartidor = Repartidor::factory()->en($this->plazaUno)->deLaCuenta($cuenta)->create(['tipo_vehiculo' => 'Bicicleta']);
        $url = "/api/administracion/repartidores/{$repartidor->id_publico}";

        $respuesta = $this->comoLaAdministracion('PATCH', $url, [
            'nombre' => '  Nombre de después  ',
            'telefono' => '871 765 4321',
            'correo' => 'Despues@Ejemplo.com',
            'tipo_vehiculo' => 'Motocicleta',
        ])->assertOk();

        $cuenta->refresh();
        $this->assertSame('Nombre de después', $cuenta->nombre);
        $this->assertSame('871 765 4321', $cuenta->telefono);
        $this->assertSame('despues@ejemplo.com', $cuenta->correo);
        $this->assertSame('Motocicleta', $repartidor->fresh()->tipo_vehiculo);
        $this->assertSame(['repartidor' => $this->fichaEsperada($repartidor)], $respuesta->json());
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $cuenta->password));

        // Solo cambia lo que llega: con un campo, los demás se quedan.
        $this->comoLaAdministracion('PATCH', $url, ['tipo_vehiculo' => 'Automóvil'])->assertOk();
        $this->assertSame('Automóvil', $repartidor->fresh()->tipo_vehiculo);
        $this->assertSame('Nombre de después', $cuenta->fresh()->nombre);

        // La foto, en multipart por POST con _method=PATCH.
        $respuesta = $this->editarEnMultipart($url, ['foto' => $this->fotoDePrueba('desde-la-app.jpg')])->assertOk();

        $cuenta->refresh();
        $this->assertStringStartsWith("usuarios/{$cuenta->id_publico}/", $cuenta->foto_ruta);
        $this->assertSame([$cuenta->foto_ruta], $this->disco->allFiles());
        $this->assertSame(['repartidor' => $this->fichaEsperada($repartidor)], $respuesta->json());

        $this->comoRepartidor($repartidor)->getJson('/api/repartidor/perfil')
            ->assertOk()
            ->assertJsonPath('repartidor.nombre', 'Nombre de después')
            ->assertJsonPath('repartidor.correo', 'despues@ejemplo.com')
            ->assertJsonPath('repartidor.tipo_vehiculo', 'Automóvil')
            ->assertJsonPath('repartidor.foto.url', fn (string $foto): bool => str_contains($foto, 'signature='));
    }

    #[TestDox('administracion-app-E23: un dato vacío, un teléfono que no vale, un correo que ya es de otra cuenta o una foto que no es imagen responden 422 con el error de cada campo, y nada cambia; su propio correo, aunque cambie de mayúsculas, no choca consigo mismo')]
    public function test_administracion_app_e23_un_dato_que_no_cumple_responde_422_por_campo(): void
    {
        Usuario::factory()->create(['correo' => 'ocupado@ejemplo.com']);
        $cuenta = RepartidorFactory::cuentaDeRepartidor(['correo' => 'propio@ejemplo.com', 'nombre' => 'Nombre de prueba']);
        $repartidor = Repartidor::factory()->en($this->plazaUno)->deLaCuenta($cuenta)->create();
        $url = "/api/administracion/repartidores/{$repartidor->id_publico}";
        $antes = $this->fotoDeLaBase();

        $this->comoLaAdministracion('PATCH', $url, ['nombre' => '   ', 'telefono' => 'no es teléfono', 'correo' => 'OCUPADO@ejemplo.com', 'tipo_vehiculo' => ''])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['nombre', 'telefono', 'correo', 'tipo_vehiculo'])
            ->assertJsonPath('errors.nombre.0', 'Escribe tu nombre.')
            ->assertJsonPath('errors.telefono.0', 'El teléfono no es válido.')
            ->assertJsonPath('errors.correo.0', CorreoSinCuenta::MENSAJE)
            ->assertJsonPath('errors.tipo_vehiculo.0', 'Escribe tu tipo de vehículo.');

        // Con una foto que no es imagen, ni lo demás que sí vale se guarda.
        $this->editarEnMultipart($url, ['nombre' => 'Nombre que no se guarda', 'foto' => UploadedFile::fake()->create('documento.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['foto']);
        $this->comoLaAdministracion('PATCH', $url, ['foto' => 'no es un archivo'])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['foto']);

        $this->assertSame($antes, $this->fotoDeLaBase());
        $this->assertSame([], $this->disco->allFiles());

        $this->comoLaAdministracion('PATCH', $url, ['correo' => 'Propio@Ejemplo.com', 'nombre' => 'Nombre nuevo'])->assertOk();
        $this->assertSame('Nombre nuevo', $cuenta->fresh()->nombre);
        $this->assertSame('propio@ejemplo.com', $cuenta->fresh()->correo);
    }

    #[TestDox('administracion-app-E11: la validación de la edición vive en App\Repartidores\EdicionDeRepartidor: da a la API y al panel los mismos errores para los mismos datos, el panel bajo data.<campo> y la API bajo su campo')]
    public function test_administracion_app_e11_la_edicion_da_los_mismos_errores_al_panel_y_a_la_api(): void
    {
        Usuario::factory()->create(['correo' => 'ocupado@ejemplo.com']);
        $repartidor = Repartidor::factory()->en($this->plazaUno)->create();
        $datos = ['nombre' => '', 'telefono' => '123', 'correo' => 'Ocupado@Ejemplo.com', 'tipo_vehiculo' => str_repeat('x', 101)];

        try {
            app(EdicionDeRepartidor::class)->editar($repartidor, $datos);
            $this->fail('La clase de dominio no rechazó los datos.');
        } catch (ValidationException $error) {
            $delDominio = $error->errors();
        }

        $this->assertSame(['nombre', 'telefono', 'correo', 'tipo_vehiculo'], array_keys($delDominio));
        $this->assertSame(
            $delDominio,
            $this->comoLaAdministracion('PATCH', "/api/administracion/repartidores/{$repartidor->id_publico}", $datos)
                ->assertUnprocessable()
                ->json('errors'),
        );

        $this->comoAdministracion();
        $panel = Livewire::test(EditRepartidor::class, ['record' => $repartidor->getRouteKey()])
            ->fillForm($datos)
            ->call('save');

        foreach ($delDominio as $campo => $textos) {
            $this->assertSame($textos, $panel->errors()->get("data.{$campo}"), $campo);
        }
    }

    #[TestDox('administracion-app-E12: un identificador que no es de ningún repartidor, o que no es ULID, responde 404 con solo su mensaje en la ficha, la edición, el alta, la suspensión, su levantamiento y la baja, y nada cambia')]
    public function test_administracion_app_e12_un_repartidor_que_no_existe_responde_404(): void
    {
        Repartidor::factory()->pendiente()->create();
        $antes = $this->fotoDeLaBase();

        foreach ([(string) Str::ulid(), 'no-es-un-ulid'] as $identificador) {
            foreach ($this->rutasDelRepartidor($identificador) as [$metodo, $url, $cuerpo]) {
                $this->comoLaAdministracion($metodo, $url, $cuerpo)
                    ->assertNotFound()
                    ->assertExactJson(self::NO_EXISTE);
            }
        }

        $this->assertSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E12: una transición que el estado ya no permite, porque otra sesión la hizo antes, responde 409 con el mensaje de App\Repartidores\TransicionNoPermitida y nada cambia')]
    public function test_administracion_app_e12_una_transicion_que_ya_no_se_puede_responde_409(): void
    {
        $activo = Repartidor::factory()->en($this->plazaUno)->create();
        $deBaja = Repartidor::factory()->en($this->plazaUno)->deBaja()->create();
        $antes = $this->fotoDeLaBase();

        $intentos = [
            ['POST', "/api/administracion/repartidores/{$activo->id_publico}/alta", ['plaza' => 'Plaza Uno'], 'Un repartidor en estado activo no se puede dar de alta.'],
            ['DELETE', "/api/administracion/repartidores/{$activo->id_publico}/suspension", [], 'Un repartidor en estado activo no se puede levantar de una suspensión.'],
            ['POST', "/api/administracion/repartidores/{$deBaja->id_publico}/suspension", [], 'Un repartidor en estado baja no se puede suspender.'],
            ['POST', "/api/administracion/repartidores/{$deBaja->id_publico}/baja", [], 'Un repartidor en estado baja no se puede dar de baja.'],
        ];

        foreach ($intentos as [$metodo, $url, $cuerpo, $mensaje]) {
            $this->comoLaAdministracion($metodo, $url, $cuerpo)
                ->assertStatus(409)
                ->assertExactJson(['message' => $mensaje]);
        }

        $this->assertSame($antes, $this->fotoDeLaBase());

        // Lo que hizo otra sesión: el repartidor ya está suspendido cuando llega la segunda.
        $this->comoLaAdministracion('POST', "/api/administracion/repartidores/{$activo->id_publico}/suspension")->assertOk();
        $this->comoLaAdministracion('POST', "/api/administracion/repartidores/{$activo->id_publico}/suspension")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Un repartidor en estado suspendido no se puede suspender.']);
        $this->assertSame(Repartidor::SUSPENDIDO, $activo->fresh()->estado);
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: cada ruta de la gestión de repartidores la abre solo la administración con su token: las cuentas de usuario, lavandería y repartidor y un token sin la habilidad reciben 403 sin datos, sin sesión o con el token vencido 401, y nada cambia')]
    public function test_administracion_app_e07_cada_ruta_la_abre_solo_la_administracion(): void
    {
        $pendiente = Repartidor::factory()->pendiente()->create();
        $activo = Repartidor::factory()->en($this->plazaUno)->create();

        $this->assertSoloEntraLaAdministracion('GET', '/api/administracion/repartidores');
        $this->assertSoloEntraLaAdministracion('GET', '/api/administracion/plazas');

        foreach ([...$this->rutasDelRepartidor($pendiente->id_publico), ...$this->rutasDelRepartidor($activo->id_publico)] as [$metodo, $url, $cuerpo]) {
            $this->assertSoloEntraLaAdministracion($metodo, $url, $cuerpo);
        }

        $this->assertSame(Repartidor::PENDIENTE, $pendiente->fresh()->estado);
        $this->assertSame(Repartidor::ACTIVO, $activo->fresh()->estado);
    }

    #[TestDox('administracion-app-E19: el contrato de la lista con la app: cada repartidor solo con su id_publico, su nombre, su estado y su plaza, o null sin plaza; sin repartidores, la lista vacía (design.md, F-D02)')]
    public function test_administracion_app_e19_el_contrato_de_la_lista(): void
    {
        $peticion = 'GET /api/administracion/repartidores';
        $this->assertCumpleSuContrato('repartidores.lista-vacia', $peticion, $this->comoLaAdministracion('GET', '/api/administracion/repartidores'));

        $this->repartidoresEnCadaEstado();
        $respuesta = $this->comoLaAdministracion('GET', '/api/administracion/repartidores');

        $this->assertCumpleSuContrato('repartidores.lista', $peticion, $respuesta);
        // La lista no trae lo que solo se ve en la ficha: ni el correo, ni el teléfono, ni la foto, ni el vehículo.
        $respuesta->assertJsonStructure(['repartidores' => ['*' => ['id_publico', 'nombre', 'estado', 'plaza']]]);
        foreach ($respuesta->json('repartidores') as $fila) {
            $this->assertSame(['id_publico', 'nombre', 'estado', 'plaza'], array_keys($fila));
        }
    }

    #[TestDox('administracion-app-E19 y administracion-app-E12: el contrato de la ficha con la app: la del activo con su foto y su plaza, la del pendiente sin ninguna, las dos con sus transiciones en una lista; y el 404 del que no existe')]
    public function test_administracion_app_e19_el_contrato_de_la_ficha(): void
    {
        $peticion = 'GET /api/administracion/repartidores/{repartidor}';
        $cuenta = RepartidorFactory::cuentaDeRepartidor(['correo' => 'repartidor1@ejemplo.com', 'nombre' => 'Repartidor de prueba 1']);
        app(FotoDePerfil::class)->poner($cuenta, $this->fotoDePrueba());
        $activo = Repartidor::factory()->en($this->plazaUno)->deLaCuenta($cuenta)->create(['tipo_vehiculo' => 'Motocicleta']);
        $pendiente = Repartidor::factory()->pendiente()->create();

        $ficha = $this->comoLaAdministracion('GET', "/api/administracion/repartidores/{$activo->id_publico}");
        $this->assertCumpleSuContrato('repartidores.ficha', $peticion, $ficha);
        $ficha->assertJsonStructure(['repartidor' => [
            'id_publico', 'nombre', 'telefono', 'correo', 'foto' => ['url', 'vence_en'], 'tipo_vehiculo', 'estado',
            'plaza' => ['nombre'], 'transiciones',
        ]]);
        $this->assertCumpleSuContrato(
            'repartidores.ficha-pendiente',
            $peticion,
            $this->comoLaAdministracion('GET', "/api/administracion/repartidores/{$pendiente->id_publico}"),
        );
        $this->assertCumpleSuContrato(
            'repartidores.no-existe',
            $peticion,
            $this->comoLaAdministracion('GET', '/api/administracion/repartidores/'.Str::ulid()),
            404,
        );
    }

    #[TestDox('administracion-app-E23: el contrato de la edición con la app: la ficha con los cambios, y el 422 con el error de cada campo')]
    public function test_administracion_app_e23_el_contrato_de_la_edicion(): void
    {
        $peticion = 'PATCH /api/administracion/repartidores/{repartidor}';
        $cuenta = RepartidorFactory::cuentaDeRepartidor(['correo' => 'repartidor2@ejemplo.com', 'nombre' => 'Repartidor de prueba 2']);
        app(FotoDePerfil::class)->poner($cuenta, $this->fotoDePrueba());
        $repartidor = Repartidor::factory()->en($this->plazaUno)->deLaCuenta($cuenta)->create(['tipo_vehiculo' => 'Bicicleta']);
        $url = "/api/administracion/repartidores/{$repartidor->id_publico}";

        $this->assertCumpleSuContrato('repartidores.edicion', $peticion, $this->comoLaAdministracion('PATCH', $url, [
            'nombre' => 'Repartidor de prueba 2 editado',
            'tipo_vehiculo' => 'Motocicleta',
        ]));
        $this->assertCumpleSuContrato(
            'repartidores.edicion-invalida',
            $peticion,
            $this->comoLaAdministracion('PATCH', $url, ['nombre' => '', 'telefono' => 'no es teléfono']),
            422,
        );
    }

    #[TestDox('administracion-app-E20 y administracion-app-E12: el contrato del alta con la app: la ficha del repartidor ya activo en su plaza, el 422 sin plaza y el 409 del que ya no está pendiente')]
    public function test_administracion_app_e20_el_contrato_del_alta(): void
    {
        $peticion = 'POST /api/administracion/repartidores/{repartidor}/alta';
        $pendiente = Repartidor::factory()->pendiente()->create();
        $url = "/api/administracion/repartidores/{$pendiente->id_publico}/alta";

        $this->assertCumpleSuContrato('repartidores.alta-sin-plaza', $peticion, $this->comoLaAdministracion('POST', $url), 422);
        $this->assertCumpleSuContrato('repartidores.alta', $peticion, $this->comoLaAdministracion('POST', $url, ['plaza' => 'Plaza Uno']));
        $this->assertCumpleSuContrato(
            'repartidores.alta-ya-no-se-puede',
            $peticion,
            $this->comoLaAdministracion('POST', $url, ['plaza' => 'Plaza Uno']),
            409,
        );
    }

    #[TestDox('administracion-app-E21 y administracion-app-E12: el contrato de la suspensión con la app: la ficha del suspendido, la del que vuelve a estar activo y el 409 del que ya está suspendido')]
    public function test_administracion_app_e21_el_contrato_de_la_suspension(): void
    {
        $activo = Repartidor::factory()->en($this->plazaUno)->create();
        $url = "/api/administracion/repartidores/{$activo->id_publico}/suspension";

        $this->assertCumpleSuContrato(
            'repartidores.suspension',
            'POST /api/administracion/repartidores/{repartidor}/suspension',
            $this->comoLaAdministracion('POST', $url),
        );
        $this->assertCumpleSuContrato(
            'repartidores.suspension-ya-no-se-puede',
            'POST /api/administracion/repartidores/{repartidor}/suspension',
            $this->comoLaAdministracion('POST', $url),
            409,
        );
        $this->assertCumpleSuContrato(
            'repartidores.levantar-suspension',
            'DELETE /api/administracion/repartidores/{repartidor}/suspension',
            $this->comoLaAdministracion('DELETE', $url),
        );
    }

    #[TestDox('administracion-app-E22: el contrato de la baja con la app: la ficha del repartidor de baja, sin ninguna transición')]
    public function test_administracion_app_e22_el_contrato_de_la_baja(): void
    {
        $activo = Repartidor::factory()->en($this->plazaUno)->create();

        $this->assertCumpleSuContrato(
            'repartidores.baja',
            'POST /api/administracion/repartidores/{repartidor}/baja',
            $this->comoLaAdministracion('POST', "/api/administracion/repartidores/{$activo->id_publico}/baja"),
        );
    }

    #[TestDox('administracion-app-E20: el contrato de las plazas del alta con la app: cada una solo con su nombre')]
    public function test_administracion_app_e20_el_contrato_de_las_plazas(): void
    {
        $respuesta = $this->comoLaAdministracion('GET', '/api/administracion/plazas');

        $this->assertCumpleSuContrato('plazas.lista', 'GET /api/administracion/plazas', $respuesta);
        $respuesta->assertExactJson(['plazas' => [['nombre' => 'Plaza Dos'], ['nombre' => 'Plaza Uno']]]);
    }

    /**
     * Un repartidor en cada estado, de las dos plazas: el pendiente sin plaza, el activo, el suspendido, el de baja y el
     * pendiente que nunca se autorizó y se dio de baja sin plaza (repartidor-E31).
     *
     * @return array<string, Repartidor>
     */
    private function repartidoresEnCadaEstado(): array
    {
        $pendienteDeBaja = Repartidor::factory()->pendiente()->create();
        app(EstadoDelRepartidor::class)->darDeBaja($pendienteDeBaja);

        return [
            'el pendiente' => Repartidor::factory()->pendiente()->create(),
            'el activo' => Repartidor::factory()->en($this->plazaUno)->create(),
            'el suspendido' => Repartidor::factory()->en($this->plazaDos)->suspendido()->create(),
            'el de baja' => Repartidor::factory()->en($this->plazaUno)->deBaja()->create(),
            'el pendiente de baja' => $pendienteDeBaja,
        ];
    }

    /**
     * El repartidor en la lista, leído de la base: su nombre, su estado y su plaza (administracion-app-E11 y E19).
     *
     * @return array<string, mixed>
     */
    private function resumenEsperado(Repartidor $repartidor): array
    {
        $leido = Repartidor::withTrashed()->with(['cuenta', 'plaza'])->whereKey($repartidor->getKey())->sole();

        return [
            'id_publico' => $leido->id_publico,
            'nombre' => $leido->cuenta->nombre,
            'estado' => $leido->estado,
            'plaza' => $leido->plaza === null ? null : ['nombre' => $leido->plaza->nombre],
        ];
    }

    /**
     * La ficha que dan para ese repartidor, leído otra vez de la base, las clases que usa el panel: su cuenta, la foto
     * de App\Identidad\FotoDePerfil, su plaza y los puede…() de App\Repartidores\EstadoDelRepartidor, en el orden de
     * los botones del panel (administracion-app-E11).
     *
     * @return array<string, mixed>
     */
    private function fichaEsperada(Repartidor $repartidor): array
    {
        $leido = Repartidor::withTrashed()->with(['cuenta', 'plaza'])->whereKey($repartidor->getKey())->sole();
        $estado = app(EstadoDelRepartidor::class);

        return [
            'id_publico' => $leido->id_publico,
            'nombre' => $leido->cuenta->nombre,
            'telefono' => $leido->cuenta->telefono,
            'correo' => $leido->cuenta->correo,
            'foto' => app(FotoDePerfil::class)->url($leido->cuenta),
            'tipo_vehiculo' => $leido->tipo_vehiculo,
            'estado' => $leido->estado,
            'plaza' => $leido->plaza === null ? null : ['nombre' => $leido->plaza->nombre],
            'transiciones' => array_keys(array_filter([
                'dar_de_alta' => $estado->puedeDarseDeAlta($leido),
                'suspender' => $estado->puedeSuspenderse($leido),
                'levantar_suspension' => $estado->puedeLevantarseLaSuspension($leido),
                'dar_de_baja' => $estado->puedeDarseDeBaja($leido),
            ])),
        ];
    }

    /**
     * Las rutas que nombran a un repartidor, cada una con un cuerpo que pasa su validación.
     *
     * @return list<array{string, string, array<string, mixed>}>
     */
    private function rutasDelRepartidor(string $identificador): array
    {
        $url = "/api/administracion/repartidores/{$identificador}";

        return [
            ['GET', $url, []],
            ['PATCH', $url, ['nombre' => 'Nombre de la prueba']],
            ['POST', "{$url}/alta", ['plaza' => 'Plaza Uno']],
            ['POST', "{$url}/suspension", []],
            ['DELETE', "{$url}/suspension", []],
            ['POST', "{$url}/baja", []],
        ];
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     */
    private function comoLaAdministracion(string $metodo, string $url, array $cuerpo = []): TestResponse
    {
        return $this->llamarConToken($this->token, $metodo, $url, $cuerpo);
    }

    /**
     * La edición en multipart, como la manda la app con una foto: por POST con _method=PATCH, porque PHP solo lee los
     * archivos de un POST.
     *
     * @param  array<string, mixed>  $datos
     */
    private function editarEnMultipart(string $url, array $datos): TestResponse
    {
        config(['app.debug' => false]);
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)
            ->withHeader('Accept', 'application/json')
            ->post($url, ['_method' => 'PATCH', ...$datos]);
    }
}
