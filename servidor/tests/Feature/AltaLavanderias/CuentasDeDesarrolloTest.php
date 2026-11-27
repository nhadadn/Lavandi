<?php

namespace Tests\Feature\AltaLavanderias;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Database\Seeders\CuentasDeDesarrolloSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Las cuentas de desarrollo para entrar a mano al panel de lavanderías y a la app del socio (design.md, "Cómo se entra
 * al panel en local"). Solo se siembran en local.
 *
 * Escenario: alta-lavanderias-E13, para probarlo a mano · Tarea 3.1
 */
class CuentasDeDesarrolloTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('alta-lavanderias-E13: en local, el sembrado deja admin@ejemplo.com, que entra al panel, y una cuenta de socio sin lavandería')]
    public function test_alta_lavanderias_e13_en_local_el_sembrado_deja_las_cuentas_de_desarrollo(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->seed(CuentasDeDesarrolloSeeder::class);
        $this->seed(CuentasDeDesarrolloSeeder::class);

        $administracion = Usuario::query()->where('correo', 'admin@ejemplo.com')->sole();
        $this->assertSame([Rol::ADMINISTRACION], $administracion->roles->pluck('codigo')->all());
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $administracion->password));
        $this->assertNotSame(UsuarioFactory::CONTRASENA, $administracion->getRawOriginal('password'));
        $this->assertTrue($administracion->canAccessPanel(Filament::getPanel('admin')));

        $socio = Usuario::query()->where('correo', 'lavanderia@ejemplo.com')->sole();
        $this->assertSame([Rol::LAVANDERIA], $socio->roles->pluck('codigo')->all());
        $this->assertFalse($socio->canAccessPanel(Filament::getPanel('admin')));

        $this->assertSame(2, Usuario::count());
    }

    #[TestDox('alta-lavanderias-E13: fuera de local, ni en el entorno de prueba ni en producción, el sembrado no deja cuentas de desarrollo')]
    public function test_alta_lavanderias_e13_fuera_de_local_no_se_siembran(): void
    {
        foreach (['testing', 'staging', 'production'] as $entorno) {
            $this->app->detectEnvironment(fn (): string => $entorno);

            // Aunque alguien fuerce el sembrado.
            $this->artisan('db:seed', ['--class' => CuentasDeDesarrolloSeeder::class, '--force' => true])->assertSuccessful();

            $this->assertSame(0, Usuario::count(), $entorno);
        }
    }
}
