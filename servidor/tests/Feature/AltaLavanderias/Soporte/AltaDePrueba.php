<?php

namespace Tests\Feature\AltaLavanderias\Soporte;

use App\Direcciones\Geocodificador;
use App\Direcciones\Punto;
use App\Models\Lavanderia;
use App\Models\Municipio;
use App\Models\Plaza;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Zona;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;
use Tests\Feature\Direcciones\Soporte\GeocodificadorSimulado;

/**
 * Lo que comparten las pruebas del alta de lavanderías: una plaza de prueba con su zona, el municipio ficticio del
 * catálogo, la geocodificación simulada, el almacenamiento de fotos falso y las peticiones de la app con la sesión de
 * una cuenta de socio lavandería. Se usa junto con PlazasDePrueba.
 *
 * Todo es ficticio: la zona es el cuadrado de un grado con su esquina suroeste en (-120, 10), en el océano Pacífico
 * (PlazasDePrueba), y la geocodificación responde un punto dentro de ella, (10.5, -119.5). Los precios son de prueba;
 * los de producción los fija quien decida DD-010.
 *
 * Cada petición olvida la cuenta de la anterior (forgetGuards), como dos peticiones reales al servidor.
 */
trait AltaDePrueba
{
    /** El punto que responde la geocodificación simulada: dentro de la zona de prueba. */
    protected const PUNTO_EN_LA_ZONA = [10.5, -119.5];

    protected Plaza $plaza;

    protected Zona $zona;

    protected Municipio $municipio;

    protected GeocodificadorSimulado $geocodificador;

    /**
     * La plaza de prueba con su zona, el municipio ficticio, la geocodificación que encuentra el punto que se pasa (o
     * ninguno) y el disco de fotos falso.
     *
     * @param  array{float, float}|null  $punto  Latitud y longitud que "encuentra"; null si no encuentra la dirección.
     */
    protected function prepararAlta(?array $punto = self::PUNTO_EN_LA_ZONA): void
    {
        Storage::fake(config('fotos.disco'));

        $this->plaza = $this->plazaDePrueba();
        $this->zona = $this->zonaDePrueba($this->plaza, 'Zona de prueba Centro', $this->cuadrado(-120.0, 10.0));
        $this->municipio = CatalogoDePrueba::municipio();
        $this->geocodificarEn($punto);
    }

    /**
     * La geocodificación simulada, que encuentra ese punto o ninguno.
     *
     * @param  array{float, float}|null  $punto
     */
    protected function geocodificarEn(?array $punto): void
    {
        $this->geocodificador = new GeocodificadorSimulado($punto === null ? null : new Punto(...$punto));
        $this->app->instance(Geocodificador::class, $this->geocodificador);
    }

    protected function cuentaDeLavanderia(): Usuario
    {
        return Usuario::factory()->conRol(Rol::LAVANDERIA)->create();
    }

    /**
     * Una foto de prueba, como la manda la app ya comprimida.
     */
    protected function fotoDePrueba(string $nombre = 'fachada.jpg', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 800, 600)->size($kilobytes);
    }

    /**
     * Un registro completo, con lo que se pase encima: el lavado estándar a 18.50 por kilo y dos ventanas, el lunes y
     * el jueves de 10:00 a 11:00.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function registroCompleto(array $datos = []): array
    {
        return [
            'nombre' => 'Lavandería de prueba Alta',
            'direccion' => [
                'calle' => 'Calle de prueba',
                'numero' => '123',
                'colonia' => 'Colonia de prueba',
                'codigo_postal' => '99000',
                'municipio' => $this->municipio->codigo,
            ],
            'foto' => $this->fotoDePrueba(),
            'servicios' => [
                ['tipo' => 'estandar', 'precio' => '18.50'],
            ],
            'ventanas' => [
                ['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
                ['dia_semana' => 4, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
            ],
            ...$datos,
        ];
    }

    /**
     * La app envía el registro con la sesión de la cuenta. La foto viaja como archivo, en multipart.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function registrar(Usuario $cuenta, array $datos): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->postJson('/api/socio/lavanderias', $datos);
    }

    /**
     * La cuenta registra su lavandería completa y la devuelve.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function registrada(?Usuario $cuenta = null, array $datos = []): Lavanderia
    {
        $cuenta ??= $this->cuentaDeLavanderia();
        $this->registrar($cuenta, $this->registroCompleto($datos))->assertCreated();

        return $this->lavanderiaDe($cuenta);
    }

    /**
     * La app edita la lavandería con la sesión de la cuenta, en JSON.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function editar(Usuario $cuenta, string $lavanderia, array $datos): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)
            ->patchJson("/api/socio/lavanderias/{$lavanderia}", $datos);
    }

    /**
     * La app edita la lavandería con una foto nueva: multipart por POST, con _method=PATCH, porque PHP solo lee los
     * archivos de un POST.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function editarConFoto(Usuario $cuenta, string $lavanderia, array $datos): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)
            ->post("/api/socio/lavanderias/{$lavanderia}", ['_method' => 'PATCH', ...$datos]);
    }

    /**
     * La app pide la lavandería con la sesión de la cuenta.
     */
    protected function consultar(Usuario $cuenta, string $lavanderia): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)
            ->getJson("/api/socio/lavanderias/{$lavanderia}");
    }

    /**
     * La lavandería que quedó registrada a nombre de la cuenta.
     */
    protected function lavanderiaDe(Usuario $cuenta): Lavanderia
    {
        return Lavanderia::query()->where('id_usuarios', $cuenta->getKey())->sole();
    }

    /**
     * Las fotos que quedaron en el disco de fotos.
     *
     * @return list<string>
     */
    protected function archivosDeFotos(): array
    {
        return Storage::disk(config('fotos.disco'))->allFiles();
    }
}
