<?php

namespace Tests\Feature\Cercanas;

use App\Agenda\ParametrosDeAgenda;
use App\Models\Calificacion;
use App\Models\Lavanderia;
use App\Models\LavanderiaHorario;
use App\Models\LavanderiaServicio;
use App\Models\ParametroPlaza;
use App\Models\Plaza;
use App\Models\Usuario;
use App\Models\Zona;
use Database\Seeders\LavanderiasDePruebaSeeder;
use Database\Seeders\PlazasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * El sembrado de plazas, lavanderías, horarios y calificaciones de prueba del hito corre solo en local y en prueba, nunca
 * en producción (ADR-0012). Los horarios de prueba y la agenda de prueba de cada plaza son de agenda-de-recoleccion
 * (tareas 1.1 y 1.2).
 *
 * Escenario: cercanas-E22 · Spec: openspec/changes/lavanderias-cercanas/specs/cercanas/spec.md
 */
class DatosDePruebaTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('cercanas-E22: en producción, el sembrado no crea ninguna plaza, lavandería, calificación ni cuenta de prueba')]
    public function test_cercanas_e22_en_produccion_el_sembrado_no_crea_nada_de_prueba(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->assertTrue($this->app->isProduction());

        // Aunque alguien fuerce el sembrado en producción: el general y cada sembrador de prueba.
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => PlazasDePruebaSeeder::class, '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => LavanderiasDePruebaSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(0, Plaza::count());
        $this->assertSame(0, Zona::count());
        $this->assertSame(0, ParametroPlaza::count());
        $this->assertSame(0, Lavanderia::withTrashed()->count());
        $this->assertSame(0, LavanderiaServicio::count());
        $this->assertSame(0, LavanderiaHorario::count());
        $this->assertSame(0, Calificacion::count());
        $this->assertSame(0, Usuario::withTrashed()->count());
    }

    #[TestDox('cercanas-E22: en producción, aunque ya haya plazas que cubran sus puntos, no se siembra ninguna lavandería, calificación ni cuenta de prueba')]
    public function test_cercanas_e22_en_produccion_aunque_haya_plazas_no_se_siembran_lavanderias(): void
    {
        // Plazas que cubren los puntos de las lavanderías de prueba, cargadas antes de pasar a producción.
        $this->seed(PlazasDePruebaSeeder::class);
        $plazas = Plaza::count();
        $this->assertGreaterThan(0, $plazas);

        $this->app->detectEnvironment(fn (): string => 'production');
        $this->assertTrue($this->app->isProduction());

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => LavanderiasDePruebaSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame($plazas, Plaza::count());
        $this->assertSame(0, Lavanderia::withTrashed()->count());
        $this->assertSame(0, LavanderiaServicio::count());
        $this->assertSame(0, LavanderiaHorario::count());
        $this->assertSame(0, Calificacion::count());
        $this->assertSame(0, Usuario::withTrashed()->count());
    }

    #[TestDox('cercanas-E22: fuera de producción, el sembrado general trae las lavanderías de prueba, y sembrar dos veces no las duplica')]
    public function test_cercanas_e22_fuera_de_produccion_el_sembrado_trae_las_lavanderias_de_prueba_sin_duplicarlas(): void
    {
        $this->assertFalse($this->app->isProduction());

        $this->seed();
        $primera = $this->conteos();
        $lavanderias = Lavanderia::orderBy('nombre')->pluck('id_publico', 'nombre')->all();

        $this->seed();

        $this->assertSame($primera, $this->conteos());
        $this->assertSame($lavanderias, Lavanderia::orderBy('nombre')->pluck('id_publico', 'nombre')->all());
        $this->assertSame([
            'Lavandería de prueba 1',
            'Lavandería de prueba 2',
            'Lavandería de prueba 3',
            'Lavandería de prueba 4',
            'Lavandería de prueba Durango Centro',
            'Lavandería de prueba Durango Norte',
            'Lavandería de prueba Durango Sur',
            'Lavandería de prueba suspendida',
        ], array_keys($lavanderias));

        // Cada una con su cuenta de prueba, en el dominio reservado para pruebas, y con su horario de prueba: de lunes a
        // sábado, sin el domingo (agenda-E04).
        foreach (Lavanderia::with(['administrador.roles', 'horarios'])->get() as $lavanderia) {
            $this->assertStringEndsWith('@prueba.test', $lavanderia->administrador->correo);
            $this->assertSame(['lavanderia'], $lavanderia->administrador->roles->pluck('codigo')->all());
            $this->assertNull($lavanderia->administrador->password);
            $this->assertSame([1, 2, 3, 4, 5, 6], $lavanderia->horarios->pluck('dia_semana')->unique()->sort()->values()->all());
        }

        // Cada plaza de prueba, con las dos claves de su agenda de prueba (agenda-E12).
        foreach (Plaza::with('parametros')->get() as $plaza) {
            $this->assertEqualsCanonicalizing(
                [ParametrosDeAgenda::ANTICIPACION_MINIMA, ParametrosDeAgenda::DIAS_HACIA_ADELANTE],
                $plaza->parametros->pluck('clave')->all(),
                $plaza->nombre,
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function conteos(): array
    {
        return [
            'plazas' => Plaza::count(),
            'parametros' => ParametroPlaza::count(),
            'lavanderias' => Lavanderia::withTrashed()->count(),
            'servicios' => LavanderiaServicio::count(),
            'horarios' => LavanderiaHorario::count(),
            'calificaciones' => Calificacion::count(),
            'usuarios' => Usuario::withTrashed()->count(),
        ];
    }
}
