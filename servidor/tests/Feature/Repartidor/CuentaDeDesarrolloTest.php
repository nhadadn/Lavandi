<?php

namespace Tests\Feature\Repartidor;

use App\Models\EstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Factories\UsuarioFactory;
use Database\Seeders\RepartidorDeDesarrolloSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * La cuenta de desarrollo del Módulo C: en local, el sembrado deja repartidor@ejemplo.com, un repartidor activo en la
 * plaza de prueba de Durango, con pedidos de prueba asignados a él, para probar la app del repartidor en un teléfono
 * (openspec/changes/repartidor/design.md, "Cómo se prueba en local"). Quién asigna en la operación real espera DD-014:
 * aquí la oferta la crea el sembrado.
 *
 * Los pedidos nacen por el camino real (CreacionDePedido, el pago con la pasarela simulada, que los confirma por su
 * cobro, y CambioDeEstado), con la lavandería de prueba Durango Norte, y sin mandar avisos ni correos. Son ficticios y solo existen en local.
 *
 * Escenario al que sirve: repartidor-E10, para probarlo a mano · Tarea 1.3
 */
class CuentaDeDesarrolloTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('repartidor-E10, datos de prueba: en local, repartidor@ejemplo.com entra a la app con la contraseña de prueba y ve tres pedidos asignados en la plaza de prueba de Durango: uno que la lavandería no ha respondido, uno que ya aceptó y uno listo para el reparto')]
    public function test_en_local_el_repartidor_de_prueba_entra_y_ve_sus_pedidos(): void
    {
        $this->enLocal();
        // Un jueves a las 08:00 de Durango: hay ventanas ese mismo día, con las 2 horas de anticipación de prueba.
        $this->travelTo(CarbonImmutable::parse('2026-12-03 08:00', 'America/Mexico_City'));

        $this->seed();

        $cuenta = Usuario::query()->where('correo', RepartidorDeDesarrolloSeeder::CORREO)->sole();
        $this->assertSame([Rol::REPARTIDOR], $cuenta->roles->pluck('codigo')->all());
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $cuenta->password));
        $this->assertNotSame(UsuarioFactory::CONTRASENA, $cuenta->getRawOriginal('password'));

        $repartidor = Repartidor::query()->where('id_usuarios', $cuenta->getKey())->sole();
        $this->assertSame(Repartidor::ACTIVO, $repartidor->estado);
        $this->assertNotNull($repartidor->aprobado_en);
        $this->assertSame('Plaza de prueba Durango', $repartidor->plaza->nombre);

        $token = $this->postJson('/api/sesion', ['correo' => RepartidorDeDesarrolloSeeder::CORREO, 'password' => UsuarioFactory::CONTRASENA])
            ->assertOk()
            ->assertJsonPath('usuario.rol', Rol::REPARTIDOR)
            ->json('token');

        $this->app['auth']->forgetGuards();
        $pedidos = $this->withToken($token)->getJson('/api/repartidor/pedidos')->assertOk()->json('pedidos');

        $this->assertCount(3, $pedidos);
        $this->assertEqualsCanonicalizing(
            [EstadoPedido::CONFIRMADO, EstadoPedido::ACEPTADO, EstadoPedido::LISTO],
            array_column(array_column($pedidos, 'estado'), 'codigo'),
        );

        foreach ($pedidos as $pedido) {
            $this->assertSame(PedidoAsignacion::OFRECIDO, $pedido['asignacion']['estado']);
            $this->assertNotNull($pedido['folio']);
            $this->assertSame('Lavandería de prueba Durango Norte', $pedido['lavanderia']['nombre']);
            // Confirmado por su cobro, pagado en la pasarela simulada (pago-E18).
            $cobro = Pedido::query()->where('id_publico', $pedido['id_publico'])->sole()->cobro;
            $this->assertSame([Pago::PAGADO, Pago::PASARELA_SIMULADA], [$cobro->estado, $cobro->pasarela]);
        }
    }

    #[TestDox('repartidor-E10, datos de prueba: sembrar dos veces no duplica la cuenta, el repartidor ni sus pedidos')]
    public function test_sembrar_dos_veces_no_duplica(): void
    {
        $this->enLocal();
        $this->travelTo(CarbonImmutable::parse('2026-12-03 08:00', 'America/Mexico_City'));

        $this->seed();
        $conteos = [Usuario::withTrashed()->count(), Repartidor::withTrashed()->count(), Pedido::count(), PedidoAsignacion::count()];
        $this->seed();

        $this->assertSame($conteos, [Usuario::withTrashed()->count(), Repartidor::withTrashed()->count(), Pedido::count(), PedidoAsignacion::count()]);
        $this->assertSame(3, PedidoAsignacion::count());
    }

    #[TestDox('repartidor-E10, datos de prueba: fuera de local, ni en el entorno de prueba ni en producción, no se siembra el repartidor de prueba ni sus pedidos')]
    public function test_fuera_de_local_no_se_siembra(): void
    {
        foreach (['testing', 'staging', 'production'] as $entorno) {
            $this->app->detectEnvironment(fn (): string => $entorno);

            // Aunque alguien fuerce el sembrado.
            $this->artisan('db:seed', ['--class' => RepartidorDeDesarrolloSeeder::class, '--force' => true])->assertSuccessful();

            $this->assertSame(0, Repartidor::withTrashed()->count(), $entorno);
            $this->assertSame(0, Usuario::query()->where('correo', RepartidorDeDesarrolloSeeder::CORREO)->count(), $entorno);
            $this->assertSame(0, Pedido::count(), $entorno);
        }
    }

    private function enLocal(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->assertTrue($this->app->environment('local'));
    }
}
