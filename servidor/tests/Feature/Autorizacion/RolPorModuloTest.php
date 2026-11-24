<?php

namespace Tests\Feature\Autorizacion;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Autorizacion\Soporte\LlamadasSinEfecto;
use Tests\Feature\Autorizacion\Soporte\TablaDeRutas;
use Tests\TestCase;

/**
 * Cada rol entra solo a su módulo: usuario al Módulo A, socio lavandería al B, socio repartidor al C y la administración
 * al D, en el panel web (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3; :21, A.4.3, los cuatro módulos; :18, Módulo C #1;
 * :19, Módulo D #1 y #2; :44, ANEXO D, D.1; ADR-0005; ADR-0019, decisión 2). Las rutas de un módulo rechazan a los demás
 * roles con 403 y sin datos.
 *
 * La matriz se arma desde la tabla de rutas: cada ruta de la API dice en autorizar:<rol> qué roles admite, y esta prueba
 * llama a cada una con la sesión de cada rol. Una ruta nueva entra sola.
 *
 * - Módulo B: las rutas bajo api/lavanderia/ y api/socio/. Módulo C: las de api/repartidor/, salvo el registro, que no
 *   pide sesión. Módulo A: las demás con el rol de usuario.
 * - Comunes a los tres roles de la app: la cuenta, el cierre de la sesión (registro-E33) y el catálogo del territorio
 *   (C-238). La administración no tiene token de la app: entra por el panel (registro-E33).
 * - La vista reducida de administración en la app espera a administracion (DD-040, C-140); hoy el Módulo D es el panel.
 *   Que cada ruta del panel rechace a los demás roles lo recorre autorizacion-E03.
 *
 * Escenario: autorizacion-E02 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Tarea 1.2
 */
class RolPorModuloTest extends TestCase
{
    use LlamadasSinEfecto;
    use RefreshDatabase;
    use TablaDeRutas;

