<?php

namespace Tests\Feature\Direcciones;

use App\Models\Colonia;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;
use Tests\Feature\Direcciones\Soporte\DireccionesDePrueba;
use Tests\TestCase;

/**
 * Las dos rutas del catálogo del territorio para la dirección asistida: la lista de municipios, cada uno con su estado,
 * y las colonias de un código postal (NSV-2026-001-v8.pdf:4:Cláusula I y :16:ANEXO A, Sección A.3, Módulo A #8; C-238;
 * openspec/changes/direcciones/design.md, "Contrato de la API").
 *
 *     GET /api/catalogos/municipios
 *     GET /api/codigos-postales/{codigo_postal}/colonias
 *
 * Las dos llevan sesión y pasan por el componente de autorización. El catálogo es el de prueba (CatalogoDePrueba).
 *
 * Escenarios: direcciones-E17, direcciones-E19 y direcciones-E20 · Spec:
 * openspec/changes/direcciones/specs/direcciones/spec.md · Tarea 4.3
 */
class CatalogoTest extends TestCase
{
    use DireccionesDePrueba;
    use RefreshDatabase;

    private const MUNICIPIOS = '/api/catalogos/municipios';

    #[TestDox('direcciones-E17: la lista de municipios trae solo los del catálogo, cada uno con su estado, por el nombre del estado y luego el del municipio')]
    public function test_direcciones_e17_la_lista_de_municipios_trae_los_del_catalogo_con_su_estado(): void
    {
        CatalogoDePrueba::territorio();

        $this->comoCuenta($this->cuentaDeUsuario())->getJson(self::MUNICIPIOS)
            ->assertOk()
            ->assertExactJson(['municipios' => [
                [
                    'codigo' => CatalogoDePrueba::MATAMOROS,
                    'nombre' => 'Matamoros',
                    'estado' => ['codigo' => '05', 'nombre' => 'Coahuila de Zaragoza'],
                ],
                [
                    'codigo' => CatalogoDePrueba::TORREON,
                    'nombre' => 'Torreón',
                    'estado' => ['codigo' => '05', 'nombre' => 'Coahuila de Zaragoza'],
                ],
                [
                    'codigo' => CatalogoDePrueba::DURANGO,
                    'nombre' => 'Durango',
                    'estado' => ['codigo' => '10', 'nombre' => 'Durango'],
                ],
                [
                    'codigo' => CatalogoDePrueba::GOMEZ_PALACIO,
                    'nombre' => 'Gómez Palacio',
                    'estado' => ['codigo' => '10', 'nombre' => 'Durango'],
                ],
            ]]);
    }

    #[TestDox('direcciones-E17: sin catálogo cargado, la lista de municipios sale vacía')]
    public function test_direcciones_e17_sin_catalogo_la_lista_sale_vacia(): void
    {
        $this->comoCuenta($this->cuentaDeUsuario())->getJson(self::MUNICIPIOS)
            ->assertOk()
            ->assertExactJson(['municipios' => []]);
    }

    #[TestDox('direcciones-E19: las colonias de un código postal salen por nombre, cada una con su tipo y su municipio')]
    public function test_direcciones_e19_las_colonias_de_un_codigo_postal_salen_por_nombre(): void
    {
        CatalogoDePrueba::territorio();

        $this->comoCuenta($this->cuentaDeUsuario())->getJson($this->colonias('34000'))
            ->assertOk()
            ->assertExactJson([
                'codigo_postal' => '34000',
                'colonias' => [
                    [
                        'nombre' => 'Colonia de prueba Centro',
                        'tipo' => 'Colonia',
                        'municipio' => ['codigo' => CatalogoDePrueba::DURANGO, 'nombre' => 'Durango'],
                    ],
                    [
                        'nombre' => 'Fraccionamiento de prueba',
                        'tipo' => 'Fraccionamiento',
                        'municipio' => ['codigo' => CatalogoDePrueba::DURANGO, 'nombre' => 'Durango'],
                    ],
                ],
            ]);
    }

    #[TestDox('direcciones-E19: un código postal de dos municipios trae las colonias de los dos, cada una con el suyo')]
    public function test_direcciones_e19_un_codigo_postal_de_dos_municipios_trae_las_de_los_dos(): void
    {
        CatalogoDePrueba::territorio();

        $this->comoCuenta($this->cuentaDeUsuario())->getJson($this->colonias('27440'))
            ->assertOk()
            ->assertExactJson([
                'codigo_postal' => '27440',
                'colonias' => [
                    [
                        'nombre' => 'Ejido de prueba',
                        'tipo' => 'Ejido',
                        'municipio' => ['codigo' => CatalogoDePrueba::MATAMOROS, 'nombre' => 'Matamoros'],
                    ],
                    [
                        'nombre' => 'Ejido de prueba',
                        'tipo' => 'Ejido',
                        'municipio' => ['codigo' => CatalogoDePrueba::TORREON, 'nombre' => 'Torreón'],
                    ],
                ],
            ]);
    }

