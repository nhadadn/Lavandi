<?php

namespace Tests\Feature\Direcciones;

use App\Models\Colonia;
use App\Models\Direccion;
use App\Models\EntidadFederativa;
use App\Models\Municipio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * La carga del catálogo del territorio: el comando direcciones:cargar-catalogo lee los archivos de Correos de México
 * (SEPOMEX) y deja en la base los estados, los municipios y las colonias del territorio de config/territorio.php
 * (NSV-2026-001-v8.pdf:4:Cláusula I; C-238; openspec/changes/direcciones/design.md, "La carga del catálogo").
 *
 * Los archivos de prueba están en tests/Fixtures/sepomex/, con el formato del archivo real: texto en ISO-8859-1 separado
 * por |, una nota en la primera línea y los nombres de las columnas en la segunda. Los municipios son reales y los
 * asentamientos, inventados. El archivo real no se versiona (DD-138).
 *
 * Escenario: direcciones-E16 · Spec: openspec/changes/direcciones/specs/direcciones/spec.md · Tarea 4.2
 */
class CargaDelCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private const COMANDO = 'direcciones:cargar-catalogo';

    private const DURANGO = __DIR__.'/../../Fixtures/sepomex/Durango.txt';

    private const COAHUILA = __DIR__.'/../../Fixtures/sepomex/Coahuila_de_Zaragoza.txt';

    private const SIN_COLUMNA = __DIR__.'/../../Fixtures/sepomex/sin-columna.txt';

    #[TestDox('direcciones-E16: con los archivos de Durango y de Coahuila quedan todos los municipios de Durango y, de Coahuila, solo Torreón y Matamoros, cada uno con su estado')]
    public function test_direcciones_e16_la_carga_toma_solo_los_municipios_del_territorio(): void
    {
        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO, self::COAHUILA]])
            ->expectsOutputToContain('Durango (10): 3 municipios y 5 colonias.')
            ->expectsOutputToContain('Coahuila de Zaragoza (05): 2 municipios y 4 colonias.')
            ->assertSuccessful();

        $this->assertSame(
            ['05' => 'Coahuila de Zaragoza', '10' => 'Durango'],
            EntidadFederativa::orderBy('codigo')->pluck('nombre', 'codigo')->all(),
        );
        $this->assertSame(
            [
                '05017' => ['Matamoros', '05'],
                '05035' => ['Torreón', '05'],
                '10005' => ['Durango', '10'],
                '10007' => ['Gómez Palacio', '10'],
                '10012' => ['Lerdo', '10'],
            ],
            Municipio::with('entidadFederativa')->orderBy('codigo')->get()
                ->mapWithKeys(fn (Municipio $municipio): array => [
                    $municipio->codigo => [$municipio->nombre, $municipio->entidadFederativa->codigo],
                ])->all(),
        );
    }

    #[TestDox('direcciones-E16: quedan las colonias de esos municipios con su código postal y su municipio, y las de Saltillo no entran')]
    public function test_direcciones_e16_quedan_las_colonias_del_territorio_y_las_de_saltillo_no(): void
    {
        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO, self::COAHUILA]])->assertSuccessful();

        $this->assertSame(
            [
                ['05017', '27440', 'Ejido de prueba', 'Ejido'],
                ['05035', '27000', 'Colonia de prueba Torreón', 'Colonia'],
                ['05035', '27010', 'Colonia de prueba Oriente', 'Colonia'],
                ['05035', '27440', 'Ejido de prueba', 'Ejido'],
                ['10005', '34000', 'Colonia de prueba Centro', 'Colonia'],
                ['10005', '34000', 'Fraccionamiento de prueba', 'Fraccionamiento'],
                ['10005', '34100', 'Colonia de prueba Norte', 'Colonia'],
                ['10007', '35000', 'Colonia de prueba Gómez', 'Colonia'],
                ['10012', '35150', 'Ejido de prueba', 'Ejido'],
            ],
            $this->colonias(),
        );

        // Saltillo y Viesca, de Coahuila, quedan fuera del territorio: ni sus municipios ni sus colonias.
        $this->assertFalse(Municipio::whereIn('codigo', ['05030', '05036'])->exists());
        $this->assertFalse(Colonia::whereIn('codigo_postal', ['25000', '27450'])->exists());
    }

    #[TestDox('direcciones-E16: el comando dice la primera línea de cada archivo y cuántas filas quedaron fuera del territorio')]
    public function test_direcciones_e16_el_comando_dice_la_primera_linea_y_lo_que_quedo_fuera(): void
    {
        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO, self::COAHUILA]])
            ->expectsOutputToContain('Archivo de prueba con el formato del Catálogo Nacional de Códigos Postales')
            ->expectsOutputToContain('Fuera del territorio: 2 filas, que no se cargaron.')
            ->assertSuccessful();
    }

    #[TestDox('direcciones-E16: correrlo otra vez con los mismos archivos deja el catálogo igual, sin filas repetidas')]
    public function test_direcciones_e16_correrlo_otra_vez_deja_el_catalogo_igual(): void
    {
        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO, self::COAHUILA]])->assertSuccessful();
        $estados = EntidadFederativa::orderBy('codigo')->pluck('id_entidades_federativas', 'codigo')->all();
        $municipios = Municipio::orderBy('codigo')->pluck('id_municipios', 'codigo')->all();
        $colonias = $this->colonias();

        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO, self::COAHUILA]])->assertSuccessful();

        $this->assertSame($estados, EntidadFederativa::orderBy('codigo')->pluck('id_entidades_federativas', 'codigo')->all());
        $this->assertSame($municipios, Municipio::orderBy('codigo')->pluck('id_municipios', 'codigo')->all());
        $this->assertSame($colonias, $this->colonias());
    }

    #[TestDox('direcciones-E16: cargar de nuevo reemplaza las colonias de los municipios que trae el archivo y nunca borra un municipio, aunque una dirección lo apunte')]
    public function test_direcciones_e16_cargar_de_nuevo_reemplaza_las_colonias_y_no_borra_municipios(): void
    {
        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO]])->assertSuccessful();
        $durango = Municipio::where('codigo', '10005')->sole();
        Direccion::create([
            'id_usuarios' => Usuario::factory()->create()->getKey(),
            'id_municipios' => $durango->getKey(),
            'colonia' => 'Colonia de prueba Centro',
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5],
        ]);

        // El archivo nuevo ya no trae Lerdo ni el fraccionamiento de Durango, y renombra una colonia.
        $nuevo = $this->archivoCon(self::DURANGO, function (array $lineas): array {
            $lineas = array_values(array_filter($lineas, fn (string $linea): bool => ! str_contains($linea, '|Lerdo|')
                && ! str_contains($linea, 'Fraccionamiento de prueba')));

            return str_replace('Colonia de prueba Norte', 'Colonia de prueba Norte Nueva', $lineas);
        });

        $this->artisan(self::COMANDO, ['archivos' => [$nuevo]])
            ->expectsOutputToContain('Durango (10): 2 municipios y 3 colonias.')
            ->assertSuccessful();

        $this->assertSame(
            [
                ['10005', '34000', 'Colonia de prueba Centro', 'Colonia'],
                ['10005', '34100', 'Colonia de prueba Norte Nueva', 'Colonia'],
                ['10007', '35000', 'Colonia de prueba Gómez', 'Colonia'],
                // Lerdo no vino: su municipio y sus colonias se quedan como estaban.
                ['10012', '35150', 'Ejido de prueba', 'Ejido'],
            ],
            $this->colonias(),
        );
        $this->assertTrue($durango->is(Direccion::sole()->municipio));
    }

    #[TestDox('direcciones-E16: un archivo al que le falta una columna del formato no carga nada, y el comando dice cuál falta')]
    public function test_direcciones_e16_un_archivo_sin_una_columna_no_carga_nada_y_dice_cual_falta(): void
    {
        // El bueno va primero: tampoco queda nada de él.
        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO, self::SIN_COLUMNA]])
            ->expectsOutputToContain('sin-columna.txt no trae la columna d_asenta')
            ->assertFailed();

        $this->assertCatalogoVacio();
    }

    #[TestDox('direcciones-E16: un municipio del archivo cuyo nombre no es el de la configuración no carga nada: una clave equivocada no carga otro municipio')]
    public function test_direcciones_e16_un_nombre_que_no_coincide_con_la_configuracion_no_carga_nada(): void
    {
        // La clave de Torreón apuntando, por error, a Viesca.
        config(['territorio.estados.05.municipios' => ['036' => 'Torreón', '017' => 'Matamoros']]);

        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO, self::COAHUILA]])
            ->expectsOutputToContain('El municipio 05036 se llama Viesca en el archivo y Torreón en config/territorio.php.')
            ->assertFailed();

        $this->assertCatalogoVacio();
    }

    #[TestDox('direcciones-E16: un estado cuyo nombre no es el de la configuración no carga nada')]
    public function test_direcciones_e16_un_estado_que_no_coincide_con_la_configuracion_no_carga_nada(): void
    {
        config(['territorio.estados.05.nombre' => 'Chihuahua']);

        $this->artisan(self::COMANDO, ['archivos' => [self::COAHUILA]])
            ->expectsOutputToContain('El estado 05 se llama Coahuila de Zaragoza en el archivo y Chihuahua en config/territorio.php.')
            ->assertFailed();

        $this->assertCatalogoVacio();
    }

    #[TestDox('direcciones-E16: si el archivo de un estado no trae un municipio de la configuración, no carga nada')]
    public function test_direcciones_e16_si_falta_un_municipio_de_la_configuracion_no_carga_nada(): void
    {
        config(['territorio.estados.05.municipios' => ['035' => 'Torreón', '017' => 'Matamoros', '099' => 'Inventado']]);

        $this->artisan(self::COMANDO, ['archivos' => [self::COAHUILA]])
            ->expectsOutputToContain('Los archivos de Coahuila de Zaragoza no traen el municipio 05099 (Inventado) de config/territorio.php.')
            ->assertFailed();

        $this->assertCatalogoVacio();
    }

    #[TestDox('direcciones-E16: una fila del territorio con un dato que no tiene la forma del archivo no carga nada, y el comando dice la línea')]
    public function test_direcciones_e16_una_fila_sin_la_forma_del_archivo_no_carga_nada(): void
    {
        $malo = $this->archivoCon(self::DURANGO, fn (array $lineas): array => str_replace('35150|', '3515|', $lineas));

        $this->artisan(self::COMANDO, ['archivos' => [$malo]])
            ->expectsOutputToContain('línea 7')
            ->assertFailed();

        $this->assertCatalogoVacio();
    }

    #[TestDox('direcciones-E16: un archivo que no existe no carga nada')]
    public function test_direcciones_e16_un_archivo_que_no_existe_no_carga_nada(): void
    {
        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO, __DIR__.'/no-existe.txt']])
            ->expectsOutputToContain('No se pudo leer el archivo')
            ->assertFailed();

        $this->assertCatalogoVacio();
    }

    #[TestDox('direcciones-E16: un archivo que ya está en UTF-8 se lee sin convertirlo dos veces')]
    public function test_direcciones_e16_un_archivo_en_utf8_se_lee_sin_convertirlo_dos_veces(): void
    {
        $enUtf8 = $this->archivoCon(self::COAHUILA, fn (array $lineas): array => $lineas, utf8: true);

        $this->artisan(self::COMANDO, ['archivos' => [$enUtf8]])->assertSuccessful();

        $this->assertSame('Torreón', Municipio::where('codigo', '05035')->value('nombre'));
    }

    #[TestDox('direcciones-E16: el comando dice qué estado del territorio no vino en los archivos')]
    public function test_direcciones_e16_el_comando_dice_que_estado_no_vino(): void
    {
        $this->artisan(self::COMANDO, ['archivos' => [self::DURANGO]])
            ->expectsOutputToContain('Ningún archivo trajo Coahuila de Zaragoza (05).')
            ->assertSuccessful();

        $this->assertSame(['10'], EntidadFederativa::pluck('codigo')->all());
    }

    private function assertCatalogoVacio(): void
    {
        $this->assertSame(
            [0, 0, 0],
            [EntidadFederativa::count(), Municipio::count(), Colonia::count()],
        );
    }

    /**
     * Las colonias del catálogo: el código de su municipio, su código postal, su nombre y su tipo, en orden.
     *
     * @return list<list<string>>
     */
    private function colonias(): array
    {
        return Colonia::query()
            ->join('municipios', 'municipios.id_municipios', '=', 'colonias.id_municipios')
            ->orderBy('municipios.codigo')
            ->orderBy('colonias.codigo_postal')
            ->orderBy('colonias.nombre')
            ->get(['municipios.codigo', 'colonias.codigo_postal', 'colonias.nombre', 'colonias.tipo'])
            ->map(fn (Colonia $colonia): array => [$colonia->codigo, $colonia->codigo_postal, $colonia->nombre, $colonia->tipo])
            ->all();
    }

    /**
     * Una copia del archivo de prueba, con sus líneas cambiadas, en ISO-8859-1 como el original o en UTF-8. Se borra al
     * terminar la prueba.
     *
     * @param  callable(list<string>): list<string>  $cambio
     */
    private function archivoCon(string $original, callable $cambio, bool $utf8 = false): string
    {
        $contenido = rtrim(mb_convert_encoding((string) file_get_contents($original), 'UTF-8', 'ISO-8859-1'));
        $lineas = preg_split('/\r\n|\n|\r/', $contenido) ?: [];
        $texto = implode("\r\n", $cambio($lineas))."\r\n";

        $archivo = (string) tempnam(sys_get_temp_dir(), 'sepomex');
        file_put_contents($archivo, $utf8 ? $texto : mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8'));
        $this->beforeApplicationDestroyed(fn () => @unlink($archivo));

        return $archivo;
    }
}
