<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Autorizacion\Autorizacion;
use App\Http\Middleware\Autorizar;
use App\Identidad\TokensDeLaApp;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\Autorizacion\Soporte\TablaDeRutas;
use Tests\TestCase;

/**
 * La puerta de la API de la administración: las rutas de /api/administracion las abre solo la cuenta de administración
 * con su token de administración. Un token suyo sin la habilidad, como los "*" que salieron por Google antes de
 * ADR-0022, o su sesión web del panel, reciben 403 sin datos, también en la consulta de la cuenta, y el token sin la
 * habilidad, también en el cierre de la sesión; las cuentas de los otros roles, 403; sin sesión, 401; y nada cambia
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de usuario; :10:Cláusula X; ADR-0019, decisión 1;
 * ADR-0022, decisiones 4, 7 y 8).
 *
 * Mientras el grupo no tenga rutas propias, la puerta se prueba con una ruta de prueba registrada aquí con el mismo
 * middleware, el mismo prefijo y el mismo nombre que el grupo de routes/api.php. El recorrido de las rutas reales que se
 * llaman administracion.* crece solo con cada función que llega.
 *
 * Escenarios: administracion-app-E06 y administracion-app-E07 · Spec:
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md · Tarea 1.3
 */
class AccesoTest extends TestCase
{
    use RefreshDatabase;
    use SesionesDeLaApp;
    use TablaDeRutas;

    /** La ruta de prueba que solo lee, y lo que responde a quien sí entra. */
    private const LECTURA = '/api/administracion/prueba/acceso';

    private const DATO_DE_LA_ADMINISTRACION = 'DatoDeLaAdministracionDePrueba';

    /** La ruta de prueba que cambia la base: crea una cuenta con este correo. */
    private const CAMBIO = '/api/administracion/prueba/cambio';

    private const CORREO_DEL_CAMBIO = 'creada.por.la.ruta.de.prueba@ejemplo.com';

