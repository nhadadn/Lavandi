<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Autorizacion\Autorizacion;
use App\Direcciones\GeocodificacionNoDisponible;
use App\Direcciones\Geocodificador;
use App\Direcciones\Punto;
use App\Http\Resources\LavanderiaDelSocioResource;
use App\Lavanderias\EdicionDeLavanderia;
use App\Lavanderias\EstadoDeLaLavanderia;
use App\Lavanderias\FotosDeLaLavanderia;
use App\Lavanderias\TransicionNoPermitida;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Municipio;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\ContratosDeLaApp;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\AltaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La gestión de lavanderías de la administración en la app: la lista de todas, de todas las plazas, con búsqueda por
 * nombre; la ficha con sus datos editables, su estado, su plaza y las transiciones que permite; la edición; y la
 * suspensión, su levantamiento y la baja (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1, "Alta, baja,
 * suspensión y edición de socios."; ADR-0022, decisión 9).
 *
 * Cada respuesta se compara con lo que dan, para los mismos datos, las clases que usa el panel web de respaldo:
 * Autorizacion::lavanderiasDelPanel(), el recurso de la lavandería que ya usa su edición, EdicionDeLavanderia (con la
 * validación que antes vivía en el panel) y EstadoDeLaLavanderia (administracion-app-E11). La ficha trae además la marca
 * de las fotos que se leyeron (fotos_leidas), que la app devuelve con la lista de fotos: la edición cuenta los lugares
 * sobre esas fotos y la rechaza si cambiaron, como el panel con las que leyó al abrir la página. Las pruebas del panel
 * (tests/Feature/AltaLavanderias/PanelEdicionTest.php, PanelSuspensionTest.php y PanelBajaTest.php) siguen sin cambios.
 * Cada ruta la abre solo la administración con su token, real, de App\Identidad\TokensDeLaApp (administracion-app-E06 y
 * E07). El alta (E17, DD-053) y los pedidos en curso (E18, DD-104) esperan su decisión: no tienen ruta.
 *
 * Escenarios: administracion-app-E06, E07, E11, E12, E13, E14, E15 y E16 · Spec:
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md · Tareas 3.1 a 3.4
 */
class LavanderiasTest extends TestCase
{
    use AgendaDePrueba;
    use AltaDePrueba;
    use ContratosDeLaApp;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use SesionesDeLaApp;

    private const LISTA = '/api/administracion/lavanderias';

    /** Lo único que trae el 404 de una lavandería que no existe (ADR-0019, decisión 3). */
    private const NO_EXISTE = ['message' => 'No existe la lavandería.'];

    private Usuario $administracion;

    /** El token de administración de la cuenta, el que recibe al iniciar sesión en la app. */
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administracion = $this->cuentaDeAdministracion();
        $this->token = $this->tokenDeLaApp($this->administracion);
    }

    #[TestDox('administracion-app-E13 y administracion-app-E11: la lista trae todas las lavanderías de las dos plazas, activas, pendientes, suspendidas y de baja, cada una con su nombre, su estado y su plaza: las mismas que Autorizacion::lavanderiasDelPanel()')]
    public function test_administracion_app_e13_la_lista_trae_todas_las_lavanderias_con_su_estado_y_su_plaza(): void
    {
        $norte = $this->plazaDePrueba('Plaza de prueba Norte');
        $sur = $this->plazaDePrueba('Plaza de prueba Sur');
        $activa = Lavanderia::factory()->en($norte)->create(['nombre' => 'Lavandería de prueba A Activa']);
        $pendiente = Lavanderia::factory()->en($norte)->pendiente()->create(['nombre' => 'Lavandería de prueba B Pendiente']);
        $suspendida = Lavanderia::factory()->en($sur)->suspendida()->create(['nombre' => 'Lavandería de prueba C Suspendida']);
        $deBaja = Lavanderia::factory()->en($sur)->deBaja()->create(['nombre' => 'Lavandería de prueba D de Baja']);
        // Una borrada no se ve, como en el panel.
        Lavanderia::factory()->en($norte)->create(['nombre' => 'Lavandería de prueba E Borrada'])->delete();

        $respuesta = $this->pedirComoLaAdministracion('GET', self::LISTA)->assertOk();

        $this->assertSame(['lavanderias' => [
            $this->enLaLista($activa, 'activa', 'Plaza de prueba Norte'),
            $this->enLaLista($pendiente, 'pendiente', 'Plaza de prueba Norte'),
            $this->enLaLista($suspendida, 'suspendida', 'Plaza de prueba Sur'),
            $this->enLaLista($deBaja, 'baja', 'Plaza de prueba Sur'),
        ]], $respuesta->json());

        // Las mismas que el panel: las que da el componente a la cuenta.
        $this->assertEqualsCanonicalizing(
            app(Autorizacion::class)->lavanderiasDelPanel($this->administracion)->pluck('id_publico')->all(),
            array_column($respuesta->json('lavanderias'), 'id_publico'),
        );
    }

    #[TestDox('administracion-app-E13: la búsqueda por nombre no distingue mayúsculas ni toma en cuenta los espacios de las orillas, busca el texto tal cual, sin comodines, y vacía o que no es texto trae todas')]
    public function test_administracion_app_e13_la_busqueda_por_nombre(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba Centro');
        $burbujas = Lavanderia::factory()->en($plaza)->create(['nombre' => 'Lavandería de prueba Burbujas']);
        $espuma = Lavanderia::factory()->en($plaza)->suspendida()->create(['nombre' => 'Lavandería de prueba Espuma']);
        $cien = Lavanderia::factory()->en($plaza)->create(['nombre' => 'Tintorería de prueba 100% Limpia']);

        $encontradas = fn (string $buscar): array => array_column(
            $this->pedirComoLaAdministracion('GET', self::LISTA, ['buscar' => $buscar])->assertOk()->json('lavanderias'),
            'id_publico',
        );

        $this->assertSame([$burbujas->id_publico], $encontradas('BURBU'));
        $this->assertSame([$espuma->id_publico], $encontradas('  espuma  '));
        $this->assertSame([$cien->id_publico], $encontradas('100%'));
        // Un % o un _ son texto, no comodines.
        $this->assertSame([$cien->id_publico], $encontradas('%'));
        $this->assertSame([], $encontradas('_'));
        $this->assertSame([], $encontradas('Sin coincidencia'));
        $this->assertSame([$burbujas->id_publico, $espuma->id_publico, $cien->id_publico], $encontradas(''));
        // Una búsqueda que no es texto, como buscar[]=x, no filtra: todas.
        $this->assertSame(
            [$burbujas->id_publico, $espuma->id_publico, $cien->id_publico],
            array_column(
                $this->pedirComoLaAdministracion('GET', self::LISTA, ['buscar' => ['Burbujas']])->assertOk()->json('lavanderias'),
                'id_publico',
            ),
        );
    }

    #[TestDox('administracion-app-E13 y administracion-app-E11: la ficha trae sus datos editables como los ve su socio en la app, con el recurso de la edición del panel, más su estado, su plaza y las transiciones que EstadoDeLaLavanderia permite para cada estado; con el ULID en mayúsculas, la misma ficha')]
    public function test_administracion_app_e13_la_ficha_trae_sus_datos_su_estado_su_plaza_y_sus_transiciones(): void
    {
        $this->freezeTime();
        $this->prepararAlta();
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta, ['nombre' => 'Lavandería de prueba Ficha']);
        $lavanderia->update(['descripcion' => 'Descripción de prueba de la ficha.']);
        $this->conFotos($lavanderia, 3);

        $ficha = $this->sinLaMarca(
            $this->pedirComoLaAdministracion('GET', self::LISTA."/{$lavanderia->id_publico}")->assertOk()->json('lavanderia'),
            $lavanderia,
        );

        $this->assertSame($this->fichaSegunElDominio($lavanderia), $ficha);
        // El ULID en mayúsculas es el mismo: la misma ficha.
        $this->assertSame(
            $ficha,
            $this->sinLaMarca(
                $this->pedirComoLaAdministracion('GET', self::LISTA.'/'.Str::upper($lavanderia->id_publico))->assertOk()->json('lavanderia'),
                $lavanderia,
            ),
        );
        // Sus datos editables son los que ve su socio en la app.
        $this->assertSame(
            $this->consultar($cuenta, $lavanderia->id_publico)->assertOk()->json('lavanderia'),
            array_diff_key($ficha, array_flip(['estado', 'plaza', 'transiciones'])),
        );
        $this->assertSame('Lavandería de prueba Ficha', $ficha['nombre']);
        $this->assertSame('Descripción de prueba de la ficha.', $ficha['descripcion']);
        $this->assertCount(3, $ficha['fotos']);
        $this->assertSame(['nombre' => $this->plaza->nombre], $ficha['plaza']);

        // Solo las transiciones que su estado permite: de la baja no sale ninguna.
        foreach ([
            Lavanderia::ACTIVA => ['suspender', 'dar_de_baja'],
            Lavanderia::PENDIENTE => ['suspender', 'dar_de_baja'],
            Lavanderia::SUSPENDIDA => ['levantar_suspension', 'dar_de_baja'],
            Lavanderia::BAJA => [],
        ] as $estado => $transiciones) {
            $lavanderia->update(['estado' => $estado]);
            $ficha = $this->pedirComoLaAdministracion('GET', self::LISTA."/{$lavanderia->id_publico}")->assertOk()->json('lavanderia');

            $this->assertSame($estado, $ficha['estado']);
            $this->assertSame($transiciones, $ficha['transiciones'], $estado);
            $this->assertSame($this->transicionesSegunElDominio($lavanderia), $ficha['transiciones'], $estado);
        }
    }

    #[TestDox('administracion-app-E12: una lavandería que no existe y una borrada responden 404 sin ningún dato en la ficha, la edición y las tres transiciones, la edición antes de validar; un valor que no es ULID, el 404 del enrutador con solo su message, también sin token; y nada cambia')]
    public function test_administracion_app_e12_una_lavanderia_que_no_existe_responde_404_sin_datos(): void
    {
        $borrada = Lavanderia::factory()->create();
        $borrada->delete();
        $antes = $this->fotoDeLaBase();

        foreach ([(string) Str::ulid(), $borrada->id_publico, Str::upper((string) Str::ulid())] as $id) {
            foreach ($this->rutasDeLaLavanderia($id) as [$metodo, $url, $cuerpo]) {
                $this->pedirComoLaAdministracion($metodo, $url, $cuerpo)
                    ->assertNotFound()
                    ->assertExactJson(self::NO_EXISTE);
            }
        }

        // La edición busca la lavandería antes de validar: con datos que no valen, también 404.
        $this->pedirComoLaAdministracion('PATCH', self::LISTA.'/'.Str::ulid(), ['nombre' => '', 'servicios' => 'no es una lista'])
            ->assertNotFound()
            ->assertExactJson(self::NO_EXISTE);

        // Un valor que no es ULID no llega a la ruta (whereUlid): el 404 del enrutador, antes de la sesión, también sin
        // token, con solo su message, que no es el de la lavandería.
        foreach ($this->rutasDeLaLavanderia('no-es-un-ulid') as [$metodo, $url, $cuerpo]) {
            foreach (['con su token' => $this->token, 'sin token' => null] as $quien => $token) {
                $respuesta = $this->llamarConToken($token, $metodo, $url, $cuerpo)->assertNotFound();
                $this->assertSame(['message'], array_keys((array) $respuesta->json()), "{$metodo} {$url} · {$quien}");
                $this->assertNotSame(self::NO_EXISTE['message'], $respuesta->json('message'), "{$metodo} {$url} · {$quien}");
            }
        }

        $this->assertSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E14 y administracion-app-E11: la administración cambia en la app el nombre, la dirección, los servicios, el horario y la descripción; queda igual que con EdicionDeLavanderia para los mismos datos, y la lavandería ve los datos nuevos en su perfil de la app')]
    public function test_administracion_app_e14_edita_sus_datos_como_la_edicion_del_panel(): void
    {
        $this->freezeTime();
        $this->prepararAlta();
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $gemela = $this->registrada();
        $datos = [
            'nombre' => 'Lavandería de prueba Editada',
            'direccion' => [
                'calle' => 'Calle de la administración',
                'numero' => '45',
                'colonia' => 'Colonia de prueba',
                'codigo_postal' => '99001',
                'municipio' => $this->municipio->codigo,
            ],
            'servicios' => [
                ['tipo' => 'estandar', 'precio' => '19.00'],
                ['tipo' => 'planchado', 'precio' => '26.50'],
            ],
            'ventanas' => [
                ['dia_semana' => 3, 'hora_inicio' => '16:00', 'hora_fin' => '17:00'],
                ['dia_semana' => 3, 'hora_inicio' => '17:00', 'hora_fin' => '18:00'],
            ],
            'descripcion' => 'Descripción de prueba desde la app de la administración.',
        ];

        $ficha = $this->pedirComoLaAdministracion('PATCH', self::LISTA."/{$lavanderia->id_publico}", $datos)
            ->assertOk()
            ->json('lavanderia');

        // La respuesta es la ficha ya con los cambios, y la lavandería la ve en su perfil de la app.
        $this->assertSame($this->fichaSegunElDominio($lavanderia), $this->sinLaMarca($ficha, $lavanderia));
        $this->assertSame('Lavandería de prueba Editada', $ficha['nombre']);
        $this->assertSame(['19.00', '26.50'], array_column($ficha['servicios'], 'precio'));
        $this->assertSame($datos['ventanas'], $ficha['ventanas']);
        $this->consultar($cuenta, $lavanderia->id_publico)
            ->assertOk()
            ->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba Editada')
            ->assertJsonPath('lavanderia.direccion.calle', 'Calle de la administración')
            ->assertJsonPath('lavanderia.descripcion', 'Descripción de prueba desde la app de la administración.');
        $this->perfilDelUsuario($lavanderia)->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba Editada');

        // La misma edición, con la clase que usa el panel, deja a otra lavandería igual: la API no agrega ni quita nada.
        $edicion = app(EdicionDeLavanderia::class);
        $edicion->editar($gemela, $edicion->validar($datos));
        $this->assertSame($this->editables($gemela), $this->editables($lavanderia));
    }

    #[TestDox('administracion-app-E14: la administración edita una lavandería en cualquier estado, pendiente, activa, suspendida o de baja, como el panel, que ofrece "Editar" en toda lavandería; la edición no cambia su estado ni sus transiciones')]
    public function test_administracion_app_e14_edita_una_lavanderia_en_cualquier_estado(): void
    {
        $this->freezeTime();
        $this->prepararAlta();
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);

        foreach ([Lavanderia::PENDIENTE, Lavanderia::ACTIVA, Lavanderia::SUSPENDIDA, Lavanderia::BAJA] as $estado) {
            $lavanderia->update(['estado' => $estado]);
            $transiciones = $this->transicionesSegunElDominio($lavanderia);

            $ficha = $this->pedirComoLaAdministracion('PATCH', self::LISTA."/{$lavanderia->id_publico}", [
                'nombre' => "Lavandería de prueba editada {$estado}",
                'descripcion' => "Descripción de prueba editada {$estado}.",
            ])->assertOk()->json('lavanderia');

            $this->assertSame($this->fichaSegunElDominio($lavanderia), $this->sinLaMarca($ficha, $lavanderia), $estado);
            $this->assertSame(
                [$estado, $transiciones, "Lavandería de prueba editada {$estado}", "Descripción de prueba editada {$estado}."],
                [$ficha['estado'], $ficha['transiciones'], $ficha['nombre'], $ficha['descripcion']],
                $estado,
            );
            $this->assertSame($estado, $lavanderia->fresh()->estado);
            // Su socio ve los datos nuevos en la app, en cualquier estado.
            $this->consultar($cuenta, $lavanderia->id_publico)
                ->assertOk()
                ->assertJsonPath('lavanderia.nombre', "Lavandería de prueba editada {$estado}");
        }
    }

    #[TestDox('administracion-app-E14: con fotos, la edición va en multipart por POST con _method=PATCH: quita una, cambia el orden y agrega una nueva en medio, y la foto principal sola reemplaza a la primera')]
    public function test_administracion_app_e14_edita_las_fotos_en_multipart(): void
    {
        $this->prepararAlta();
        $lavanderia = $this->registrada();
        [$primera, $segunda, $tercera] = $this->conFotos($lavanderia, 3);

        // La nueva va en medio: en multipart, Laravel junta los textos antes que los archivos, y el orden se respeta. Los
        // lugares son los de la lectura de la ficha, que va con su marca.
        $this->editarEnMultipart($lavanderia->id_publico, [
            'fotos' => [0 => 2, 1 => $this->fotoDePrueba('nueva.jpg'), 2 => 0],
            'fotos_leidas' => $this->marcaDeLaFicha($lavanderia),
        ])->assertOk()->assertJsonCount(3, 'lavanderia.fotos');

        $rutas = $this->rutasEnOrden($lavanderia);
        $this->assertSame($tercera, $rutas[0]);
        $this->assertNotContains($rutas[1], [$primera, $segunda, $tercera]);
        $this->assertSame($primera, $rutas[2]);
        $this->assertEqualsCanonicalizing($rutas, $this->archivosDeFotos());

        $this->editarEnMultipart($lavanderia->id_publico, ['foto' => $this->fotoDePrueba('principal.jpg')])->assertOk();

        $nuevas = $this->rutasEnOrden($lavanderia);
        $this->assertNotSame($tercera, $nuevas[0]);
        $this->assertSame([$rutas[1], $primera], array_slice($nuevas, 1));
        $this->assertEqualsCanonicalizing($nuevas, $this->archivosDeFotos());
    }

    #[TestDox('administracion-app-E14 y administracion-app-E11: un dato que no cumple las validaciones del registro responde 422 con el error de cada campo, los mismos que da EdicionDeLavanderia::validar(), y nada cambia')]
    public function test_administracion_app_e14_un_dato_que_no_cumple_las_reglas_responde_422_por_campo(): void
    {
        $this->prepararAlta();
        $lavanderia = $this->registrada();
        $this->conFotos($lavanderia, 2);
        $datos = [
            'nombre' => '',
            'direccion' => [
                'calle' => 'Calle de prueba',
                'numero' => '123',
                'colonia' => 'Colonia de prueba',
                'codigo_postal' => '123',
                'municipio' => $this->municipio->codigo,
            ],
            'servicios' => [['tipo' => 'estandar', 'precio' => '-1']],
            'ventanas' => [['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '11:30']],
            'descripcion' => str_repeat('a', 1001),
            'fotos' => [0, 7],
        ];
        $antes = $this->fotoDeLaBase();
        $archivos = $this->archivosDeFotos();
        $consultas = $this->geocodificador->consultas;

        $respuesta = $this->pedirComoLaAdministracion('PATCH', self::LISTA."/{$lavanderia->id_publico}", $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'nombre',
                'direccion.codigo_postal',
                'servicios.0.precio',
                'ventanas.0',
                'descripcion',
            ]);

        $this->assertSame($this->erroresSegunElDominio($datos), $respuesta->json('errors'));
        $this->assertSame('Falta el nombre de la lavandería.', $respuesta->json('errors.nombre.0'));
        $this->assertSame($antes, $this->fotoDeLaBase());
        $this->assertSame($archivos, $this->archivosDeFotos());
        // Se rechaza antes de ubicar la dirección.
        $this->assertSame($consultas, $this->geocodificador->consultas);

        // Las reglas de la lista de fotos, también: una foto que no tiene se rechaza en su lugar.
        $this->pedirComoLaAdministracion('PATCH', self::LISTA."/{$lavanderia->id_publico}", [
            'fotos' => [0, 7],
            'fotos_leidas' => $this->marcaDeLaFicha($lavanderia),
        ])->assertUnprocessable()->assertJsonValidationErrors(['fotos.1']);
        $this->assertSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E14 y administracion-app-E11: las fotos se cuentan sobre las que se leyeron al abrir, como el panel: si su socio las cambió después, la lista responde 422 en fotos con el mensaje de FotosDeLaLavanderia, el mismo que da EdicionDeLavanderia con esas fotos leídas, y nada cambia; sin la marca de la lectura, o con una que no dio el servidor, también; con la marca de una lectura nueva, se guarda; y sin lista de fotos, la marca no cuenta')]
    public function test_administracion_app_e14_las_fotos_se_cuentan_sobre_las_leidas_al_abrir(): void
    {
        $this->prepararAlta();
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        [$primera, $segunda, $tercera] = $this->conFotos($lavanderia, 3);
        $url = self::LISTA."/{$lavanderia->id_publico}";

        // La administración abre la edición, y su socio, desde su app, quita la segunda foto.
        $leida = $this->marcaDeLaFicha($lavanderia);
        $this->editar($cuenta, $lavanderia->id_publico, ['fotos' => [0, 2]])->assertOk();
        $archivos = $this->archivosDeFotos();

        // Con los lugares de su lectura, [1, 0] nombraría la tercera foto como la segunda: se rechaza.
        $respuesta = $this->pedirComoLaAdministracion('PATCH', $url, ['fotos' => [1, 0], 'fotos_leidas' => $leida])
            ->assertUnprocessable();

        $this->assertSame(['fotos' => [FotosDeLaLavanderia::FOTOS_QUE_CAMBIARON]], $respuesta->json('errors'));
        $this->assertSame([$primera, $tercera], $this->rutasEnOrden($lavanderia));
        $this->assertSame($archivos, $this->archivosDeFotos());

        // Es lo que da la clase que usa el panel con las fotos que leyó: la API no copia la regla.
        $this->assertSame(
            $respuesta->json('errors'),
            $this->erroresDeLaEdicion($lavanderia, ['fotos' => [1, 0]], [$primera, $segunda, $tercera]),
        );

        // Sin la marca, o con una que no dio el servidor, la lista no nombra las fotos que tiene: también se rechaza.
        foreach ([[], ['fotos_leidas' => 'una-marca-que-no-dio-el-servidor'], ['fotos_leidas' => ['no', 'es', 'texto']]] as $marca) {
            $this->pedirComoLaAdministracion('PATCH', $url, ['fotos' => [1, 0], ...$marca])
                ->assertUnprocessable()
                ->assertExactJson([
                    'message' => FotosDeLaLavanderia::FOTOS_QUE_CAMBIARON,
                    'errors' => ['fotos' => [FotosDeLaLavanderia::FOTOS_QUE_CAMBIARON]],
                ]);
        }
        $this->assertSame([$primera, $tercera], $this->rutasEnOrden($lavanderia));

        // Al volver a abrir la edición, la marca nueva cuenta los lugares sobre las dos fotos que tiene.
        $this->pedirComoLaAdministracion('PATCH', $url, ['fotos' => [1, 0], 'fotos_leidas' => $this->marcaDeLaFicha($lavanderia)])
            ->assertOk()
            ->assertJsonCount(2, 'lavanderia.fotos');
        $this->assertSame([$tercera, $primera], $this->rutasEnOrden($lavanderia));

        // Sin lista de fotos, la marca no cuenta: el nombre se guarda aunque las fotos hayan cambiado.
        $this->pedirComoLaAdministracion('PATCH', $url, ['nombre' => 'Lavandería de prueba con otro nombre', 'fotos_leidas' => $leida])
            ->assertOk()
            ->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba con otro nombre');
    }

    #[TestDox('administracion-app-E14: una dirección nueva fuera de las zonas responde 422 en la dirección con su motivo, y si la geocodificación no responde, 503; en los dos casos nada cambia')]
    public function test_administracion_app_e14_la_direccion_que_no_se_ubica_y_la_geocodificacion_que_no_responde(): void
    {
        $this->prepararAlta();
        $lavanderia = $this->registrada();
        $direccion = ['direccion' => [
            'calle' => 'Calle lejana',
            'numero' => '1',
            'colonia' => 'Colonia de prueba',
            'codigo_postal' => '99000',
            'municipio' => $this->municipio->codigo,
        ]];
        $antes = $this->fotoDeLaBase();
        $this->geocodificarEn([30.0, -100.0]);

        $this->pedirComoLaAdministracion('PATCH', self::LISTA."/{$lavanderia->id_publico}", $direccion)
            ->assertUnprocessable()
            ->assertJsonPath('motivo', 'fuera_de_la_zona_de_servicio')
            ->assertJsonPath('errors.direccion.0', 'La dirección está fuera de las zonas de servicio.');
        $this->assertSame($antes, $this->fotoDeLaBase());

        $this->app->instance(Geocodificador::class, new class implements Geocodificador
        {
            public function geocodificar(string $texto, Municipio $municipio): ?Punto
            {
                throw GeocodificacionNoDisponible::porque('sin red');
            }
        });

        $this->pedirComoLaAdministracion('PATCH', self::LISTA."/{$lavanderia->id_publico}", $direccion)
            ->assertStatus(503)
            ->assertExactJson([
                'message' => 'No pudimos ubicar la dirección. Inténtalo más tarde.',
                'motivo' => 'geocodificacion_no_disponible',
            ]);
        $this->assertSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E11: la validación que movió la tarea 3.2 es una para la app, la API y el panel: sin lo leído valida cada campo que llega; con lo leído al abrir, solo los que cambiaron, y las fotos en sus lugares de siempre no cambian, pero una nueva sí; la lista de fotos se toma en el orden de sus lugares')]
    public function test_administracion_app_e11_la_validacion_de_la_edicion_es_una_para_la_app_la_api_y_el_panel(): void
    {
        $this->prepararAlta();
        $edicion = app(EdicionDeLavanderia::class);

        // Sin lo leído, como la app: cada campo que llega, y lo que no es de la edición no cuenta.
        $this->assertSame(['nombre' => 'Nombre nuevo'], $edicion->validar(['nombre' => 'Nombre nuevo', '_method' => 'PATCH']));
        $this->assertSame(['nombre' => ['Falta el nombre de la lavandería.']], $this->erroresSegunElDominio(['nombre' => '']));

        // Con lo leído, como el panel: una dirección sin municipio y una franja larga no se validan si no cambiaron.
        $leidos = [
            'nombre' => 'Nombre de antes',
            'descripcion' => null,
            'direccion' => [
                'calle' => 'Calle',
                'numero' => '1',
                'colonia' => 'Colonia',
                'codigo_postal' => '00000',
                'municipio' => null,
            ],
            'servicios' => [['tipo' => 'estandar', 'precio' => '18.50']],
            'ventanas' => [['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '14:00']],
            'fotos' => [0, 1],
        ];
        // Los servicios se comparan con ==, como los devuelve el formulario: 18.5 es el mismo precio que 18.50.
        $mismosServicios = [['tipo' => 'estandar', 'precio' => '18.5']];
        $this->assertSame(
            ['nombre' => 'Nombre nuevo'],
            $edicion->validar([...$leidos, 'nombre' => 'Nombre nuevo', 'servicios' => $mismosServicios], $leidos),
        );

        // Las fotos se comparan idénticas: otro orden cambia, y una foto nueva nunca es un lugar.
        $this->assertSame(['fotos' => [1, 0]], $edicion->validar([...$leidos, 'fotos' => [1, 0]], $leidos));
        $nueva = $this->fotoDePrueba('nueva.jpg');
        $this->assertSame(['fotos' => [0, $nueva]], $edicion->validar([...$leidos, 'fotos' => [0, $nueva]], $leidos));

        // En multipart los archivos llegan después de los textos: la lista se toma en el orden de sus lugares.
        $this->assertSame(['fotos' => [0 => $nueva, 1 => 0]], $edicion->validar(['fotos' => [1 => 0, 0 => $nueva]]));
    }

    #[TestDox('administracion-app-E15 y administracion-app-E11: la administración suspende una lavandería activa y le levanta la suspensión con la regla de alta-lavanderias-E14 y E15; después de cada acción la ficha trae su estado nuevo y solo las transiciones que permite')]
    public function test_administracion_app_e15_suspender_y_levantar_la_suspension(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba Centro');
        $activa = Lavanderia::factory()->en($plaza)->create();
        $url = self::LISTA."/{$activa->id_publico}/suspension";

        $ficha = $this->pedirComoLaAdministracion('POST', $url)->assertOk()->json('lavanderia');

        $this->assertSame([Lavanderia::SUSPENDIDA, ['levantar_suspension', 'dar_de_baja']], [$ficha['estado'], $ficha['transiciones']]);
        $this->assertSame(Lavanderia::SUSPENDIDA, $activa->fresh()->estado);
        $this->assertSame($this->transicionesSegunElDominio($activa), $ficha['transiciones']);
        $this->assertFalse(Lavanderia::query()->visiblesParaElUsuario()->whereKey($activa->getKey())->exists());

        $ficha = $this->pedirComoLaAdministracion('DELETE', $url)->assertOk()->json('lavanderia');

        $this->assertSame([Lavanderia::ACTIVA, ['suspender', 'dar_de_baja']], [$ficha['estado'], $ficha['transiciones']]);
        $this->assertSame(Lavanderia::ACTIVA, $activa->fresh()->estado);
        $this->assertTrue(Lavanderia::query()->visiblesParaElUsuario()->whereKey($activa->getKey())->exists());

        // Una que nunca tuvo alta vuelve a pendiente, como en el panel (alta-lavanderias-E15).
        $pendiente = Lavanderia::factory()->en($plaza)->pendiente()->create();
        $url = self::LISTA."/{$pendiente->id_publico}/suspension";
        $this->pedirComoLaAdministracion('POST', $url)->assertOk()->assertJsonPath('lavanderia.estado', Lavanderia::SUSPENDIDA);
        $this->pedirComoLaAdministracion('DELETE', $url)
            ->assertOk()
            ->assertJsonPath('lavanderia.estado', Lavanderia::PENDIENTE)
            ->assertJsonPath('lavanderia.transiciones', ['suspender', 'dar_de_baja']);
        $this->assertSame(Lavanderia::PENDIENTE, $pendiente->fresh()->estado);
    }

    #[TestDox('administracion-app-E16: la administración da de baja una lavandería con pedidos entregados con la regla de alta-lavanderias-E17: queda de baja, sin borrarse, con sus datos y su historial, y su ficha ya no ofrece ninguna transición')]
    public function test_administracion_app_e16_dar_de_baja_conserva_sus_datos_y_su_historial(): void
    {
        $this->prepararPedido();
        $usuario = $this->cuentaDeUsuario();
        $entregado = $this->llevarHastaEntregado($this->pedidoAceptadoDe($usuario));
        $servicios = $this->lavanderia->servicios()->count();
        $horarios = $this->lavanderia->horarios()->count();

        $ficha = $this->pedirComoLaAdministracion('POST', self::LISTA."/{$this->lavanderia->id_publico}/baja")
            ->assertOk()
            ->json('lavanderia');

        $this->assertSame([Lavanderia::BAJA, []], [$ficha['estado'], $ficha['transiciones']]);
        $this->assertSame($this->transicionesSegunElDominio($this->lavanderia), []);

        // La fila se queda, sin borrado suave, con sus servicios y su horario, y su pedido entregado sigue apuntando a ella.
        $fila = DB::table('lavanderias')->where('id_lavanderias', $this->lavanderia->getKey())->sole();
        $this->assertSame([Lavanderia::BAJA, null], [$fila->estado, $fila->deleted_at]);
        $this->assertSame(
            [$servicios, $horarios],
            [$this->lavanderia->servicios()->count(), $this->lavanderia->horarios()->count()],
        );
        $this->assertSame(1, Pedido::count());
        $entregado->refresh();
        $this->assertSame(
            [$this->lavanderia->getKey(), EstadoPedido::ENTREGADO],
            [$entregado->id_lavanderias, $entregado->estado->codigo],
        );
        $this->consultarPedido($usuario, $entregado->id_publico)->assertOk();
        $this->assertFalse(Lavanderia::query()->visiblesParaElUsuario()->whereKey($this->lavanderia->getKey())->exists());

        // La administración la sigue viendo, en la lista y en su ficha.
        $this->pedirComoLaAdministracion('GET', self::LISTA)->assertOk()->assertJsonPath('lavanderias.0.estado', Lavanderia::BAJA);
        $this->pedirComoLaAdministracion('GET', self::LISTA."/{$this->lavanderia->id_publico}")
            ->assertOk()
            ->assertJsonPath('lavanderia.transiciones', []);
    }

    #[TestDox('administracion-app-E12: una lavandería que otra sesión acaba de dar de baja responde 409 al suspenderla, con el mensaje de EstadoDeLaLavanderia, y nada cambia; igual levantar la suspensión de una activa o dar de baja otra vez')]
    public function test_administracion_app_e12_una_transicion_que_ya_no_se_puede_responde_409_con_el_mensaje_del_dominio(): void
    {
        $lavanderia = Lavanderia::factory()->create();
        $activa = Lavanderia::factory()->create();
        $url = self::LISTA."/{$lavanderia->id_publico}";

        // La administración abre la ficha, que ofrece suspender, y otra sesión la da de baja, por ejemplo desde el panel.
        $this->pedirComoLaAdministracion('GET', $url)->assertOk()->assertJsonPath('lavanderia.transiciones', ['suspender', 'dar_de_baja']);
        app(EstadoDeLaLavanderia::class)->darDeBaja($lavanderia);
        $antes = $this->fotoDeLaBase();

        $this->pedirComoLaAdministracion('POST', "{$url}/suspension")
            ->assertConflict()
            ->assertExactJson(['message' => 'Una lavandería en estado baja no se puede suspender.']);
        $this->pedirComoLaAdministracion('POST', "{$url}/baja")
            ->assertConflict()
            ->assertExactJson(['message' => 'Una lavandería en estado baja no se puede dar de baja.']);
        $this->pedirComoLaAdministracion('DELETE', self::LISTA."/{$activa->id_publico}/suspension")
            ->assertConflict()
            ->assertExactJson(['message' => 'Una lavandería en estado activa no se puede levantar de una suspensión.']);

        // Cada mensaje es el de la clase de dominio para el mismo cambio.
        $this->assertSame([
            'Una lavandería en estado baja no se puede suspender.',
            'Una lavandería en estado baja no se puede dar de baja.',
            'Una lavandería en estado activa no se puede levantar de una suspensión.',
        ], [
            $this->mensajeDelDominio(fn (EstadoDeLaLavanderia $estado) => $estado->suspender($lavanderia->fresh())),
            $this->mensajeDelDominio(fn (EstadoDeLaLavanderia $estado) => $estado->darDeBaja($lavanderia->fresh())),
            $this->mensajeDelDominio(fn (EstadoDeLaLavanderia $estado) => $estado->levantarSuspension($activa->fresh())),
        ]);
        $this->assertSame($antes, $this->fotoDeLaBase());
        $this->assertSame([Lavanderia::BAJA, Lavanderia::ACTIVA], [$lavanderia->fresh()->estado, $activa->fresh()->estado]);
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: cada ruta de las lavanderías la abre solo la administración con su token: las cuentas de usuario, lavandería y repartidor y un token sin la habilidad reciben 403 sin datos, sin sesión o con el token vencido 401, y nada cambia')]
    public function test_administracion_app_e06_y_e07_cada_ruta_de_las_lavanderias_solo_la_abre_la_administracion(): void
    {
        $lavanderia = Lavanderia::factory()->create(['nombre' => 'Lavandería de prueba sin cambios']);

        $this->assertSoloEntraLaAdministracion('GET', self::LISTA);

        foreach ($this->rutasDeLaLavanderia($lavanderia->id_publico) as [$metodo, $url, $cuerpo]) {
            $this->assertSoloEntraLaAdministracion($metodo, $url, $cuerpo);
        }

        $lavanderia->refresh();
        $this->assertSame(['Lavandería de prueba sin cambios', Lavanderia::ACTIVA], [$lavanderia->nombre, $lavanderia->estado]);

        // La administración sí entra: el rechazo no es de toda llamada.
        $this->pedirComoLaAdministracion('POST', self::LISTA."/{$lavanderia->id_publico}/suspension")->assertOk();
        $this->assertSame(Lavanderia::SUSPENDIDA, $lavanderia->fresh()->estado);
    }

    #[TestDox('administracion-app-E13: el contrato de la lista con la app: cada lavandería con su id_publico, su nombre, su estado y su plaza; con una búsqueda sin coincidencias, la lista vacía (design.md, F-D01)')]
    public function test_administracion_app_e13_el_contrato_de_la_lista(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba Norte');
        Lavanderia::factory()->en($plaza)->create(['nombre' => 'Lavandería de prueba A Activa']);
        Lavanderia::factory()->en($this->plazaDePrueba('Plaza de prueba Sur'))->suspendida()->create(['nombre' => 'Lavandería de prueba B Suspendida']);

        $this->assertCumpleSuContrato('lavanderias.lista', 'GET /api/administracion/lavanderias', $this->pedirComoLaAdministracion('GET', self::LISTA));
        $this->assertCumpleSuContrato(
            'lavanderias.lista-vacia',
            'GET /api/administracion/lavanderias',
            $this->pedirComoLaAdministracion('GET', self::LISTA, ['buscar' => 'Sin coincidencia']),
        );
    }

    #[TestDox('administracion-app-E13 y administracion-app-E12: el contrato de la ficha con la app: sus datos editables con sus fotos, sus servicios y sus ventanas, su estado, su plaza y sus transiciones en una lista; y el 404 de la que no existe')]
    public function test_administracion_app_e13_el_contrato_de_la_ficha(): void
    {
        $lavanderia = $this->lavanderiaDelContrato();

        $this->assertCumpleSuContrato(
            'lavanderias.ficha',
            'GET /api/administracion/lavanderias/{lavanderia}',
            $this->pedirComoLaAdministracion('GET', self::LISTA."/{$lavanderia->id_publico}"),
        );
        $this->assertCumpleSuContrato(
            'lavanderias.no-existe',
            'GET /api/administracion/lavanderias/{lavanderia}',
            $this->pedirComoLaAdministracion('GET', self::LISTA.'/'.Str::ulid()),
            404,
        );
    }

    #[TestDox('administracion-app-E14: el contrato de la edición con la app: la ficha ya con los cambios, y el 422 con el error de cada campo')]
    public function test_administracion_app_e14_el_contrato_de_la_edicion(): void
    {
        $lavanderia = $this->lavanderiaDelContrato();
        $url = self::LISTA."/{$lavanderia->id_publico}";

        $this->assertCumpleSuContrato(
            'lavanderias.edicion',
            'PATCH /api/administracion/lavanderias/{lavanderia}',
            $this->pedirComoLaAdministracion('PATCH', $url, ['nombre' => 'Lavandería de prueba Editada', 'descripcion' => 'Descripción de prueba editada.']),
        );
        $this->assertCumpleSuContrato(
            'lavanderias.edicion-invalida',
            'PATCH /api/administracion/lavanderias/{lavanderia}',
            $this->pedirComoLaAdministracion('PATCH', $url, ['nombre' => '', 'servicios' => [['tipo' => 'estandar', 'precio' => '-1']]]),
            422,
        );
    }

    #[TestDox('administracion-app-E15 y administracion-app-E12: el contrato de la suspensión con la app: la ficha de la suspendida, la de la que vuelve a estar activa y el 409 de la que ya está suspendida')]
    public function test_administracion_app_e15_el_contrato_de_la_suspension(): void
    {
        $lavanderia = $this->lavanderiaDelContrato();
        $url = self::LISTA."/{$lavanderia->id_publico}/suspension";

        $this->assertCumpleSuContrato(
            'lavanderias.suspension',
            'POST /api/administracion/lavanderias/{lavanderia}/suspension',
            $this->pedirComoLaAdministracion('POST', $url),
        );
        $this->assertCumpleSuContrato(
            'lavanderias.suspension-ya-no-se-puede',
            'POST /api/administracion/lavanderias/{lavanderia}/suspension',
            $this->pedirComoLaAdministracion('POST', $url),
            409,
        );
        $this->assertCumpleSuContrato(
            'lavanderias.levantar-suspension',
            'DELETE /api/administracion/lavanderias/{lavanderia}/suspension',
            $this->pedirComoLaAdministracion('DELETE', $url),
        );
    }

    #[TestDox('administracion-app-E16: el contrato de la baja con la app: la ficha de la lavandería de baja, sin ninguna transición')]
    public function test_administracion_app_e16_el_contrato_de_la_baja(): void
    {
        $lavanderia = $this->lavanderiaDelContrato();

        $this->assertCumpleSuContrato(
            'lavanderias.baja',
            'POST /api/administracion/lavanderias/{lavanderia}/baja',
            $this->pedirComoLaAdministracion('POST', self::LISTA."/{$lavanderia->id_publico}/baja"),
        );
    }

    /**
     * La lavandería de los contratos: registrada completa desde la app, con su descripción y dos fotos, y ya activa, para
     * que la ficha traiga cada parte con datos.
     */
    private function lavanderiaDelContrato(): Lavanderia
    {
        $this->freezeTime();
        $this->prepararAlta();
        $lavanderia = $this->registrada(null, ['nombre' => 'Lavandería de prueba del contrato']);
        $lavanderia->update(['descripcion' => 'Descripción de prueba del contrato.', 'estado' => Lavanderia::ACTIVA]);
        $this->conFotos($lavanderia, 2);

        return $lavanderia;
    }

    /**
     * La administración llama a la API con su token.
     *
     * @param  array<string, mixed>  $cuerpo  El cuerpo, o la consulta de un GET.
     */
    private function pedirComoLaAdministracion(string $metodo, string $url, array $cuerpo = []): TestResponse
    {
        return $this->llamarConToken($this->token, $metodo, $url, $cuerpo);
    }

    /**
     * La administración edita la lavandería con archivos: multipart por POST, con _method=PATCH, como la app.
     *
     * @param  array<string, mixed>  $datos
     */
    private function editarEnMultipart(string $lavanderia, array $datos): TestResponse
    {
        config(['app.debug' => false]);
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)
            ->withHeader('Accept', 'application/json')
            ->post(self::LISTA."/{$lavanderia}", ['_method' => 'PATCH', ...$datos]);
    }

    /**
     * Las rutas que nombran una lavandería, cada una con un cuerpo que la cambiaría: la ficha, la edición y las tres
     * transiciones.
     *
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function rutasDeLaLavanderia(string $lavanderia): array
    {
        $url = self::LISTA."/{$lavanderia}";

        return [
            ['GET', $url, []],
            ['PATCH', $url, ['nombre' => 'Lavandería de prueba cambiada']],
            ['POST', "{$url}/suspension", []],
            ['DELETE', "{$url}/suspension", []],
            ['POST', "{$url}/baja", []],
        ];
    }

    /**
     * @return array{id_publico: string, nombre: string, estado: string, plaza: array{nombre: string}}
     */
    private function enLaLista(Lavanderia $lavanderia, string $estado, string $plaza): array
    {
        return [
            'id_publico' => $lavanderia->id_publico,
            'nombre' => $lavanderia->nombre,
            'estado' => $estado,
            'plaza' => ['nombre' => $plaza],
        ];
    }

    /**
     * La ficha que dan, para la lavandería como está en la base, las clases que usa el panel: el recurso de su edición,
     * su estado, su plaza y las transiciones de EstadoDeLaLavanderia.
     *
     * @return array<string, mixed>
     */
    private function fichaSegunElDominio(Lavanderia $lavanderia): array
    {
        $actual = LavanderiaDelSocioResource::cargar($lavanderia->fresh());

        return [
            ...(new LavanderiaDelSocioResource($actual))->resolve(),
            'estado' => $actual->estado,
            'plaza' => ['nombre' => Plaza::query()->findOrFail($actual->id_plazas)->nombre],
            'transiciones' => $this->transicionesSegunElDominio($actual),
        ];
    }

    /**
     * La ficha sin la marca de su lectura, después de comprobar que la marca nombra las fotos que la lavandería tiene, en
     * su orden: la marca es un texto cifrado que cambia en cada respuesta, así que se compara por lo que nombra.
     *
     * @param  array<string, mixed>  $ficha
     * @return array<string, mixed>
     */
    private function sinLaMarca(array $ficha, Lavanderia $lavanderia): array
    {
        $this->assertIsString($ficha['fotos_leidas'] ?? null);
        $this->assertSame($this->rutasEnOrden($lavanderia), app(FotosDeLaLavanderia::class)->rutasDeLaMarca($ficha['fotos_leidas']));

        return array_diff_key($ficha, array_flip(['fotos_leidas']));
    }

    /**
     * La marca de las fotos de la ficha que la administración abre ahora: la que la app devuelve con la lista de fotos.
     */
    private function marcaDeLaFicha(Lavanderia $lavanderia): string
    {
        return $this->pedirComoLaAdministracion('GET', self::LISTA."/{$lavanderia->id_publico}")
            ->assertOk()
            ->json('lavanderia.fotos_leidas');
    }

    /**
     * Los errores que da la edición del panel para esos datos, con esas fotos leídas al abrir.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $fotosLeidas
     * @return array<string, list<string>>
     */
    private function erroresDeLaEdicion(Lavanderia $lavanderia, array $datos, array $fotosLeidas): array
    {
        $edicion = app(EdicionDeLavanderia::class);

        try {
            $edicion->editar($lavanderia, $edicion->validar($datos), fotosLeidas: $fotosLeidas);
        } catch (ValidationException $error) {
            return $error->errors();
        }

        $this->fail('La edición aceptó las fotos.');
    }

    /**
     * Las transiciones que EstadoDeLaLavanderia permite a la lavandería como está en la base, con el nombre de su ruta.
     *
     * @return list<string>
     */
    private function transicionesSegunElDominio(Lavanderia $lavanderia): array
    {
        $estado = app(EstadoDeLaLavanderia::class);
        $actual = $lavanderia->fresh();

        return array_keys(array_filter([
            'suspender' => $estado->puedeSuspenderse($actual),
            'levantar_suspension' => $estado->puedeLevantarseLaSuspension($actual),
            'dar_de_baja' => $estado->puedeDarseDeBaja($actual),
        ]));
    }

    /**
     * Los datos de la lavandería que la edición cambia, sin su ULID ni sus fotos.
     *
     * @return array<string, mixed>
     */
    private function editables(Lavanderia $lavanderia): array
    {
        $datos = (new LavanderiaDelSocioResource(LavanderiaDelSocioResource::cargar($lavanderia->fresh())))->resolve();

        return array_diff_key($datos, array_flip(['id_publico', 'foto', 'fotos']));
    }

    /**
     * Los errores que da la validación de la edición para esos datos.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, list<string>>
     */
    private function erroresSegunElDominio(array $datos): array
    {
        try {
            app(EdicionDeLavanderia::class)->validar($datos);
        } catch (ValidationException $error) {
            return $error->errors();
        }

        $this->fail('La validación de la edición aceptó los datos.');
    }

    /**
     * El mensaje con que EstadoDeLaLavanderia rechaza el cambio.
     *
     * @param  callable(EstadoDeLaLavanderia): void  $cambio
     */
    private function mensajeDelDominio(callable $cambio): string
    {
        try {
            $cambio(app(EstadoDeLaLavanderia::class));
        } catch (TransicionNoPermitida $error) {
            return $error->getMessage();
        }

        $this->fail('EstadoDeLaLavanderia permitió el cambio.');
    }
}
