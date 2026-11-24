<?php

namespace Tests\Feature\AltaLavanderias;

use App\Models\Lavanderia;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\AltaDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El perfil y los horarios de la lavandería desde la app: la lavandería edita su información con las mismas
 * validaciones del registro, y nunca la de otra (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6;
 * :20:Medidas de seguridad, separación por tipo de usuario). Una lavandería ajena responde igual que una que no existe
 * (ADR-0019, decisión 3).
 *
 * Escenarios: alta-lavanderias-E18 y alta-lavanderias-E20 · Tarea 2.1 · Spec:
 * openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class PerfilTest extends TestCase
{
    use AltaDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararAlta();
    }

    #[TestDox('alta-lavanderias-E18: la lavandería cambia su foto y su horario desde la app y el servidor guarda los datos nuevos')]
    public function test_alta_lavanderias_e18_la_lavanderia_cambia_su_foto_y_su_horario_y_se_guardan(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $fotoAnterior = $lavanderia->fotos()->sole()->ruta;

        $respuesta = $this->editarConFoto($cuenta, $lavanderia->id_publico, [
            'foto' => $this->fotoDePrueba('nueva.png'),
            'ventanas' => [
                ['dia_semana' => 2, 'hora_inicio' => '09:00', 'hora_fin' => '10:00'],
                ['dia_semana' => 2, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
            ],
        ])->assertOk();

        // La foto nueva reemplaza a la anterior, cuyo archivo se borra.
        $foto = $lavanderia->fotos()->sole();
        $this->assertNotSame($fotoAnterior, $foto->ruta);
        $this->assertSame([$foto->ruta], $this->archivosDeFotos());
        Storage::disk(config('fotos.disco'))->assertMissing($fotoAnterior);

        // El horario es el nuevo, completo.
        $respuesta->assertJsonPath('lavanderia.ventanas', [
            ['dia_semana' => 2, 'hora_inicio' => '09:00', 'hora_fin' => '10:00'],
            ['dia_semana' => 2, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
        ]);
        $this->assertSame(2, $lavanderia->horarios()->count());

        // Lo que no se mandó sigue igual.
        $respuesta->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba Alta');
        $respuesta->assertJsonPath('lavanderia.servicios.0.precio', '18.50');
    }

    #[TestDox('alta-lavanderias-E18: la lavandería cambia su nombre, sus servicios y su dirección, que se geocodifica de nuevo una sola vez')]
    public function test_alta_lavanderias_e18_cambia_nombre_servicios_y_direccion(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $this->geocodificarEn([10.6, -119.4]);

        $this->editar($cuenta, $lavanderia->id_publico, [
            'nombre' => 'Lavandería de prueba Renovada',
            'direccion' => [
                'calle' => 'Calle de prueba nueva',
                'numero' => '45',
                'colonia' => 'Colonia de prueba',
                'codigo_postal' => '99000',
                'municipio' => $this->municipio->codigo,
            ],
            'servicios' => [['tipo' => 'planchado', 'precio' => '30.00']],
        ])->assertOk()
            ->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba Renovada')
            ->assertJsonPath('lavanderia.direccion.calle', 'Calle de prueba nueva')
            ->assertJsonPath('lavanderia.servicios', [[
                'tipo' => 'planchado',
                'nombre' => 'Lavado con planchado',
                'precio' => '30.00',
                'unidad' => ['codigo' => 'kg', 'nombre' => 'kilo'],
            ]]);

        $this->assertCount(1, $this->geocodificador->consultas);
        $this->assertSame($this->plaza->getKey(), $lavanderia->fresh()->id_plazas);
        // El servicio que ya no ofrece queda inactivo, sin borrarse.
        $this->assertSame(
            ['estandar' => false, 'planchado' => true],
            $lavanderia->servicios()->with('tipo')->get()->mapWithKeys(fn ($servicio): array => [$servicio->tipo->codigo => $servicio->activo])->sortKeys()->all(),
        );
    }

    #[TestDox('alta-lavanderias-E18: la misma dirección que ya tiene no se vuelve a geocodificar')]
    public function test_alta_lavanderias_e18_la_misma_direccion_no_se_vuelve_a_geocodificar(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $this->geocodificarEn([10.6, -119.4]);

        $this->editar($cuenta, $lavanderia->id_publico, [
            'direccion' => $this->registroCompleto()['direccion'],
        ])->assertOk();

        $this->assertSame([], $this->geocodificador->consultas);
    }

    #[TestDox('alta-lavanderias-E18: la edición aplica las validaciones del registro y, si rechaza, no cambia nada')]
    public function test_alta_lavanderias_e18_la_edicion_aplica_las_validaciones_del_registro(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $antes = $this->consultar($cuenta, $lavanderia->id_publico)->json('lavanderia');

        $this->editar($cuenta, $lavanderia->id_publico, [
            'nombre' => '',
            'direccion' => ['calle' => 'Calle de prueba'],
            'servicios' => [['tipo' => 'calzado', 'precio' => '90.00']],
            'ventanas' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'nombre',
            'direccion.numero',
            'direccion.municipio',
            'servicios.0.tipo',
            'ventanas',
        ]);

        $this->editarConFoto($cuenta, $lavanderia->id_publico, [
            'foto' => $this->fotoDePrueba('nueva.jpg', kilobytes: (int) config('fotos.peso_maximo_kb') + 1),
        ])->assertUnprocessable()->assertJsonValidationErrors(['foto']);

        $this->assertSame($this->sinUrl($antes), $this->sinUrl($this->consultar($cuenta, $lavanderia->id_publico)->json('lavanderia')));
        $this->assertCount(1, $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E18: una dirección nueva fuera de las zonas no se guarda')]
    public function test_alta_lavanderias_e18_una_direccion_nueva_fuera_de_las_zonas_no_se_guarda(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $this->geocodificarEn([30.0, -100.0]);

        $this->editar($cuenta, $lavanderia->id_publico, [
            'direccion' => [...$this->registroCompleto()['direccion'], 'calle' => 'Calle lejana'],
        ])->assertUnprocessable()->assertJsonPath('motivo', 'fuera_de_la_zona_de_servicio');

        $this->assertSame('Calle de prueba', $lavanderia->fresh()->calle);
    }

    #[TestDox('alta-lavanderias-E18: una dirección nueva en la zona de otra plaza no se guarda: el cambio de plaza espera la pregunta abierta 5')]
    public function test_alta_lavanderias_e18_una_direccion_nueva_en_otra_plaza_no_se_guarda(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $otra = $this->plazaDePrueba('Plaza de prueba 2');
        $this->zonaDePrueba($otra, 'Zona de prueba Norte', $this->cuadrado(-116.0, 10.0));
        $this->geocodificarEn([10.5, -115.5]);

        $this->editar($cuenta, $lavanderia->id_publico, [
            'direccion' => [...$this->registroCompleto()['direccion'], 'calle' => 'Calle de otra plaza'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['direccion'])
            ->assertJsonPath('motivo', 'en_otra_plaza');

        $lavanderia->refresh();
        $this->assertSame([$this->plaza->getKey(), 'Calle de prueba'], [$lavanderia->id_plazas, $lavanderia->calle]);
    }

    #[TestDox('alta-lavanderias-E18: la lavandería ve su información y sus ventanas actuales, y su lista trae solo la suya')]
    public function test_alta_lavanderias_e18_la_lavanderia_ve_su_informacion_y_sus_ventanas(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $this->registrada();

        $this->consultar($cuenta, $lavanderia->id_publico)
            ->assertOk()
            ->assertJsonPath('lavanderia.id_publico', $lavanderia->id_publico)
            ->assertJsonPath('lavanderia.ventanas.1', ['dia_semana' => 4, 'hora_inicio' => '10:00', 'hora_fin' => '11:00']);

        $this->app['auth']->forgetGuards();
        $this->withToken($cuenta->createToken('app')->plainTextToken)->getJson('/api/socio/lavanderias')
            ->assertOk()
            ->assertJsonCount(1, 'lavanderias')
            ->assertJsonPath('lavanderias.0.id_publico', $lavanderia->id_publico);
    }

    #[TestDox('alta-lavanderias-E20: una lavandería no edita la información ni las ventanas de otra, y los datos de la otra no cambian')]
    public function test_alta_lavanderias_e20_una_lavanderia_no_edita_la_informacion_ni_las_ventanas_de_otra(): void
    {
        $primera = $this->cuentaDeLavanderia();
        $this->registrada($primera);
        $segunda = $this->cuentaDeLavanderia();
        $deLaSegunda = $this->registrada($segunda, ['nombre' => 'Lavandería de prueba Segunda']);
        $antes = $this->consultar($segunda, $deLaSegunda->id_publico)->json('lavanderia');

        $this->editar($primera, $deLaSegunda->id_publico, [
            'nombre' => 'Lavandería ajena',
            'ventanas' => [['dia_semana' => 7, 'hora_inicio' => '20:00', 'hora_fin' => '21:00']],
        ])->assertNotFound()->assertJsonPath('message', 'No existe la lavandería.')->assertJsonMissingPath('lavanderia');

        $this->editarConFoto($primera, $deLaSegunda->id_publico, ['foto' => $this->fotoDePrueba('ajena.jpg')])
            ->assertNotFound();

        // Tampoco la ve, ni la encuentra en su lista; responde igual que una que no existe.
        $this->consultar($primera, $deLaSegunda->id_publico)->assertNotFound()->assertJsonMissingPath('lavanderia');
        $this->consultar($primera, strtolower((string) Str::ulid()))->assertNotFound();

        $this->assertSame($this->sinUrl($antes), $this->sinUrl($this->consultar($segunda, $deLaSegunda->id_publico)->json('lavanderia')));
        $this->assertCount(2, $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E20: una cuenta de usuario o de administración no ve ni edita una lavandería por la app del socio')]
    public function test_alta_lavanderias_e20_una_cuenta_de_otro_rol_no_ve_ni_edita_la_lavanderia(): void
    {
        $lavanderia = $this->registrada();

        foreach ([Rol::USUARIO, Rol::ADMINISTRACION] as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();

            $this->editar($cuenta, $lavanderia->id_publico, ['nombre' => 'Otra'])->assertForbidden()->assertJsonMissingPath('lavanderia');
            $this->consultar($cuenta, $lavanderia->id_publico)->assertForbidden()->assertJsonMissingPath('lavanderia');
        }

        $this->assertSame('Lavandería de prueba Alta', Lavanderia::sole()->nombre);
    }

    /**
     * La lavandería sin la URL firmada de su foto, que cambia en cada consulta.
     *
     * @param  array<string, mixed>  $lavanderia
     * @return array<string, mixed>
     */
    private function sinUrl(array $lavanderia): array
    {
        unset($lavanderia['foto']);

        return $lavanderia;
    }
}
