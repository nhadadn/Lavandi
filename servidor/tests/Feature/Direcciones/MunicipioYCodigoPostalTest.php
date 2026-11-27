<?php

namespace Tests\Feature\Direcciones;

use App\Direcciones\Punto;
use App\Models\Direccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;
use Tests\Feature\Direcciones\Soporte\DireccionesDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La dirección asistida en POST /api/direcciones: el municipio es el código de uno del catálogo del territorio y se
 * guarda como llave, en la escrita y en la del mapa; el código postal, si viene, son cinco números; la colonia y el
 * código postal no se comparan con el catálogo. Todo se valida antes de geocodificar, así que un error no gasta ninguna
 * consulta. La dirección sale con el nombre del municipio y el de su estado (NSV-2026-001-v8.pdf:4:Cláusula I y :16:
 * ANEXO A, Sección A.3, Módulo A #8; C-238; openspec/changes/direcciones/design.md, decisiones 7 a 9).
 *
 * El catálogo es el de prueba (CatalogoDePrueba), escrito directo en la base. La geocodificación se simula.
 *
 * Escenarios: direcciones-E02, direcciones-E17, direcciones-E18 y direcciones-E20 · Spec:
 * openspec/changes/direcciones/specs/direcciones/spec.md · Tarea 4.4
 */
class MunicipioYCodigoPostalTest extends TestCase
{
    use DireccionesDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const DIRECCIONES = '/api/direcciones';

    private const ELIGE_DE_LA_LISTA = 'Elige un municipio de la lista.';

    private const CINCO_NUMEROS = 'El código postal son cinco números.';

    protected function setUp(): void
    {
        parent::setUp();

        CatalogoDePrueba::territorio();
        // Ninguna prueba sale a la red: la geocodificación real no se llama nunca aquí.
        Http::preventStrayRequests();
    }

    #[TestDox('direcciones-E17: la dirección escrita con un municipio de la lista se guarda con su llave y sale con el nombre del municipio y el de su estado')]
    public function test_direcciones_e17_la_escrita_con_un_municipio_de_la_lista_sale_con_su_municipio_y_su_estado(): void
    {
        $geocodificacion = $this->simularGeocodificacion(new Punto(10.25, -119.75));
        $torreon = CatalogoDePrueba::municipio(CatalogoDePrueba::TORREON);

        $respuesta = $this->comoCuenta($this->cuentaDeUsuario())->postJson(self::DIRECCIONES, $this->direccionEscrita([
            'codigo_postal' => '27000',
            'colonia' => 'Colonia de prueba Torreón',
            'municipio' => CatalogoDePrueba::TORREON,
        ]))->assertCreated();

        $direccion = Direccion::sole();
        $this->assertSame($torreon->getKey(), $direccion->id_municipios);
        $respuesta->assertJsonPath('direccion.municipio', 'Torreón');
        $respuesta->assertJsonPath('direccion.estado', 'Coahuila de Zaragoza');
        // El texto que se geocodifica lleva el municipio y el estado del catálogo (decisión 10).
        $this->assertSame(
            ['Calle de prueba 123, Colonia de prueba Torreón, 27000 Torreón, Coahuila de Zaragoza'],
            $geocodificacion->consultas,
        );
    }

    #[TestDox('direcciones-E17: la del mapa guarda el municipio de la lista si viene, sin geocodificar; sin él, sale sin municipio ni estado')]
    public function test_direcciones_e17_la_del_mapa_guarda_el_municipio_si_viene(): void
    {
        $geocodificacion = $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();
        $delMapa = ['origen' => 'mapa', 'latitud' => 10.5, 'longitud' => -119.5, 'colonia' => 'Colonia de prueba'];

        $conMunicipio = $this->comoCuenta($cuenta)
            ->postJson(self::DIRECCIONES, [...$delMapa, 'municipio' => CatalogoDePrueba::MATAMOROS])
            ->assertCreated();
        $sinMunicipio = $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, $delMapa)->assertCreated();

        $conMunicipio->assertJsonPath('direccion.municipio', 'Matamoros');
        $conMunicipio->assertJsonPath('direccion.estado', 'Coahuila de Zaragoza');
        $sinMunicipio->assertJsonPath('direccion.municipio', null);
        $sinMunicipio->assertJsonPath('direccion.estado', null);

        $this->assertSame(
            [CatalogoDePrueba::municipio(CatalogoDePrueba::MATAMOROS)->getKey(), null],
            Direccion::orderBy('id_direcciones')->pluck('id_municipios')->all(),
        );
        $this->assertSame([], $geocodificacion->consultas);
    }

    #[TestDox('direcciones-E17: la lista de favoritas y el detalle de la dirección también salen con el municipio y su estado')]
    public function test_direcciones_e17_las_favoritas_y_el_detalle_salen_con_el_municipio_y_su_estado(): void
    {
        $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();

        $guardada = $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, $this->direccionEscrita([
            'municipio' => CatalogoDePrueba::GOMEZ_PALACIO,
            'es_favorita' => true,
            'alias' => 'Casa',
        ]))->assertCreated()->json('direccion');

        $this->assertSame(['Gómez Palacio', 'Durango'], [$guardada['municipio'], $guardada['estado']]);

        $this->comoCuenta($cuenta)->getJson(self::DIRECCIONES.'/favoritas')
            ->assertOk()
            ->assertExactJson(['direcciones' => [$guardada]]);
        $this->comoCuenta($cuenta)->getJson(self::DIRECCIONES.'/'.$guardada['id_publico'])
            ->assertOk()
            ->assertExactJson(['direccion' => $guardada]);
    }

    #[TestDox('direcciones-E18: un municipio que no está en el catálogo, como Saltillo, o un código que no existe, se rechaza en la escrita sin geocodificar ni guardar')]
    public function test_direcciones_e18_un_municipio_fuera_del_catalogo_se_rechaza_en_la_escrita(): void
    {
        $geocodificacion = $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();

        // Saltillo, un código que no existe, el nombre en lugar del código, y un número en lugar de texto.
        foreach ([CatalogoDePrueba::SALTILLO, '12345', 'Durango', 10005, ['10005']] as $municipio) {
            $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, $this->direccionEscrita(['municipio' => $municipio]))
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors(['municipio'])
                ->assertJsonPath('errors.municipio', [self::ELIGE_DE_LA_LISTA]);
        }

        // Sin municipio, el error de hoy: falta.
        $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, $this->direccionEscrita(['municipio' => '']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.municipio', ['Falta el municipio.']);

        $this->assertSame([], $geocodificacion->consultas);
        $this->assertSame(0, Direccion::count());
    }

    #[TestDox('direcciones-E18: en la del mapa, un municipio que no está en el catálogo también se rechaza y no se guarda nada')]
    public function test_direcciones_e18_un_municipio_fuera_del_catalogo_se_rechaza_en_la_del_mapa(): void
    {
        $geocodificacion = $this->simularGeocodificacion();

        $this->comoCuenta($this->cuentaDeUsuario())->postJson(self::DIRECCIONES, [
            'origen' => 'mapa',
            'latitud' => 10.5,
            'longitud' => -119.5,
            'colonia' => 'Colonia de prueba',
            'municipio' => CatalogoDePrueba::SALTILLO,
        ])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['municipio'])
            ->assertJsonPath('message', self::ELIGE_DE_LA_LISTA);

        $this->assertSame([], $geocodificacion->consultas);
        $this->assertSame(0, Direccion::count());
    }

    #[TestDox('direcciones-E20: un código postal que no está en el catálogo, con la colonia escrita a mano, se guarda tal como vino')]
    public function test_direcciones_e20_un_codigo_postal_sin_catalogo_se_guarda_tal_como_vino(): void
    {
        $this->simularGeocodificacion();

        $respuesta = $this->comoCuenta($this->cuentaDeUsuario())->postJson(self::DIRECCIONES, $this->direccionEscrita([
            'codigo_postal' => '99999',
            'colonia' => 'Colonia escrita a mano',
            'municipio' => CatalogoDePrueba::DURANGO,
        ]))->assertCreated();

        $respuesta->assertJsonPath('direccion.codigo_postal', '99999');
        $respuesta->assertJsonPath('direccion.colonia', 'Colonia escrita a mano');
        $this->assertSame(['99999', 'Colonia escrita a mano'], [Direccion::sole()->codigo_postal, Direccion::sole()->colonia]);
    }

    #[TestDox('direcciones-E20: una colonia que no aparece en la lista de su código postal, o que es de otro, se guarda sin compararla con el catálogo')]
    public function test_direcciones_e20_una_colonia_que_no_aparece_en_la_lista_se_guarda_sin_compararla(): void
    {
        $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();

        // 34000 es de Durango y no trae esa colonia; la segunda es de un código postal de Torreón.
        foreach (['Colonia que no está en la lista', 'Colonia de prueba Torreón'] as $colonia) {
            $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, $this->direccionEscrita([
                'codigo_postal' => '34000',
                'colonia' => $colonia,
                'municipio' => CatalogoDePrueba::DURANGO,
            ]))->assertCreated()->assertJsonPath('direccion.colonia', $colonia);
        }

        $this->assertSame(
            ['Colonia que no está en la lista', 'Colonia de prueba Torreón'],
            Direccion::orderBy('id_direcciones')->pluck('colonia')->all(),
        );
    }

    #[TestDox('direcciones-E20: un código postal que no son cinco números se rechaza con el error de su campo, sin geocodificar, en la escrita y en la del mapa')]
    public function test_direcciones_e20_un_codigo_postal_que_no_son_cinco_numeros_se_rechaza(): void
    {
        $geocodificacion = $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();
        $delMapa = ['origen' => 'mapa', 'latitud' => 10.5, 'longitud' => -119.5, 'colonia' => 'Colonia de prueba'];

        // También un número en lugar de texto: el código postal viaja como texto, para no perder un cero a la izquierda.
        foreach (['3400', '340000', 'ABCDE', '34-00', ['34000'], 34000] as $codigoPostal) {
            foreach ([$this->direccionEscrita(), $delMapa] as $datos) {
                $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, [...$datos, 'codigo_postal' => $codigoPostal])
                    ->assertUnprocessable()
                    ->assertOnlyJsonValidationErrors(['codigo_postal'])
                    ->assertJsonPath('errors.codigo_postal', [self::CINCO_NUMEROS]);
            }
        }

        $this->assertSame([], $geocodificacion->consultas);
        $this->assertSame(0, Direccion::count());
    }

    #[TestDox('direcciones-E20: el código postal se pide pero no se exige: sin él, la dirección se guarda')]
    public function test_direcciones_e20_sin_codigo_postal_la_direccion_se_guarda(): void
    {
        $this->simularGeocodificacion();

        $this->comoCuenta($this->cuentaDeUsuario())
            ->postJson(self::DIRECCIONES, $this->direccionEscrita(['codigo_postal' => '']))
            ->assertCreated()
            ->assertJsonPath('direccion.codigo_postal', null);
    }

    #[TestDox('direcciones-E02: con un código postal del catálogo y sin colonia, la dirección se rechaza: la lista ayuda, no la llena sola')]
    public function test_direcciones_e02_con_un_codigo_postal_del_catalogo_y_sin_colonia_se_rechaza(): void
    {
        $geocodificacion = $this->simularGeocodificacion();
        $sinColonia = $this->direccionEscrita(['codigo_postal' => '34000', 'municipio' => CatalogoDePrueba::DURANGO]);
        unset($sinColonia['colonia']);

        $this->comoCuenta($this->cuentaDeUsuario())->postJson(self::DIRECCIONES, $sinColonia)
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['colonia'])
            ->assertJsonPath('errors.colonia', ['Falta la colonia.']);

        $this->assertSame([], $geocodificacion->consultas);
        $this->assertSame(0, Direccion::count());
    }
}