    #[TestDox('direcciones-E19: un asentamiento que el archivo repite con el mismo nombre, tipo y municipio sale una sola vez')]
    public function test_direcciones_e19_un_asentamiento_repetido_sale_una_sola_vez(): void
    {
        $durango = CatalogoDePrueba::territorio()[CatalogoDePrueba::DURANGO];
        // Como "2 de Octubre" en el archivo de Durango: dos filas iguales salvo su identificador en Correos de México.
        Colonia::query()->create([
            'id_municipios' => $durango->getKey(),
            'codigo_postal' => '34000',
            'nombre' => 'Colonia de prueba Centro',
            'tipo' => 'Colonia',
        ]);
        // Mismo nombre, otro tipo: es otro asentamiento.
        Colonia::query()->create([
            'id_municipios' => $durango->getKey(),
            'codigo_postal' => '34000',
            'nombre' => 'Colonia de prueba Centro',
            'tipo' => 'Barrio',
        ]);

        $colonias = $this->comoCuenta($this->cuentaDeUsuario())->getJson($this->colonias('34000'))
            ->assertOk()
            ->json('colonias');

        $this->assertSame(
            [
                ['Colonia de prueba Centro', 'Barrio'],
                ['Colonia de prueba Centro', 'Colonia'],
                ['Fraccionamiento de prueba', 'Fraccionamiento'],
            ],
            array_map(fn (array $colonia): array => [$colonia['nombre'], $colonia['tipo']], $colonias),
        );
    }

    #[TestDox('direcciones-E20: un código postal de cinco números que no está en el catálogo responde sin colonias, no con un error')]
    public function test_direcciones_e20_un_codigo_postal_sin_catalogo_responde_sin_colonias(): void
    {
        CatalogoDePrueba::territorio();

        $this->comoCuenta($this->cuentaDeUsuario())->getJson($this->colonias('99999'))
            ->assertOk()
            ->assertExactJson(['codigo_postal' => '99999', 'colonias' => []]);
    }

    #[TestDox('direcciones-E20: un código postal que no son cinco números se rechaza con el error de su campo')]
    public function test_direcciones_e20_un_codigo_postal_que_no_son_cinco_numeros_se_rechaza(): void
    {
        CatalogoDePrueba::territorio();
        $cuenta = $this->cuentaDeUsuario();

        foreach (['3400', '340000', 'ABCDE', '3400A', '%20%2034000'] as $codigoPostal) {
            $this->comoCuenta($cuenta)->getJson($this->colonias($codigoPostal))
                ->assertUnprocessable()
                ->assertExactJson([
                    'message' => 'El código postal son cinco números.',
                    'errors' => ['codigo_postal' => ['El código postal son cinco números.']],
                ]);
        }
    }

    #[TestDox('direcciones-E17 y E19: sin sesión, o con una cuenta sin el rol de usuario, las dos rutas no dan ningún dato del catálogo')]
    public function test_direcciones_e17_y_e19_sin_sesion_o_sin_rol_no_dan_ningun_dato(): void
    {
        CatalogoDePrueba::territorio();
        $sinRol = Usuario::factory()->create();

        $rutas = [self::MUNICIPIOS, $this->colonias('34000')];

        foreach ($rutas as $ruta) {
            $this->getJson($ruta)
                ->assertUnauthorized()
                ->assertJsonMissingPath('municipios')
                ->assertJsonMissingPath('colonias');
        }

        foreach ($rutas as $ruta) {
            $this->comoCuenta($sinRol)->getJson($ruta)
                ->assertForbidden()
                ->assertJsonMissingPath('municipios')
                ->assertJsonMissingPath('colonias');
        }
    }

    private function colonias(string $codigoPostal): string
    {
        return "/api/codigos-postales/{$codigoPostal}/colonias";
    }

    #[TestDox('alta-lavanderias-E01: la cuenta de una lavandería consulta el catálogo para registrar su dirección; la administración no')]
    public function test_alta_lavanderias_e01_la_lavanderia_consulta_el_catalogo(): void
    {
        CatalogoDePrueba::territorio();
        $lavanderia = Usuario::factory()->conRol(Rol::LAVANDERIA)->create();
        $token = $lavanderia->createToken('app')->plainTextToken;

        $this->withToken($token)->getJson('/api/catalogos/municipios')
            ->assertOk()
            ->assertJsonPath('municipios.0.codigo', CatalogoDePrueba::MATAMOROS);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/codigos-postales/34000/colonias')->assertOk();

        $this->app['auth']->forgetGuards();
        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create();
        $this->withToken($administracion->createToken('app')->plainTextToken)
            ->getJson('/api/catalogos/municipios')
            ->assertForbidden();
    }
}
