<?php

namespace Tests\Feature\Registro;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\CuentaExterna;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Las cuatro primeras tablas del negocio, roles, usuarios, rol_usuario y cuentas_externas, y sus modelos de Eloquent
 * (openspec/changes/registro-e-inicio-de-sesion/design.md, "Tablas y migración"; ADR-0016).
 *
 * Escenarios: la parte de la base de registro-E01, registro-E02 y registro-E16, y modelo-E01 a modelo-E17 sobre las
 * tablas nuevas · Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md
 */
class MigracionTest extends TestCase
{
    use RefreshDatabase;

    private const TABLAS = ['roles', 'usuarios', 'rol_usuario', 'cuentas_externas'];

    /** SQLSTATE de PostgreSQL cuando se repite un valor único. */
    private const UNICO_REPETIDO = '23505';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    #[TestDox('modelo-E01 a modelo-E17: las cuatro tablas de las cuentas existen y cumplen la prueba de esquema')]
    public function test_modelo_e01_a_e17_las_cuatro_tablas_de_las_cuentas_existen_y_cumplen_la_prueba_de_esquema(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => in_array($violacion->tabla, self::TABLAS, true),
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
    }

    #[TestDox('modelo-E05: las cuatro tablas de las cuentas no llevan plaza')]
    public function test_modelo_e05_las_cuatro_tablas_de_las_cuentas_no_llevan_plaza(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
            $this->assertFalse(Schema::hasColumn($tabla, 'id_plazas'), "{$tabla} no lleva id_plazas.");
        }
    }

    #[TestDox('modelo-E17: rol_usuario es tabla de enlace, sin llave propia ni fechas de alta y de cambio')]
    public function test_modelo_e17_rol_usuario_es_tabla_de_enlace_sin_llave_propia_ni_fechas_de_alta_y_de_cambio(): void
    {
        $this->assertSame(['id_usuarios', 'id_roles'], Schema::getColumnListing('rol_usuario'));

        $llave = collect(Schema::getIndexes('rol_usuario'))->firstWhere('primary', true);
        $this->assertSame(['id_usuarios', 'id_roles'], $llave['columns'] ?? null);
    }

    #[TestDox('registro-E29: usuarios no guarda una constancia de aceptación del aviso y los términos: los consentimientos son de EL CLIENTE, y si el servidor la guarda lo decide DD-080')]
    public function test_registro_e29_usuarios_no_guarda_una_constancia_de_aceptacion(): void
    {
        $this->assertFalse(Schema::hasColumn('usuarios', 'terminos_aceptados_en'));
        $this->assertSame([], array_values(array_filter(
            Schema::getColumnListing('usuarios'),
            fn (string $columna): bool => str_contains($columna, 'terminos') || str_contains($columna, 'acept'),
        )));
        $this->assertArrayNotHasKey('terminos_aceptados_en', (new Usuario)->getCasts());
    }

    #[TestDox('registro-E01: la migración siembra los cuatro roles')]
    public function test_registro_e01_la_migracion_siembra_los_cuatro_roles(): void
    {
        $this->assertSame(
            ['administracion', 'lavanderia', 'repartidor', 'usuario'],
            DB::table('roles')->orderBy('codigo')->pluck('codigo')->all(),
        );
        $this->assertSame(0, DB::table('roles')->whereNull('nombre')->orWhere('nombre', '')->count());
    }

    #[TestDox('registro-E01: una cuenta nace activa y la base solo acepta los estados activo y bloqueado')]
    public function test_registro_e01_una_cuenta_nace_activa_y_la_base_solo_acepta_los_estados_activo_y_bloqueado(): void
    {
        $cuenta = $this->insertarUsuario('persona@ejemplo.com');

        $this->assertSame('activo', DB::table('usuarios')->where('id_usuarios', $cuenta)->value('estado'));

        DB::table('usuarios')->where('id_usuarios', $cuenta)->update(['estado' => 'bloqueado']);
        $this->assertSame('bloqueado', DB::table('usuarios')->where('id_usuarios', $cuenta)->value('estado'));

        $this->assertRechazada(
            fn () => DB::table('usuarios')->where('id_usuarios', $cuenta)->update(['estado' => 'suspendido']),
            self::CHECK_INCUMPLIDO,
        );
    }

    #[TestDox('registro-E02: la base rechaza un segundo correo igual, sin distinguir mayúsculas')]
    public function test_registro_e02_la_base_rechaza_un_segundo_correo_igual_sin_distinguir_mayusculas(): void
    {
        $this->insertarUsuario('persona@ejemplo.com');

        $this->assertRechazada(fn () => $this->insertarUsuario('persona@ejemplo.com'), self::UNICO_REPETIDO);
        $this->assertRechazada(fn () => $this->insertarUsuario('Persona@Ejemplo.COM'), self::UNICO_REPETIDO);

        $this->assertSame(1, DB::table('usuarios')->count());
    }

    #[TestDox('registro-E02: la contraseña es opcional, para quien entra con Google o con Apple')]
    public function test_registro_e02_la_contrasena_es_opcional_para_quien_entra_con_google_o_con_apple(): void
    {
        $cuenta = $this->insertarUsuario('persona@ejemplo.com');

        $this->assertNull(DB::table('usuarios')->where('id_usuarios', $cuenta)->value('password'));
    }

    #[TestDox('registro-E16: la base rechaza dos cuentas externas con el mismo proveedor y sujeto')]
    public function test_registro_e16_la_base_rechaza_dos_cuentas_externas_con_el_mismo_proveedor_y_sujeto(): void
    {
        $una = $this->insertarUsuario('una@ejemplo.com');
        $otra = $this->insertarUsuario('otra@ejemplo.com');

        $this->insertarCuentaExterna($una, 'google', 'sujeto-1');
        // El mismo sujeto con otro proveedor es otra identidad.
        $this->insertarCuentaExterna($una, 'apple', 'sujeto-1');

        $this->assertRechazada(fn () => $this->insertarCuentaExterna($otra, 'google', 'sujeto-1'), self::UNICO_REPETIDO);

        $this->assertSame(2, DB::table('cuentas_externas')->count());
    }

    #[TestDox('registro-E16: la base solo acepta google y apple como proveedor')]
    public function test_registro_e16_la_base_solo_acepta_google_y_apple_como_proveedor(): void
    {
        $cuenta = $this->insertarUsuario('persona@ejemplo.com');

        $this->assertRechazada(
            fn () => $this->insertarCuentaExterna($cuenta, 'facebook', 'sujeto-1'),
            self::CHECK_INCUMPLIDO,
        );
    }

    #[TestDox('modelo-E16: Rol, Usuario y CuentaExterna declaran su tabla y su llave igual que la base')]
    public function test_modelo_e16_rol_usuario_y_cuenta_externa_declaran_su_tabla_y_su_llave_igual_que_la_base(): void
    {
        $this->assertSame(['roles', 'id_roles'], [(new Rol)->getTable(), (new Rol)->getKeyName()]);
        $this->assertSame(['usuarios', 'id_usuarios'], [(new Usuario)->getTable(), (new Usuario)->getKeyName()]);
        $this->assertSame(
            ['cuentas_externas', 'id_cuentas_externas'],
            [(new CuentaExterna)->getTable(), (new CuentaExterna)->getKeyName()],
        );

        $this->assertSame(
            [],
            array_map('strval', (new RevisorDeModelos)->revisar([Rol::class, Usuario::class, CuentaExterna::class])),
        );
    }

    #[TestDox('registro-E01: el rol de la cuenta vive en rol_usuario y el modelo lo lee por sus dos llaves')]
    public function test_registro_e01_el_rol_de_la_cuenta_vive_en_rol_usuario_y_el_modelo_lo_lee_por_sus_dos_llaves(): void
    {
        $usuario = Usuario::create(['correo' => 'persona@ejemplo.com']);
        $rol = Rol::where('codigo', 'usuario')->firstOrFail();

        $usuario->roles()->attach($rol);

        $this->assertTrue(DB::table('rol_usuario')
            ->where('id_usuarios', $usuario->getKey())
            ->where('id_roles', $rol->getKey())
            ->exists());
        $this->assertSame(['usuario'], $usuario->roles()->pluck('codigo')->all());
        $this->assertTrue($usuario->is($rol->usuarios()->first()));
    }

    #[TestDox('registro-E16: la cuenta externa pertenece a su usuario por id_usuarios')]
    public function test_registro_e16_la_cuenta_externa_pertenece_a_su_usuario_por_id_usuarios(): void
    {
        $usuario = Usuario::create(['correo' => 'persona@ejemplo.com']);

        $cuenta = $usuario->cuentasExternas()->create([
            'proveedor' => 'google',
            'sujeto' => 'sujeto-1',
            'correo' => 'persona@ejemplo.com',
        ]);

        $this->assertSame($usuario->getKey(), $cuenta->id_usuarios);
        $this->assertTrue($usuario->is($cuenta->usuario));
        $this->assertTrue($cuenta->is($usuario->cuentasExternas()->first()));
    }

    #[TestDox('registro-E07: Usuario es la cuenta del proveedor de autenticación, con tokens de Sanctum y borrado suave')]
    public function test_registro_e07_usuario_es_la_cuenta_del_proveedor_de_autenticacion_con_tokens_de_sanctum_y_borrado_suave(): void
    {
        $this->assertSame(Usuario::class, config('auth.providers.users.model'));
        $this->assertSame(Usuario::class, Auth::guard('web')->getProvider()->getModel());

        $this->assertInstanceOf(Authenticatable::class, new Usuario);
        $this->assertContains(HasApiTokens::class, class_uses_recursive(Usuario::class));
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Usuario::class));

        $usuario = Usuario::create(['correo' => 'persona@ejemplo.com']);
        $this->assertTrue(Str::isUlid($usuario->id_publico));

        $usuario->delete();

        $this->assertSoftDeleted('usuarios', ['id_usuarios' => $usuario->getKey()]);
        $this->assertNull(Usuario::find($usuario->getKey()));
    }

    #[TestDox('registro-E22: la recuperación de la contraseña usa el correo de la cuenta')]
    public function test_registro_e22_la_recuperacion_de_la_contrasena_usa_el_correo_de_la_cuenta(): void
    {
        $usuario = Usuario::create(['correo' => 'persona@ejemplo.com']);

        $this->assertInstanceOf(CanResetPassword::class, $usuario);
        $this->assertSame('persona@ejemplo.com', $usuario->getEmailForPasswordReset());
    }

    private function insertarUsuario(string $correo): int
    {
        return DB::table('usuarios')->insertGetId([
            'id_publico' => (string) Str::ulid(),
            'correo' => $correo,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuarios');
    }

    private function insertarCuentaExterna(int $usuario, string $proveedor, string $sujeto): void
    {
        DB::table('cuentas_externas')->insert([
            'id_usuarios' => $usuario,
            'proveedor' => $proveedor,
            'sujeto' => $sujeto,
            'correo' => 'persona@ejemplo.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * La operación falla con el SQLSTATE dado. Corre en una transacción anidada, un savepoint, para que el error no
     * aborte la transacción de la prueba.
     */
    private function assertRechazada(callable $operacion, string $sqlstate): void
    {
        try {
            DB::transaction($operacion);
            $this->fail("La base debió rechazar la operación con SQLSTATE {$sqlstate}.");
        } catch (QueryException $error) {
            $this->assertSame($sqlstate, $error->getCode(), $error->getMessage());
        }
    }
}
