<?php

namespace Tests\Feature\PedidosLavanderia;

use App\Models\Lavanderia;
use App\Models\Usuario;
use Database\Seeders\LavanderiasDePruebaSeeder;
use Database\Seeders\PlazasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Los datos de prueba del Módulo B: cada lavandería de prueba de Durango con una cuenta de socio lavandería que entra a
 * la app con correo y contraseña, para probar en un teléfono su lista de pedidos, aceptar, rechazar y sus botones
 * (pedidos-de-la-lavanderia y estados-del-pedido). Son tan ficticias como sus lavanderías, y solo se siembran en local:
 * fuera de él, la lavandería queda con su cuenta sin contraseña del dominio reservado para pruebas (cercanas-E22).
 *
 * Escenarios a los que sirve: pedidos-lavanderia-E01 y estados-E03 · Spec:
 * openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md
 */
class CuentasDePruebaTest extends TestCase
{
    use RefreshDatabase;

    /** El correo de la cuenta de cada lavandería de prueba de Durango. */
    private const CUENTAS = [
        'Lavandería de prueba Durango Centro' => 'lavanderia.centro@ejemplo.com',
        'Lavandería de prueba Durango Norte' => 'lavanderia.norte@ejemplo.com',
        'Lavandería de prueba Durango Sur' => 'lavanderia.sur@ejemplo.com',
    ];

    #[TestDox('pedidos-lavanderia-E01, datos de prueba: en local, cada lavandería de prueba de Durango la administra su cuenta de socio lavandería, que inicia sesión en la app con su correo y la contraseña de prueba')]
    public function test_en_local_cada_lavanderia_de_durango_tiene_su_cuenta_que_entra_a_la_app(): void
    {
        $this->enLocal();

        $this->seed();

        foreach (self::CUENTAS as $lavanderia => $correo) {
            $cuenta = Lavanderia::query()->where('nombre', $lavanderia)->sole()->administrador;

            $this->assertSame($correo, $cuenta->correo);
            $this->assertSame(['lavanderia'], $cuenta->roles->pluck('codigo')->all());
            $this->assertTrue(Hash::check(LavanderiasDePruebaSeeder::CONTRASENA_DE_PRUEBA, $cuenta->password));

            $this->app['auth']->forgetGuards();
            $this->postJson('/api/sesion', ['correo' => $correo, 'password' => LavanderiasDePruebaSeeder::CONTRASENA_DE_PRUEBA])
                ->assertOk()
                ->assertJsonPath('usuario.correo', $correo)
                ->assertJsonPath('usuario.rol', 'lavanderia');
        }
    }

    #[TestDox('pedidos-lavanderia-E01, datos de prueba: con la sesión de su cuenta, la lavandería de prueba de Durango pide su lista de pedidos')]
    public function test_en_local_la_cuenta_de_prueba_pide_su_lista_de_pedidos(): void
    {
        $this->enLocal();
        $this->seed();

        $token = $this->postJson('/api/sesion', [
            'correo' => self::CUENTAS['Lavandería de prueba Durango Centro'],
            'password' => LavanderiasDePruebaSeeder::CONTRASENA_DE_PRUEBA,
        ])->assertOk()->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/lavanderia/pedidos')->assertOk()->assertExactJson(['pedidos' => []]);
    }

    #[TestDox('pedidos-lavanderia-E01, datos de prueba: sembrar dos veces no duplica las cuentas, y la contraseña de prueba sigue sirviendo')]
    public function test_sembrar_dos_veces_no_duplica_las_cuentas(): void
    {
        $this->enLocal();

        $this->seed();
        $cuentas = Usuario::withTrashed()->count();
        $this->seed();

        $this->assertSame($cuentas, Usuario::withTrashed()->count());
        foreach (self::CUENTAS as $correo) {
            $this->assertTrue(Hash::check(LavanderiasDePruebaSeeder::CONTRASENA_DE_PRUEBA, Usuario::conCorreo($correo)->sole()->password));
        }
    }

    #[TestDox('pedidos-lavanderia-E01, datos de prueba: fuera de local, ninguna cuenta de prueba tiene contraseña ni existen las cuentas que entran a la app')]
    public function test_fuera_de_local_ninguna_cuenta_de_prueba_entra_a_la_app(): void
    {
        $this->assertFalse($this->app->environment('local'));

        $this->seed(PlazasDePruebaSeeder::class);
        $this->seed(LavanderiasDePruebaSeeder::class);

        $this->assertSame(0, Usuario::query()->whereIn('correo', array_values(self::CUENTAS))->count());
        $this->assertSame(0, Usuario::query()->whereNotNull('password')->count());
    }

    private function enLocal(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->assertTrue($this->app->environment('local'));
    }
}