    private const ROLES = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR, Rol::ADMINISTRACION];

    /** Los roles con módulo en la app (Autorizacion::usaLaApp()). */
    private const ROLES_DE_LA_APP = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR];

    /** Las rutas comunes a los tres roles de la app: no son de un módulo. */
    private const COMUNES_DE_LA_APP = [
        'DELETE api/sesion',
        'GET api/catalogos/municipios',
        'GET api/codigos-postales/{codigo_postal}/colonias',
        'GET api/cuenta',
    ];

    /** @var array<string, Usuario> Una cuenta de cada rol, que opera en su módulo. */
    private array $cuentas;

    protected function setUp(): void
    {
        parent::setUp();

        // Las respuestas se revisan como salen en producción, sin el detalle de depuración del entorno local.
        config(['app.debug' => false]);

        foreach (self::ROLES as $rol) {
            $this->cuentas[$rol] = $this->cuentaQueOpera($rol);
        }
    }

    #[TestDox('autorizacion-E02: cada ruta de un módulo admite solo al rol de ese módulo: la del Módulo A al usuario, la del B a la lavandería y la del C al repartidor; solo la cuenta, la sesión y el catálogo son comunes a los tres')]
    public function test_autorizacion_e02_cada_ruta_de_un_modulo_admite_solo_a_su_rol(): void
    {
        $fueraDeSuModulo = [];
        $porModulo = ['A' => 0, 'B' => 0, 'C' => 0];

        foreach ($this->rutasDeLaApiConRol() as $nombre => ['roles' => $roles]) {
            if (in_array($nombre, self::COMUNES_DE_LA_APP, true)) {
                $esperados = self::ROLES_DE_LA_APP;
            } else {
                $modulo = $this->moduloDe($nombre);
                $esperados = [['A' => Rol::USUARIO, 'B' => Rol::LAVANDERIA, 'C' => Rol::REPARTIDOR][$modulo]];
                $porModulo[$modulo]++;
            }

            if (array_values(array_diff($roles, $esperados)) !== [] || array_values(array_diff($esperados, $roles)) !== []) {
                $fueraDeSuModulo[] = "{$nombre} admite ".implode(',', $roles).'; su módulo, '.implode(',', $esperados);
            }
        }

        $this->assertSame([], $fueraDeSuModulo);

        // Los tres módulos de la app tienen rutas, y las comunes existen: la prueba no pasa en vacío.
        $this->assertSame([], array_filter($porModulo, fn (int $rutas): bool => $rutas === 0));
        $this->assertSame([], array_values(array_diff(self::COMUNES_DE_LA_APP, array_keys($this->rutasDeLaApiConRol()))));
    }

    #[TestDox('autorizacion-E02: con una cuenta de cada rol y su sesión, cada ruta de la API de un módulo que no es el suyo responde 403 sin ningún dato; la administración, en todas')]
    public function test_autorizacion_e02_ningun_rol_entra_al_modulo_de_otro(): void
    {
        $entraron = [];
        $rechazos = array_fill_keys(self::ROLES, 0);

        foreach ($this->rutasDeLaApiConRol() as $nombre => ['ruta' => $ruta, 'roles' => $roles]) {
            foreach (array_diff(self::ROLES, $roles) as $rol) {
                $respuesta = $this->llamarSinEfecto($this->cuentas[$rol], $this->metodoDe($nombre), $this->urlDe($ruta));

                if ($respuesta->status() !== 403 || $respuesta->json() !== self::SIN_ACCESO) {
                    $entraron[] = "{$nombre} · {$rol} · {$respuesta->status()} · {$respuesta->getContent()}";
                }

                $rechazos[$rol]++;
            }
        }

        $this->assertSame([], $entraron);

        // Cada rol tiene módulos ajenos que lo rechazan: la prueba no pasa en vacío.
        $this->assertSame([], array_keys(array_filter($rechazos, fn (int $veces): bool => $veces === 0)));
        $this->assertSame(count($this->rutasDeLaApiConRol()), $rechazos[Rol::ADMINISTRACION]);
    }

    #[TestDox('autorizacion-E02: cada cuenta sí llega a cada ruta de su propio módulo y a las comunes de la app: ninguna le responde que no hay sesión ni que no tiene acceso')]
    public function test_autorizacion_e02_cada_cuenta_llega_a_las_rutas_de_su_modulo(): void
    {
        $noLlegaron = [];
        $llegadas = array_fill_keys(self::ROLES_DE_LA_APP, 0);

        foreach ($this->rutasDeLaApiConRol() as $nombre => ['ruta' => $ruta, 'roles' => $roles]) {
            foreach ($roles as $rol) {
                $respuesta = $this->llamarSinEfecto($this->cuentas[$rol], $this->metodoDe($nombre), $this->urlDe($ruta));

                if (in_array($respuesta->status(), [401, 403], true) || $respuesta->status() >= 500) {
                    $noLlegaron[] = "{$nombre} · {$rol} · {$respuesta->status()} · {$respuesta->getContent()}";
                }

                $llegadas[$rol]++;
            }
        }

        $this->assertSame([], $noLlegaron);
        $this->assertSame([], array_keys(array_filter($llegadas, fn (int $veces): bool => $veces === 0)));
    }

    #[TestDox('autorizacion-E02: la administración llega a su módulo, el panel web, y las cuentas de los otros roles no')]
    public function test_autorizacion_e02_la_administracion_llega_a_su_modulo_en_el_panel(): void
    {
        $this->actingAs($this->cuentas[Rol::ADMINISTRACION], 'web')->get('/admin')->assertOk();

        foreach (self::ROLES_DE_LA_APP as $rol) {
            $this->actingAs($this->cuentas[$rol], 'web')->get('/admin')->assertForbidden();
        }
    }

    /**
     * El módulo de una ruta de la API por su prefijo: B, las de la lavandería; C, las del repartidor; A, las demás.
     */
    private function moduloDe(string $nombre): string
    {
        $uri = Str::after($nombre, ' ');

        return match (true) {
            Str::startsWith($uri, ['api/lavanderia/', 'api/socio/']) => 'B',
            Str::startsWith($uri, 'api/repartidor/') => 'C',
            default => 'A',
        };
    }
}
