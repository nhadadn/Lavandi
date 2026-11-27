<?php

namespace Tests\Feature\Registro;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * El registro con correo y contraseña: POST /api/registro crea la cuenta con el rol de usuario, guarda la contraseña
 * con hash y nunca la devuelve (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #2; :20, Protección de
 * contraseñas; ADR-0005 y ADR-0018).
 *
 * Escenarios: registro-E01, registro-E02, registro-E03, registro-E05 y registro-E06 · Spec:
 * openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md
 */
class RegistroConCorreoTest extends TestCase
{
    use RefreshDatabase;

    /** La regla que prueban estas pruebas: la de ADR-0018 (C-223). */
    private const CONTRASENA_MINIMA = 8;

    private const CONTRASENA = 'una-clave-larga';

    protected function setUp(): void
    {
        parent::setUp();

        config(['acceso.contrasena_minima' => self::CONTRASENA_MINIMA]);
    }

    #[TestDox('registro-E01: una cuenta nueva queda con el rol de usuario y la respuesta trae un token de sesión')]
    public function test_registro_e01_una_cuenta_nueva_queda_con_el_rol_de_usuario_y_la_respuesta_trae_un_token_de_sesion(): void
    {
        $respuesta = $this->postJson('/api/registro', [
            'correo' => 'persona@ejemplo.com',
            'password' => self::CONTRASENA,
        ]);

        $respuesta->assertCreated();
        $respuesta->assertJsonStructure(['token', 'usuario' => ['id_publico', 'correo', 'rol']]);
        $respuesta->assertJsonPath('usuario.correo', 'persona@ejemplo.com');
        $respuesta->assertJsonPath('usuario.rol', 'usuario');

        $usuario = Usuario::sole();
        $this->assertSame($usuario->id_publico, $respuesta->json('usuario.id_publico'));
        $this->assertTrue(Str::isUlid($respuesta->json('usuario.id_publico')));
        $this->assertSame([Rol::USUARIO], $usuario->roles()->pluck('codigo')->all());
        $this->assertSame('activo', $usuario->fresh()->estado);

        // El token abre la sesión de esa cuenta.
        $this->withToken($respuesta->json('token'))
            ->getJson('/api/cuenta')
            ->assertOk()
            ->assertJsonPath('usuario.id_publico', $usuario->id_publico);
    }

    #[TestDox('registro-E01: si la petición trae otro rol, la cuenta queda igual con el rol de usuario')]
    public function test_registro_e01_si_la_peticion_trae_otro_rol_la_cuenta_queda_igual_con_el_rol_de_usuario(): void
    {
        $idAdministracion = Rol::where('codigo', Rol::ADMINISTRACION)->value('id_roles');

        $respuesta = $this->postJson('/api/registro', [
            'correo' => 'persona@ejemplo.com',
            'password' => self::CONTRASENA,
            'rol' => Rol::ADMINISTRACION,
            'roles' => [Rol::ADMINISTRACION, 'lavanderia'],
            'id_roles' => $idAdministracion,
            'estado' => 'bloqueado',
        ]);

        $respuesta->assertCreated();
        $respuesta->assertJsonPath('usuario.rol', 'usuario');

        $usuario = Usuario::sole();
        $this->assertSame([Rol::USUARIO], $usuario->roles()->pluck('codigo')->all());
        $this->assertSame(1, DB::table('rol_usuario')->count());
        $this->assertSame('activo', $usuario->fresh()->estado);
    }

