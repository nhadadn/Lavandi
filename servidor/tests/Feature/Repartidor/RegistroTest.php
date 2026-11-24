<?php

namespace Tests\Feature\Repartidor;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem as Disco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\DiscoLocalDeFotos;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * El registro del repartidor desde la app, POST /api/repartidor/registro: con nombre, teléfono, correo, foto de perfil y
 * tipo de vehículo, y su contraseña, queda una cuenta de repartidor pendiente, sin acceso a pedidos, hasta que EL CLIENTE
 * lo dé de alta (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #1, "El alta, validación y autorización del
 * repartidor corresponden a EL CLIENTE"; ADR-0005, correo y contraseña para todos los roles).
 *
 * Los cinco datos son obligatorios (design.md, lectura 4) y el tipo de vehículo es texto libre (lectura 3). La foto va al
 * almacenamiento privado de fotos, como la de perfil de cualquier cuenta (ADR-0006); usa el disco local privado de
 * desarrollo, que firma como el de producción. Cómo llega el repartidor a su registro dentro de la app, y si su cuenta
 * tiene otros roles, espera DD-053 (repartidor-E04).
 *
 * Escenarios: repartidor-E01, repartidor-E02 y repartidor-E03 · Spec: openspec/changes/repartidor/specs/repartidor/spec.md
 * · Tarea 1.1
 */
class RegistroTest extends TestCase
{
    use AgendaDePrueba;
    use DiscoLocalDeFotos;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;

    private Disco $disco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disco = $this->usarDiscoLocalDeFotos();
    }

    #[TestDox('repartidor-E01: una persona sin cuenta se registra con sus cinco datos y su contraseña; queda una cuenta de repartidor pendiente, sin plaza, y la respuesta lo dice')]
    public function test_repartidor_e01_se_registra_y_queda_pendiente(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-01 18:00:00', 'UTC'));
        $foto = $this->fotoDePrueba();

        $respuesta = $this->registrarRepartidor($this->datosDelRegistro([
            'nombre' => 'Rubén Repartidor',
            'telefono' => '618 123 4567',
            'correo' => 'Ruben.Repartidor@Ejemplo.com',
            'foto' => $foto,
            'tipo_vehiculo' => 'Bicicleta',
        ]))->assertCreated();

        $cuenta = Usuario::query()->sole();
        $repartidor = Repartidor::query()->sole();

        // La cuenta, con su rol de repartidor y solo ese, sus datos y su contraseña con hash.
        $this->assertSame('ruben.repartidor@ejemplo.com', $cuenta->correo);
        $this->assertSame('Rubén Repartidor', $cuenta->nombre);
        $this->assertSame('618 123 4567', $cuenta->telefono);
        $this->assertSame([Rol::REPARTIDOR], $cuenta->roles->pluck('codigo')->all());
        $this->assertTrue(Hash::check('contrasena-de-prueba', $cuenta->password));
        $this->assertNotSame('contrasena-de-prueba', $cuenta->getRawOriginal('password'));

        // La foto, en el almacenamiento privado, en la carpeta de la cuenta; en la base, solo su ruta.
        $this->assertStringStartsWith("usuarios/{$cuenta->id_publico}/", $cuenta->foto_ruta);
        $this->assertSame([$cuenta->foto_ruta], $this->disco->allFiles());
        $this->assertSame($foto->getContent(), $this->disco->get($cuenta->foto_ruta));

        // El repartidor, pendiente: sin plaza, sin alta, con su tipo de vehículo.
        $this->assertTrue($cuenta->is($repartidor->cuenta));
        $this->assertSame(Repartidor::PENDIENTE, $repartidor->estado);
        $this->assertNull($repartidor->id_plazas);
        $this->assertNull($repartidor->aprobado_en);
        $this->assertSame('Bicicleta', $repartidor->tipo_vehiculo);

        // La respuesta: la sesión, la cuenta y el repartidor, que dice que espera la autorización.
        $respuesta->assertJsonStructure(['token', 'usuario' => ['id_publico', 'correo', 'rol'], 'repartidor']);
        $respuesta->assertJsonPath('usuario.rol', Rol::REPARTIDOR);
        $respuesta->assertJsonPath('repartidor.id_publico', $repartidor->id_publico);
        $respuesta->assertJsonPath('repartidor.nombre', 'Rubén Repartidor');
        $respuesta->assertJsonPath('repartidor.telefono', '618 123 4567');
        $respuesta->assertJsonPath('repartidor.correo', 'ruben.repartidor@ejemplo.com');
        $respuesta->assertJsonPath('repartidor.tipo_vehiculo', 'Bicicleta');
        $respuesta->assertJsonPath('repartidor.estado', Repartidor::PENDIENTE);
        $respuesta->assertJsonPath('repartidor.puede_operar', false);
        $respuesta->assertJsonPath('repartidor.plaza', null);
        $this->assertSame(['url', 'vence_en'], array_keys($respuesta->json('repartidor.foto')));
        $this->assertStringNotContainsString('foto_ruta', $respuesta->getContent());
        $this->assertStringNotContainsString('password', $respuesta->getContent());
    }

    #[TestDox('repartidor-E01: con la sesión del registro, o al iniciar sesión con su correo y su contraseña, el repartidor pendiente consulta su cuenta y su perfil, que dice que espera la autorización')]
    public function test_repartidor_e01_el_pendiente_consulta_su_cuenta_y_su_perfil(): void
    {
        $token = $this->registrarRepartidor($this->datosDelRegistro())->assertCreated()->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/cuenta')
            ->assertOk()
            ->assertJsonPath('usuario.rol', Rol::REPARTIDOR);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/repartidor/perfil')
            ->assertOk()
            ->assertJsonPath('repartidor.estado', Repartidor::PENDIENTE)
            ->assertJsonPath('repartidor.puede_operar', false)
            ->assertJsonPath('repartidor.correo', 'repartidor.prueba@ejemplo.com');

        $this->app['auth']->forgetGuards();
        $sesion = $this->postJson('/api/sesion', ['correo' => 'repartidor.prueba@ejemplo.com', 'password' => 'contrasena-de-prueba'])
            ->assertOk()
            ->assertJsonPath('usuario.rol', Rol::REPARTIDOR)
            ->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($sesion)->getJson('/api/repartidor/perfil')
            ->assertOk()
            ->assertJsonPath('repartidor.estado', Repartidor::PENDIENTE);
    }

    #[TestDox('repartidor-E01: el registro ignora lo que no es uno de sus datos: un estado, una plaza o un rol que mande la app no cambian nada, y la cuenta queda repartidor y pendiente')]
    public function test_repartidor_e01_el_registro_ignora_estado_plaza_y_rol(): void
    {
        $plaza = $this->plazaDePrueba();

        $this->registrarRepartidor($this->datosDelRegistro([
            'estado' => Repartidor::ACTIVO,
            'id_plazas' => $plaza->getKey(),
            'plaza' => $plaza->getKey(),
            'aprobado_en' => '2026-12-01T00:00:00Z',
            'rol' => Rol::ADMINISTRACION,
        ]))->assertCreated()->assertJsonPath('repartidor.estado', Repartidor::PENDIENTE);

        $repartidor = Repartidor::query()->sole();
        $this->assertSame(Repartidor::PENDIENTE, $repartidor->estado);
        $this->assertNull($repartidor->id_plazas);
        $this->assertNull($repartidor->aprobado_en);
        $this->assertSame([Rol::REPARTIDOR], $repartidor->cuenta->roles->pluck('codigo')->all());
    }

    /**
     * Cada uno de los cinco datos, y la contraseña, con el campo de la respuesta y el mensaje.
     *
     * @return array<string, array{string, string}>
     */
    public static function datosObligatorios(): array
    {
        return [
            'sin nombre' => ['nombre', 'Escribe tu nombre.'],
            'sin teléfono' => ['telefono', 'Escribe tu teléfono.'],
            'sin correo' => ['correo', 'Escribe tu correo.'],
            'sin foto de perfil' => ['foto', 'Toma o elige tu foto de perfil.'],
            'sin tipo de vehículo' => ['tipo_vehiculo', 'Escribe tu tipo de vehículo.'],
            'sin contraseña' => ['password', 'Escribe una contraseña.'],
        ];
    }

    #[DataProvider('datosObligatorios')]
    #[TestDox('repartidor-E02: un registro sin alguno de sus datos se rechaza con el campo que falta, y no se crea ninguna cuenta, ningún repartidor ni ninguna foto')]
    public function test_repartidor_e02_un_registro_incompleto_se_rechaza(string $campo, string $mensaje): void
    {
        $this->registrarRepartidor($this->datosDelRegistro([$campo => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$campo => $mensaje])
            ->assertJsonMissingPath('token');

        $this->assertNingunaCuenta();
    }

    #[TestDox('repartidor-E02: un dato vacío o de puros espacios cuenta como faltante: el registro se rechaza y no se crea nada')]
    public function test_repartidor_e02_un_dato_vacio_cuenta_como_faltante(): void
    {
        foreach (['nombre', 'telefono', 'correo', 'tipo_vehiculo'] as $campo) {
            foreach (['', '   '] as $vacio) {
                $this->registrarRepartidor($this->datosDelRegistro([$campo => $vacio]))
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors([$campo]);
            }
        }

        $this->assertNingunaCuenta();
    }

    #[TestDox('repartidor-E02: un correo que ya tiene cuenta, uno que no es correo, un teléfono que no es teléfono o una foto que no es imagen se rechazan, y no se crea nada')]
    public function test_repartidor_e02_los_datos_que_no_valen_se_rechazan(): void
    {
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'ocupado@ejemplo.com']);
        $cuentas = Usuario::withTrashed()->count();

        $casos = [
            'correo' => ['OCUPADO@ejemplo.com', 'Ese correo ya tiene una cuenta.'],
            'telefono' => ['no es un teléfono', 'El teléfono no es válido.'],
            'foto' => [$this->fotoQueNoEsImagen(), null],
        ];

        foreach ($casos as $campo => [$valor, $mensaje]) {
            $respuesta = $this->registrarRepartidor($this->datosDelRegistro([$campo => $valor]))->assertUnprocessable();
            $mensaje === null
                ? $respuesta->assertJsonValidationErrors([$campo])
                : $respuesta->assertJsonValidationErrors([$campo => $mensaje]);
        }

        $this->registrarRepartidor($this->datosDelRegistro(['correo' => 'no-es-un-correo']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correo' => 'El correo no es válido.']);

        $this->assertSame($cuentas, Usuario::withTrashed()->count());
        $this->assertSame(0, Repartidor::withTrashed()->count());
        $this->assertSame([], $this->disco->allFiles());
    }

    #[TestDox('repartidor-E03: un repartidor pendiente que pide su lista de pedidos o intenta aceptar, rechazar o mover un pedido recibe el rechazo de la API, que dice que su registro espera la autorización, y el pedido no cambia')]
    public function test_repartidor_e03_un_repartidor_pendiente_no_opera(): void
    {
        $token = $this->registrarRepartidor($this->datosDelRegistro())->assertCreated()->json('token');
        $pedido = $this->pedidoConfirmado();
        $eventos = $pedido->eventos()->count();

        $peticiones = [
            ['GET', '/api/repartidor/pedidos', []],
            ['GET', "/api/repartidor/pedidos/{$pedido->id_publico}", []],
            ['POST', "/api/repartidor/pedidos/{$pedido->id_publico}/aceptar", []],
            ['POST', "/api/repartidor/pedidos/{$pedido->id_publico}/rechazar", ['motivo' => 'Motivo de prueba.']],
            ['POST', "/api/repartidor/pedidos/{$pedido->id_publico}/estado", ['estado' => EstadoPedido::RECOLECTADO]],
        ];

        foreach ($peticiones as [$metodo, $direccion, $cuerpo]) {
            $this->app['auth']->forgetGuards();

            $this->withToken($token)->json($metodo, $direccion, $cuerpo)
                ->assertForbidden()
                ->assertExactJson([
                    'message' => 'Tu registro espera la autorización de la administración.',
                    'estado' => Repartidor::PENDIENTE,
                ]);
        }

        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->fresh()->estado->codigo);
        $this->assertSame($eventos, $pedido->eventos()->count());
        $this->assertNull($pedido->fresh()->id_repartidores);
    }

    #[TestDox('repartidor-E03: las rutas del repartidor piden su rol: sin sesión responden 401, y una cuenta de usuario o de lavandería recibe 403 sin ningún dato')]
    public function test_repartidor_e03_las_rutas_del_repartidor_piden_su_rol(): void
    {
        $this->getJson('/api/repartidor/perfil')->assertUnauthorized();
        $this->getJson('/api/repartidor/pedidos')->assertUnauthorized();

        foreach ([Rol::USUARIO, Rol::LAVANDERIA] as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();

            foreach (['/api/repartidor/perfil', '/api/repartidor/pedidos'] as $direccion) {
                $this->comoRepartidor($cuenta)->getJson($direccion)
                    ->assertForbidden()
                    ->assertJsonPath('message', 'Esta cuenta no tiene acceso.')
                    ->assertJsonMissingPath('repartidor')
                    ->assertJsonMissingPath('pedidos');
            }
        }
    }

    private function assertNingunaCuenta(): void
    {
        $this->assertSame(0, Usuario::withTrashed()->count());
        $this->assertSame(0, Repartidor::withTrashed()->count());
        $this->assertSame([], $this->disco->allFiles());
    }

    private function fotoQueNoEsImagen(): UploadedFile
    {
        return UploadedFile::fake()->create('documento.pdf', 20, 'application/pdf');
    }

    /**
     * Un pedido confirmado por su pago, como lo confirmará el cobro (C-188), en la plaza de prueba de prepararPedido().
     */
    private function pedidoConfirmado(): Pedido
    {
        $this->prepararPedido();

        return $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
    }
}