    #[TestDox('administracion-app-E07: la cuenta de administración, con su token, sí entra a una ruta de /api/administracion; las de usuario, lavandería y repartidor reciben 403 sin datos, sin sesión o con el token vencido 401, y nada cambia')]
    public function test_administracion_app_e07_ningun_otro_rol_entra_a_la_administracion(): void
    {
        $this->registrarLasRutasDePrueba();

        $this->assertSoloEntraLaAdministracion('GET', self::LECTURA);
        $this->assertSoloEntraLaAdministracion('POST', self::CAMBIO, ['nombre' => 'Cambio de prueba']);
        $this->assertSame(0, Usuario::query()->where('correo', self::CORREO_DEL_CAMBIO)->count());

        // La administración sí entra: el rechazo no es de toda llamada.
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $this->llamarConToken($token, 'GET', self::LECTURA)->assertOk()->assertJsonPath('dato', self::DATO_DE_LA_ADMINISTRACION);
        $antes = $this->fotoDeLaBase();
        $this->llamarConToken($token, 'POST', self::CAMBIO)->assertOk();
        $this->assertSame(1, Usuario::query()->where('correo', self::CORREO_DEL_CAMBIO)->count());
        // Y la foto de la base sí ve un cambio: la comparación de arriba no pasa en vacío.
        $this->assertNotSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E06: un token de la cuenta de administración sin la habilidad administracion, con "*", recibe 403 sin datos en las rutas de /api/administracion, y nada cambia: "*" no cuenta como administracion')]
    public function test_administracion_app_e06_un_token_sin_la_habilidad_no_abre_la_administracion(): void
    {
        $this->registrarLasRutasDePrueba();
        $administracion = $this->cuentaDeAdministracion();
        $sinLaHabilidad = $this->tokenSinLaHabilidad($administracion);
        $this->assertSame(['*'], PersonalAccessToken::findToken($sinLaHabilidad)?->abilities);
        $antes = $this->fotoDeLaBase();

        foreach ([['GET', self::LECTURA], ['POST', self::CAMBIO]] as [$metodo, $url]) {
            $this->llamarConToken($sinLaHabilidad, $metodo, $url)
                ->assertForbidden()
                ->assertExactJson(self::RESPUESTA_SIN_ACCESO);
        }

        $this->assertSame($antes, $this->fotoDeLaBase());
        $this->assertSame(0, Usuario::query()->where('correo', self::CORREO_DEL_CAMBIO)->count());
    }

    #[TestDox('administracion-app-E06: con un token sin la habilidad, GET /api/cuenta y DELETE /api/sesion también responden 403 sin datos, y el token no se revoca')]
    public function test_administracion_app_e06_un_token_sin_la_habilidad_no_abre_la_cuenta_ni_cierra_la_sesion(): void
    {
        $administracion = $this->cuentaDeAdministracion();
        $sinLaHabilidad = $this->tokenSinLaHabilidad($administracion);
        $antes = $this->fotoDeLaBase();

        $this->llamarConToken($sinLaHabilidad, 'GET', '/api/cuenta')->assertForbidden()->assertExactJson(self::RESPUESTA_SIN_ACCESO);
        $this->llamarConToken($sinLaHabilidad, 'DELETE', '/api/sesion')->assertForbidden()->assertExactJson(self::RESPUESTA_SIN_ACCESO);

        $this->assertSame($antes, $this->fotoDeLaBase());
        $this->assertSame(1, $administracion->tokens()->count());
    }

    #[TestDox('administracion-app-E06: cuando la cuenta de administración inicia sesión con su correo y su contraseña, se borran sus tokens sin la habilidad, y solo esos: sus tokens de administración y los de otras cuentas siguen')]
    public function test_administracion_app_e06_al_iniciar_sesion_con_contrasena_se_borran_los_tokens_sin_la_habilidad(): void
    {
        $administracion = $this->cuentaDeAdministracion(['correo' => 'duenio@ejemplo.com']);
        $deAdministracion = $this->tokenDeLaApp($administracion);
        $deGoogle = $this->tokenSinLaHabilidad($administracion);
        $otroDeGoogle = $this->tokenSinLaHabilidad($administracion);
        $usuario = $this->cuentaQueOperaComo(Rol::USUARIO);
        $delUsuario = $this->tokenDeLaApp($usuario);
        $this->assertSame(3, $administracion->tokens()->count());

        $nuevo = $this->llamarConToken(null, 'POST', '/api/sesion', [
            'correo' => 'duenio@ejemplo.com',
            'password' => UsuarioFactory::CONTRASENA,
        ])->assertOk()->json('token');

        $this->assertNull(PersonalAccessToken::findToken($deGoogle));
        $this->assertNull(PersonalAccessToken::findToken($otroDeGoogle));
        $this->assertSame(2, $administracion->tokens()->count());
        $this->assertSame(
            [TokensDeLaApp::NOMBRE_DE_ADMINISTRACION],
            $administracion->tokens()->pluck('name')->unique()->values()->all(),
        );

        $this->llamarConToken($deGoogle, 'GET', '/api/cuenta')->assertUnauthorized();
        $this->llamarConToken($deAdministracion, 'GET', '/api/cuenta')->assertOk();
        $this->llamarConToken($nuevo, 'GET', '/api/cuenta')->assertOk();
        $this->llamarConToken($delUsuario, 'GET', '/api/cuenta')->assertOk();
        $this->assertSame(1, $usuario->tokens()->count());
    }

    #[TestDox('administracion-app-E06: la sesión web de la cuenta de administración, que Sanctum da por buena con un TransientToken, recibe 403 sin datos en una ruta de /api/administracion y en GET /api/cuenta, y nada cambia; sin token o con un TransientToken, el componente no deja pasar al rol de administración')]
    public function test_administracion_app_e06_la_sesion_web_no_abre_la_api_de_la_administracion(): void
    {
        $this->registrarLasRutasDePrueba();
        $administracion = $this->cuentaDeAdministracion();
        $autorizacion = app(Autorizacion::class);

        $this->assertFalse($autorizacion->permiteEnLaApi($administracion, Rol::ADMINISTRACION, null));
        $this->assertFalse($autorizacion->permiteEnLaApi($administracion, Rol::ADMINISTRACION, new TransientToken));
        // Con su token de administración, sí: la regla no rechaza toda llamada.
        $this->assertTrue($autorizacion->permiteEnLaApi(
            $administracion,
            Rol::ADMINISTRACION,
            app(TokensDeLaApp::class)->crear($administracion)->accessToken,
        ));

        // La sesión web es la del panel de respaldo: Sanctum revisa su guardia web antes que el token, y a la cuenta que
        // encuentra ahí le pone un TransientToken, sin habilidades guardadas.
        config(['app.debug' => false]);
        $antes = $this->fotoDeLaBase();

        foreach ([self::LECTURA, '/api/cuenta'] as $url) {
            $this->app['auth']->forgetGuards();
            $this->withoutToken()->actingAs($administracion, 'web')->getJson($url)
                ->assertForbidden()
                ->assertExactJson(self::RESPUESTA_SIN_ACCESO);
        }

        $this->assertSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E07: cada ruta real de /api/administracion se llama administracion.*, lleva la sesión de Sanctum y autorizar:administracion, y solo la abre la administración: el recorrido crece solo con cada función')]
    public function test_administracion_app_e07_cada_ruta_real_de_la_administracion_solo_la_abre_la_administracion(): void
    {
        $sinElGrupo = [];
        $sinNombre = [];

        foreach ($this->rutasDeLaApi() as $nombre => $ruta) {
            if (Str::startsWith($ruta->uri(), 'api/administracion/') && ! Str::startsWith((string) $ruta->getName(), 'administracion.')) {
                $sinNombre[] = $nombre;
            }
        }

        foreach ($this->rutasDeLaAdministracion() as $nombre => $ruta) {
            $middleware = $this->middlewareDe($ruta);

            if (! Str::startsWith($ruta->uri(), 'api/administracion/')
                || ! in_array(Authenticate::class.':sanctum', $middleware, true)
                || ! in_array(Autorizar::class.':'.Rol::ADMINISTRACION, $middleware, true)) {
                $sinElGrupo[] = $nombre;

                continue;
            }

            $this->assertSoloEntraLaAdministracion($this->metodoDe($nombre), $this->urlDe($ruta));
        }

        $this->assertSame([], $sinNombre, 'Una ruta bajo api/administracion/ sin el nombre del grupo.');
        $this->assertSame([], $sinElGrupo, 'Una ruta administracion.* fuera del grupo de routes/api.php.');
    }

    /**
     * Las rutas reales que se llaman administracion.*, por "MÉTODO uri".
     *
     * @return array<string, Ruta>
     */
    private function rutasDeLaAdministracion(): array
    {
        return array_filter(
            $this->rutasRegistradas(),
            fn (Ruta $ruta): bool => Str::startsWith((string) $ruta->getName(), 'administracion.'),
        );
    }

    /**
     * Dos rutas de prueba con el mismo middleware, el mismo prefijo y el mismo nombre que el grupo de la administración
     * de routes/api.php: una que lee y responde un dato, y una que cambia la base.
     */
    private function registrarLasRutasDePrueba(): void
    {
        Route::prefix('api')->middleware('api')->group(function (): void {
            Route::middleware(['auth:sanctum', 'autorizar:administracion'])
                ->prefix('administracion')
                ->name('administracion.')
                ->group(function (): void {
                    Route::get('prueba/acceso', fn (): array => ['dato' => self::DATO_DE_LA_ADMINISTRACION])
                        ->name('prueba.acceso');
                    Route::post('prueba/cambio', function (): array {
                        Usuario::factory()->create(['correo' => self::CORREO_DEL_CAMBIO]);

                        return ['cambio' => true];
                    })->name('prueba.cambio');
                });
        });
    }
}