    #[TestDox('registro-E02: un correo que ya tiene cuenta se rechaza con un error en el correo, y no se crea otra')]
    public function test_registro_e02_un_correo_que_ya_tiene_cuenta_se_rechaza_con_un_error_en_el_correo_y_no_se_crea_otra(): void
    {
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'persona@ejemplo.com']);

        $respuesta = $this->postJson('/api/registro', [
            'correo' => 'persona@ejemplo.com',
            'password' => self::CONTRASENA,
        ]);

        $respuesta->assertUnprocessable();
        $respuesta->assertJsonValidationErrors(['correo' => 'Ese correo ya tiene una cuenta.']);
        $respuesta->assertJsonMissingValidationErrors(['password']);
        $respuesta->assertJsonMissingPath('token');
        $this->assertSame(1, Usuario::count());
    }

    #[TestDox('registro-E02: el correo repetido se rechaza sin distinguir mayúsculas')]
    public function test_registro_e02_el_correo_repetido_se_rechaza_sin_distinguir_mayusculas(): void
    {
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'persona@ejemplo.com']);

        $this->postJson('/api/registro', ['correo' => 'Persona@Ejemplo.COM', 'password' => self::CONTRASENA])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correo' => 'Ese correo ya tiene una cuenta.']);

        // Una fila escrita fuera de la API, con otras mayúsculas, también cuenta.
        DB::table('usuarios')->insert([
            'id_publico' => (string) Str::ulid(),
            'correo' => 'OTRA@Ejemplo.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/registro', ['correo' => 'otra@ejemplo.com', 'password' => self::CONTRASENA])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correo' => 'Ese correo ya tiene una cuenta.']);

        $this->assertSame(2, Usuario::count());
    }

    #[TestDox('registro-E02: el correo de una cuenta borrada tampoco se puede volver a registrar')]
    public function test_registro_e02_el_correo_de_una_cuenta_borrada_tampoco_se_puede_volver_a_registrar(): void
    {
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'persona@ejemplo.com'])->delete();

        $this->postJson('/api/registro', ['correo' => 'persona@ejemplo.com', 'password' => self::CONTRASENA])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correo' => 'Ese correo ya tiene una cuenta.']);

        $this->assertSame(1, Usuario::withTrashed()->count());
    }

    #[TestDox('registro-E02: el correo se guarda en minúsculas')]
    public function test_registro_e02_el_correo_se_guarda_en_minusculas(): void
    {
        $this->postJson('/api/registro', ['correo' => 'Persona@Ejemplo.COM', 'password' => self::CONTRASENA])
            ->assertCreated()
            ->assertJsonPath('usuario.correo', 'persona@ejemplo.com');

        $this->assertSame('persona@ejemplo.com', DB::table('usuarios')->value('correo'));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, string>}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'correo mal formado' => [
                ['correo' => 'persona-arroba-ejemplo', 'password' => self::CONTRASENA],
                ['correo' => 'El correo no es válido.'],
            ],
            'sin correo' => [
                ['password' => self::CONTRASENA],
                ['correo' => 'Escribe tu correo.'],
            ],
            'correo que no es texto' => [
                ['correo' => ['persona@ejemplo.com'], 'password' => self::CONTRASENA],
                ['correo' => 'El correo no es válido.'],
            ],
            'contraseña vacía' => [
                ['correo' => 'persona@ejemplo.com', 'password' => ''],
                ['password' => 'Escribe una contraseña.'],
            ],
            'sin contraseña' => [
                ['correo' => 'persona@ejemplo.com'],
                ['password' => 'Escribe una contraseña.'],
            ],
            'contraseña de 7 caracteres' => [
                ['correo' => 'persona@ejemplo.com', 'password' => '1234567'],
                ['password' => 'La contraseña debe tener al menos 8 caracteres.'],
            ],
            'los dos campos mal' => [
                ['correo' => 'persona', 'password' => 'corta'],
                [
                    'correo' => 'El correo no es válido.',
                    'password' => 'La contraseña debe tener al menos 8 caracteres.',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $errores
     */
    #[DataProvider('datosInvalidos')]
    #[TestDox('registro-E03: los datos inválidos se rechazan con el error de cada campo ($_dataName)')]
    public function test_registro_e03_los_datos_invalidos_se_rechazan_con_el_error_de_cada_campo(array $datos, array $errores): void
    {
        $respuesta = $this->postJson('/api/registro', $datos);

        $respuesta->assertUnprocessable();
        $respuesta->assertJsonStructure(['message', 'errors']);
        $respuesta->assertJsonValidationErrors($errores);
        $this->assertSame(array_keys($errores), array_keys($respuesta->json('errors')));
        $respuesta->assertJsonMissingPath('token');
        $this->assertSame(0, Usuario::withTrashed()->count());
    }

    #[TestDox('registro-E03: una contraseña con el mínimo exacto de caracteres se acepta')]
    public function test_registro_e03_una_contrasena_con_el_minimo_exacto_de_caracteres_se_acepta(): void
    {
        $this->postJson('/api/registro', ['correo' => 'persona@ejemplo.com', 'password' => '12345678'])
            ->assertCreated();
    }

    #[TestDox('registro-E03: el mínimo de la contraseña sale de la configuración')]
    public function test_registro_e03_el_minimo_de_la_contrasena_sale_de_la_configuracion(): void
    {
        config(['acceso.contrasena_minima' => 12]);

        $this->postJson('/api/registro', ['correo' => 'persona@ejemplo.com', 'password' => '12345678901'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'La contraseña debe tener al menos 12 caracteres.']);

        $this->postJson('/api/registro', ['correo' => 'persona@ejemplo.com', 'password' => '123456789012'])
            ->assertCreated();
    }

    #[TestDox('registro-E05: la contraseña se guarda con un hash con el que se valida, nunca en texto legible')]
    public function test_registro_e05_la_contrasena_se_guarda_con_un_hash_con_el_que_se_valida_nunca_en_texto_legible(): void
    {
        $this->postJson('/api/registro', ['correo' => 'persona@ejemplo.com', 'password' => self::CONTRASENA])
            ->assertCreated();

        $guardada = DB::table('usuarios')->value('password');

        $this->assertNotSame(self::CONTRASENA, $guardada);
        $this->assertStringNotContainsString(self::CONTRASENA, $guardada);
        $this->assertTrue(Hash::isHashed($guardada));
        $this->assertTrue(Hash::check(self::CONTRASENA, $guardada));
    }

    /**
     * Un costo igual al de las pruebas y uno mayor que el de cualquier entorno: el cast hashed guardaba tal cual el
     * primero y rechazaba con error el segundo.
     *
     * @return array<string, array{0: int}>
     */
    public static function costosDeUnHash(): array
    {
        return [
            'hash de costo 4' => [4],
            'hash de costo 13' => [13],
        ];
    }

    #[DataProvider('costosDeUnHash')]
    #[TestDox('registro-E05: una contraseña con forma de hash se guarda con su propio hash, no tal cual ($_dataName)')]
    public function test_registro_e05_una_contrasena_con_forma_de_hash_se_guarda_con_su_propio_hash_no_tal_cual(int $costo): void
    {
        // P parece un hash de bcrypt de la contraseña "x", que no cumple la regla (registro-E03).
        $contrasena = Hash::make('x', ['rounds' => $costo]);

        $this->postJson('/api/registro', ['correo' => 'persona@ejemplo.com', 'password' => $contrasena])
            ->assertCreated();

        $guardada = (string) DB::table('usuarios')->value('password');

        $this->assertNotSame($contrasena, $guardada);
        $this->assertTrue(Hash::check($contrasena, $guardada));

        // Entra con P, no con la contraseña de la que P es hash.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/sesion', ['correo' => 'persona@ejemplo.com', 'password' => 'x'])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/sesion', ['correo' => 'persona@ejemplo.com', 'password' => $contrasena])->assertOk();
    }

    #[TestDox('registro-E06: la respuesta del registro no incluye la contraseña ni su hash')]
    public function test_registro_e06_la_respuesta_del_registro_no_incluye_la_contrasena_ni_su_hash(): void
    {
        $respuesta = $this->postJson('/api/registro', [
            'correo' => 'persona@ejemplo.com',
            'password' => self::CONTRASENA,
        ]);

        $respuesta->assertCreated();
        $respuesta->assertJsonMissingPath('usuario.password');
        $this->assertStringNotContainsString(self::CONTRASENA, $respuesta->getContent());
        $this->assertStringNotContainsString(DB::table('usuarios')->value('password'), $respuesta->getContent());
        $this->assertStringNotContainsString('password', $respuesta->getContent());
    }

    #[TestDox('registro-E06: el modelo de la cuenta oculta la contraseña al convertirse en JSON')]
    public function test_registro_e06_el_modelo_de_la_cuenta_oculta_la_contrasena_al_convertirse_en_json(): void
    {
        $usuario = Usuario::factory()->create();

        $this->assertArrayNotHasKey('password', $usuario->toArray());
        $this->assertStringNotContainsString($usuario->password, $usuario->toJson());
    }
}
