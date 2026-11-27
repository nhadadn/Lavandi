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
 * al D, en la app con las rutas de /api/administracion y en el panel web, que queda como respaldo interno
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3; :21, A.4.3, los cuatro módulos; :18, Módulo C #1; :19, Módulo D #1 y #2;
 * :44, ANEXO D, D.1; :3, Cláusula I; ADR-0005; ADR-0019, decisión 2; C-247; ADR-0022, decisiones 6, 7 y 8). Las rutas de
 * un módulo rechazan a los demás roles con 403 y sin datos.
 *
 * La matriz se arma desde la tabla de rutas: cada ruta de la API dice en autorizar:<rol> qué roles admite, y esta prueba
 * llama a cada una con la sesión de cada rol, con el token real que da App\Identidad\TokensDeLaApp: el de la
 * administración, con su habilidad. Una ruta nueva entra sola.
 *
 * - Módulo B: las rutas bajo api/lavanderia/ y api/socio/. Módulo C: las de api/repartidor/, salvo el registro, que no
 *   pide sesión. Módulo D: las de api/administracion/. Módulo A: las demás con el rol de usuario.
 * - Comunes a los cuatro roles: la cuenta y el cierre de la sesión (registro-E33), y el catálogo de municipios (C-238),
 *   que la administración lee para cambiar el municipio de una lavandería (administracion-app-E14; adenda del 2026-11-26
 *   de ADR-0022, decisión 6). Las colonias de un código postal, a los tres roles de los Módulos A, B y C.
 * - Que cada ruta del panel rechace a los demás roles lo recorre autorizacion-E03.
 *
 * Escenario: autorizacion-E02 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md, con el delta de
 * openspec/changes/administracion-en-la-app/specs/autorizacion/spec.md · Tareas 1.2 de autorizacion-por-rol, y 1.6,
 * 3.1 y 4.3 de administracion-en-la-app
 */
class RolPorModuloTest extends TestCase
{
    use LlamadasSinEfecto;
    use RefreshDatabase;
    use TablaDeRutas;

    private const ROLES = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR, Rol::ADMINISTRACION];

    /** Los roles de los Módulos A, B y C, los que usan las colonias del catálogo del territorio. */
    private const ROLES_DEL_CATALOGO = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR];

    /**
     * Las rutas comunes a los cuatro roles: no son de un módulo. El catálogo de municipios también, desde la tarea 4.3 de
     * administracion-en-la-app (administracion-app-E14).
     */
    private const COMUNES_DE_LA_APP = [
        'DELETE api/sesion',
        'GET api/cuenta',
        'GET api/catalogos/municipios',
    ];

    /** Las rutas del catálogo del territorio comunes a los tres roles de los Módulos A, B y C: las colonias. */
    private const DEL_CATALOGO = [
        'GET api/codigos-postales/{codigo_postal}/colonias',
    ];

    /** El rol de cada módulo. */
    private const ROL_DEL_MODULO = ['A' => Rol::USUARIO, 'B' => Rol::LAVANDERIA, 'C' => Rol::REPARTIDOR, 'D' => Rol::ADMINISTRACION];

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

    #[TestDox('autorizacion-E02: cada ruta de un módulo admite solo al rol de ese módulo: la del Módulo A al usuario, la del B a la lavandería, la del C al repartidor y la del D a la administración; la cuenta, la sesión y el catálogo de municipios son comunes a los cuatro, y las colonias del catálogo, a los tres de los Módulos A, B y C')]
    public function test_autorizacion_e02_cada_ruta_de_un_modulo_admite_solo_a_su_rol(): void
    {
        $fueraDeSuModulo = [];
        $porModulo = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];

        foreach ($this->rutasDeLaApiConRol() as $nombre => ['roles' => $roles]) {
            if (in_array($nombre, self::COMUNES_DE_LA_APP, true)) {
                $esperados = self::ROLES;
            } elseif (in_array($nombre, self::DEL_CATALOGO, true)) {
                $esperados = self::ROLES_DEL_CATALOGO;
            } else {
                $modulo = $this->moduloDe($nombre);
                $esperados = [self::ROL_DEL_MODULO[$modulo]];
                $porModulo[$modulo]++;
            }

            if (array_values(array_diff($roles, $esperados)) !== [] || array_values(array_diff($esperados, $roles)) !== []) {
                $fueraDeSuModulo[] = "{$nombre} admite ".implode(',', $roles).'; su módulo, '.implode(',', $esperados);
            }
        }

        $this->assertSame([], $fueraDeSuModulo);

        // Los cuatro módulos tienen rutas, y las comunes existen: la prueba no pasa en vacío. El Módulo D entró con sus
        // primeras rutas, las de las lavanderías (tarea 3.1 de openspec/changes/administracion-en-la-app/tasks.md).
        $this->assertSame([], array_keys(array_filter($porModulo, fn (int $rutas): bool => $rutas === 0)));
        $this->assertSame([], array_values(array_diff(
            [...self::COMUNES_DE_LA_APP, ...self::DEL_CATALOGO],
            array_keys($this->rutasDeLaApiConRol()),
        )));
    }

    #[TestDox('autorizacion-E02: con una cuenta de cada rol y su sesión, cada ruta de la API de un módulo que no es el suyo responde 403 sin ningún dato; la administración, en todas las de los Módulos A, B y C')]
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

        // La administración es rechazada en todas las rutas de los Módulos A, B y C, con las colonias del catálogo, y solo
        // en esas: no en las comunes, que incluyen el catálogo de municipios.
        $deLosModulosABC = array_filter(
            array_keys($this->rutasDeLaApiConRol()),
            fn (string $nombre): bool => ! in_array($nombre, self::COMUNES_DE_LA_APP, true) && $this->moduloDe($nombre) !== 'D',
        );
        $this->assertSame(count($deLosModulosABC), $rechazos[Rol::ADMINISTRACION]);
    }

    #[TestDox('autorizacion-E02: cada cuenta sí llega a cada ruta de su propio módulo y a las comunes: también la administración, con su token de administración; ninguna le responde que no hay sesión ni que no tiene acceso')]
    public function test_autorizacion_e02_cada_cuenta_llega_a_las_rutas_de_su_modulo(): void
    {
        $noLlegaron = [];
        $llegadas = array_fill_keys(self::ROLES, 0);

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

    #[TestDox('autorizacion-E02: la administración llega a su módulo en el panel web de respaldo, y las cuentas de los otros roles no')]
    public function test_autorizacion_e02_la_administracion_llega_a_su_modulo_en_el_panel(): void
    {
        $this->actingAs($this->cuentas[Rol::ADMINISTRACION], 'web')->get('/admin')->assertOk();

        foreach (array_diff(self::ROLES, [Rol::ADMINISTRACION]) as $rol) {
            $this->actingAs($this->cuentas[$rol], 'web')->get('/admin')->assertForbidden();
        }
    }

    /**
     * El módulo de una ruta de la API por su prefijo: B, las de la lavandería; C, las del repartidor; D, las de la
     * administración; A, las demás.
     */
    private function moduloDe(string $nombre): string
    {
        $uri = Str::after($nombre, ' ');

        return match (true) {
            Str::startsWith($uri, ['api/lavanderia/', 'api/socio/']) => 'B',
            Str::startsWith($uri, 'api/repartidor/') => 'C',
            Str::startsWith($uri, 'api/administracion/') => 'D',
            default => 'A',
        };
    }
}
