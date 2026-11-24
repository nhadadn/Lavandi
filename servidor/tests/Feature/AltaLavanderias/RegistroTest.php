<?php

namespace Tests\Feature\AltaLavanderias;

use App\Models\Lavanderia;
use App\Models\Rol;
use App\Models\TipoServicio;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\AltaDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El registro de la lavandería desde la app: nombre, dirección, foto, servicios con su precio y su horario por día de la
 * semana; sin cualquiera de ellos no se guarda nada (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1). La foto
 * va al disco privado de fotos del entorno (ADR-0006).
 *
 * Escenarios: alta-lavanderias-E01 y alta-lavanderias-E02 · Tarea 1.1 · Spec:
 * openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class RegistroTest extends TestCase
{
    use AltaDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Las tablas en que el registro escribe; si se rechaza, ninguna tiene filas. */
    private const TABLAS = ['lavanderias', 'lavanderia_servicios', 'lavanderia_horarios', 'lavanderia_fotos'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararAlta();
    }

    #[TestDox('alta-lavanderias-E01: el registro completo guarda la lavandería con sus datos y responde con ella')]
    public function test_alta_lavanderias_e01_el_registro_completo_guarda_la_lavanderia_y_responde_con_ella(): void
    {
        $cuenta = $this->cuentaDeLavanderia();

        $respuesta = $this->registrar($cuenta, $this->registroCompleto())->assertCreated();

        $lavanderia = $this->lavanderiaDe($cuenta);
        $this->assertSame('Lavandería de prueba Alta', $lavanderia->nombre);
        $this->assertSame(
            ['Calle de prueba', '123', 'Colonia de prueba', '99000', $this->municipio->getKey()],
            [$lavanderia->calle, $lavanderia->numero, $lavanderia->colonia, $lavanderia->codigo_postal, $lavanderia->id_municipios],
        );
        $this->assertSame($this->plaza->getKey(), $lavanderia->id_plazas);
        $this->assertSame(self::PUNTO_EN_LA_ZONA, $this->ubicacionDe($lavanderia));

        // El servicio, con su precio por kilo y la plaza de la lavandería.
        $servicio = $lavanderia->servicios()->with(['tipo', 'unidad'])->sole();
        $this->assertSame([TipoServicio::ESTANDAR, UnidadMedida::KILO, '18.50', true], [
            $servicio->tipo->codigo, $servicio->unidad->codigo, $servicio->precio, $servicio->activo,
        ]);
        $this->assertSame($this->plaza->getKey(), $servicio->id_plazas);

        // El horario, por día de la semana.
        $this->assertSame(
            [[1, '10:00:00', '11:00:00'], [4, '10:00:00', '11:00:00']],
            $lavanderia->horarios()->orderBy('dia_semana')->get()
                ->map(fn ($ventana): array => [$ventana->dia_semana, $ventana->hora_inicio, $ventana->hora_fin])->all(),
        );

        // La foto quedó en el disco privado, y en la base solo su ruta.
        $foto = $lavanderia->fotos()->sole();
        Storage::disk(config('fotos.disco'))->assertExists($foto->ruta);
        $this->assertSame([$foto->ruta], $this->archivosDeFotos());
        $this->assertSame(1, $foto->orden);

        // Responde con la lavandería registrada, sin llaves internas ni la ruta del almacenamiento.
        $respuesta->assertJsonPath('lavanderia.id_publico', $lavanderia->id_publico);
        $respuesta->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba Alta');
        $respuesta->assertJsonPath('lavanderia.direccion', [
            'calle' => 'Calle de prueba',
            'numero' => '123',
            'colonia' => 'Colonia de prueba',
            'codigo_postal' => '99000',
            'municipio' => ['codigo' => $this->municipio->codigo, 'nombre' => 'Municipio de prueba', 'estado' => 'Estado de prueba'],
        ]);
        $respuesta->assertJsonPath('lavanderia.servicios', [[
            'tipo' => 'estandar',
            'nombre' => 'Lavado estándar',
            'precio' => '18.50',
            'unidad' => ['codigo' => 'kg', 'nombre' => 'kilo'],
        ]]);
        $respuesta->assertJsonPath('lavanderia.ventanas', [
            ['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
            ['dia_semana' => 4, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
        ]);
        $this->assertIsString($respuesta->json('lavanderia.foto.url'));
        $this->assertIsString($respuesta->json('lavanderia.foto.vence_en'));
        $this->assertSame(['url', 'vence_en'], array_keys($respuesta->json('lavanderia.foto')));
        $respuesta->assertJsonMissingPath('lavanderia.id_lavanderias');
        $respuesta->assertJsonMissingPath('lavanderia.id_plazas');
        $respuesta->assertJsonMissingPath('lavanderia.id_usuarios');
    }

    #[TestDox('alta-lavanderias-E01: la URL de la foto es firmada y vence; sin firma, el archivo no se sirve')]
    public function test_alta_lavanderias_e01_la_url_de_la_foto_es_firmada_y_vence(): void
    {
        // Con el disco local privado de desarrollo, no con el falso, que no firma: la firma la revisa la ruta que sirve
        // los archivos. Su raíz va a un directorio temporal que se borra al terminar.
        $raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fotos-'.Str::random(8);
        config(['fotos.disco' => 'fotos_local', 'filesystems.disks.fotos_local.root' => $raiz]);
        Storage::forgetDisk('fotos_local');
        $this->beforeApplicationDestroyed(fn () => (new Filesystem)->deleteDirectory($raiz));
        $cuenta = $this->cuentaDeLavanderia();

        $url = $this->registrar($cuenta, $this->registroCompleto())->assertCreated()->json('lavanderia.foto.url');

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->get($url)->assertOk();

        $sinFirma = strtok($url, '?');
        $this->get($sinFirma)->assertForbidden();

        $this->travel((int) config('fotos.vigencia_minutos') + 1)->minutes();
        $this->get($url)->assertForbidden();
    }

    #[TestDox('alta-lavanderias-E01: una cuenta que ya tiene su lavandería no registra otra')]
    public function test_alta_lavanderias_e01_una_cuenta_que_ya_tiene_su_lavanderia_no_registra_otra(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $this->registrar($cuenta, $this->registroCompleto())->assertCreated();

        $this->registrar($cuenta, $this->registroCompleto(['nombre' => 'Otra lavandería']))
            ->assertConflict()
            ->assertJsonPath('message', 'Esta cuenta ya tiene una lavandería registrada.');

        $this->assertSame(1, Lavanderia::count());
        $this->assertCount(1, $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E02: sin ninguno de los datos, el servidor rechaza el registro, señala cada uno y no guarda nada')]
    public function test_alta_lavanderias_e02_sin_ninguno_de_los_datos_rechaza_y_senala_cada_uno(): void
    {
        $cuenta = $this->cuentaDeLavanderia();

        $this->registrar($cuenta, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'direccion', 'foto', 'servicios', 'ventanas']);

        $this->assertNadaGuardado();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function datosQueFaltan(): array
    {
        return [
            'sin nombre' => ['nombre', 'nombre'],
            'sin dirección' => ['direccion', 'direccion'],
            'sin foto' => ['foto', 'foto'],
            'sin servicios' => ['servicios', 'servicios'],
            'sin horario' => ['ventanas', 'ventanas'],
        ];
    }

    #[DataProvider('datosQueFaltan')]
    #[TestDox('alta-lavanderias-E02: sin $quita, el servidor rechaza el registro, lo señala y no guarda nada')]
    public function test_alta_lavanderias_e02_sin_un_dato_el_servidor_rechaza_el_registro_y_lo_senala(string $quita, string $campo): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $registro = $this->registroCompleto();
        unset($registro[$quita]);

        $respuesta = $this->registrar($cuenta, $registro)->assertUnprocessable()->assertJsonValidationErrors([$campo]);

        // Solo señala lo que falta.
        $this->assertSame([$campo], array_values(array_unique(array_map(
            fn (string $clave): string => strtok($clave, '.'),
            array_keys($respuesta->json('errors')),
        ))));
        $this->assertNadaGuardado();
    }

    #[TestDox('alta-lavanderias-E02: cada parte de la dirección que falta se señala en su campo')]
    public function test_alta_lavanderias_e02_cada_parte_de_la_direccion_que_falta_se_senala_en_su_campo(): void
    {
        $cuenta = $this->cuentaDeLavanderia();

        $this->registrar($cuenta, $this->registroCompleto(['direccion' => ['colonia' => 'Colonia de prueba']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'direccion.calle',
                'direccion.numero',
                'direccion.codigo_postal',
                'direccion.municipio',
            ])
            ->assertJsonMissingValidationErrors(['direccion.colonia']);

        $this->assertNadaGuardado();
    }

    #[TestDox('alta-lavanderias-E02: un servicio sin su precio se señala en su campo')]
    public function test_alta_lavanderias_e02_un_servicio_sin_su_precio_se_senala(): void
    {
        $cuenta = $this->cuentaDeLavanderia();

        $this->registrar($cuenta, $this->registroCompleto(['servicios' => [['tipo' => 'estandar']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['servicios.0.precio']);

        $this->assertNadaGuardado();
    }

    #[TestDox('alta-lavanderias-E02: un archivo que no es una imagen, o una foto más pesada que el máximo, se rechaza en el campo foto')]
    public function test_alta_lavanderias_e02_un_archivo_que_no_es_imagen_o_una_foto_pesada_se_rechaza(): void
    {
        $cuenta = $this->cuentaDeLavanderia();

        $this->registrar($cuenta, $this->registroCompleto(['foto' => UploadedFile::fake()->create('menu.pdf', 50, 'application/pdf')]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['foto']);

        config(['fotos.peso_maximo_kb' => 100]);
        $this->registrar($cuenta, $this->registroCompleto(['foto' => $this->fotoDePrueba(kilobytes: 101)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['foto']);

        $this->assertNadaGuardado();
    }

    #[TestDox('alta-lavanderias-E01: una cuenta sin el rol de socio lavandería no registra una lavandería')]
    public function test_alta_lavanderias_e01_una_cuenta_sin_el_rol_de_socio_lavanderia_no_registra(): void
    {
        foreach ([Rol::USUARIO, Rol::ADMINISTRACION] as $rol) {
            $this->registrar(Usuario::factory()->conRol($rol)->create(), $this->registroCompleto())
                ->assertForbidden()
                ->assertJsonMissingPath('lavanderia');
        }

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->postJson('/api/socio/lavanderias', $this->registroCompleto())->assertUnauthorized();

        $this->assertNadaGuardado();
    }

    private function assertNadaGuardado(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), "{$tabla} tiene filas.");
        }

        $this->assertSame([], $this->archivosDeFotos());
        // Sin datos completos ni siquiera se consulta la geocodificación.
        $this->assertSame([], $this->geocodificador->consultas);
    }

    /**
     * @return array{float, float}
     */
    private function ubicacionDe(Lavanderia $lavanderia): array
    {
        $punto = DB::selectOne(
            'select ST_Y(ubicacion::geometry) as latitud, ST_X(ubicacion::geometry) as longitud
               from lavanderias where id_lavanderias = ?',
            [$lavanderia->getKey()],
        );

        return [(float) $punto->latitud, (float) $punto->longitud];
    }
}
