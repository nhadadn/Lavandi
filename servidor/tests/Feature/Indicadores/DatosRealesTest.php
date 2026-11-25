<?php

namespace Tests\Feature\Indicadores;

use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Indicadores\PedidosPorEstadoGrafica;
use App\Filament\Widgets\Indicadores\PedidosTotalesGrafica;
use App\Filament\Widgets\Indicadores\PedidosTotalesTabla;
use App\Filament\Widgets\Indicadores\RankingDeLavanderiasTabla;
use App\Filament\Widgets\Indicadores\RetencionTabla;
use App\Filament\Widgets\Indicadores\UsuariosActivosGrafica;
use App\Models\Calificacion;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Finder\Finder;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El panel calcula cada indicador con los pedidos y los usuarios de la base, sin exportar ni procesar nada a mano, y su
 * código no trae cifras de ejemplo (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5; NSV-2026-001-v8.pdf:21:ANEXO A,
 * Sección A.4.3, "muestra datos reales de pedidos y usuarios activos"; A.4.4).
 *
 * Cubre los indicadores que ya están en la página. Acreditar A43-7 en el entorno de prueba o en producción necesita
 * lavanderías reales (DD-021): esta prueba no lo espera.
 *
 * Escenarios: indicadores-E23 · Spec: openspec/changes/indicadores/specs/indicadores/spec.md · Tarea 6.3
 */
class DatosRealesTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /**
     * Los archivos del panel de indicadores: la página, sus widgets y sus consultas, con los de los reportes financieros,
     * de donde salen los ingresos brutos (indicadores-E16).
     */
    private const CODIGO_DEL_PANEL = ['Filament/Pages', 'Filament/Widgets/Indicadores', 'Indicadores', 'Filament/Widgets/Finanzas', 'Finanzas'];

    /** Diciembre de 2026, por mes: el mes del jueves de prueba. */
    private const FILTROS = ['desde' => '2026-12-01', 'hasta' => '2026-12-31', 'granularidad' => 'mes'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('indicadores-E23: con una base sembrada por el test, cada indicador de la página da las cifras que salen de esa base')]
    public function test_indicadores_e23_cada_indicador_da_las_cifras_de_la_base(): void
    {
        $this->sembrarLaBase();
        $this->comoAdministracion();

        // Lo que dice la base, contado aquí sin pasar por los indicadores.
        $confirmados = Pedido::query()->whereNotNull('confirmado_en')->count();
        $entregados = Pedido::query()->whereNotNull('confirmado_en')
            ->whereHas('estado', fn ($estado) => $estado->where('codigo', EstadoPedido::ENTREGADO))->count();
        $personas = DB::table('actividad_usuarios')->distinct()->count('id_usuarios');
        $promedio = round((float) Calificacion::query()->where('id_lavanderias', $this->lavanderia->getKey())->avg('estrellas'), 1);
        $this->assertSame([3, 1, 2, 4.5], [$confirmados, $entregados, $personas, $promedio]);

        $this->assertSame([$confirmados], $this->serie(PedidosTotalesGrafica::class));
        $this->assertSame([$confirmados - $entregados, $entregados], $this->serie(PedidosPorEstadoGrafica::class));
        $this->assertSame([$personas], $this->serie(UsuariosActivosGrafica::class));

        $this->widget(PedidosTotalesTabla::class, self::FILTROS)
            ->assertTableColumnStateSet('pedidos', $confirmados, '2026-12-01');
        // Las dos cuentas que pidieron se registraron en diciembre, y una de ellas volvió a pedir.
        $this->widget(RetencionTabla::class, self::FILTROS)
            ->assertTableColumnStateSet('con_primer_pedido', 2, '2026-12-01')
            ->assertTableColumnStateSet('porcentaje', 50.0, '2026-12-01');
        $this->widget(RankingDeLavanderiasTabla::class, self::FILTROS)
            ->assertTableColumnStateSet('promedio', $promedio, $this->lavanderia->id_publico);
    }

    #[TestDox('indicadores-E23: la administración abre el panel con los filtros en la dirección y ve las cifras de la base, sin cargas diferidas')]
    public function test_indicadores_e23_al_abrir_el_panel_se_ven_las_cifras_de_la_base(): void
    {
        $this->sembrarLaBase();
        $this->comoAdministracion();

        $pagina = $this->get('/admin/indicadores?'.http_build_query(['filters' => self::FILTROS]))->assertOk();

        foreach ((new Indicadores)->getWidgets() as $widget) {
            $pagina->assertSeeLivewire($widget);
        }

        $pagina->assertSee('Del 01/12/2026 al 31/12/2026, por mes')
            ->assertSee('Activos: 2. Completados: 1.')
            ->assertSee('diciembre 2026: 2.')
            ->assertSee('50 %')
            ->assertSeeInOrder([$this->lavanderia->nombre, '4.5']);
    }

    #[TestDox('indicadores-E23: con la base sin pedidos, sin actividad y sin calificaciones, ningún indicador da cifras')]
    public function test_indicadores_e23_con_la_base_vacia_ningun_indicador_da_cifras(): void
    {
        $this->comoAdministracion();
        $filtros = ['desde' => '2026-11-30', 'hasta' => '2026-12-06', 'granularidad' => 'dia'];

        $this->assertSame([0, 0, 0, 0, 0, 0, 0], $this->serie(PedidosTotalesGrafica::class, $filtros));
        $this->assertSame([0, 0], $this->serie(PedidosPorEstadoGrafica::class, $filtros));
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], $this->serie(UsuariosActivosGrafica::class, $filtros));

        $this->widget(PedidosTotalesTabla::class, $filtros)
            ->assertTableColumnStateSet('pedidos', 0, '2026-11-30')
            ->assertTableColumnStateSet('pedidos_anterior', 0, '2026-11-30');
        $this->widget(RetencionTabla::class, $filtros)
            ->assertTableColumnStateSet('con_primer_pedido', 0, '2026-11-01')
            ->assertTableColumnStateSet('porcentaje', null, '2026-12-01');
        $this->widget(RankingDeLavanderiasTabla::class, $filtros)
            ->assertTableColumnStateSet('promedio', null, $this->lavanderia->id_publico)
            ->assertTableColumnStateSet('lugar', null, $this->lavanderia->id_publico);
    }

    #[TestDox('indicadores-E23: el código del panel no trae cifras de ejemplo ni datos inventados')]
    public function test_indicadores_e23_el_codigo_del_panel_no_trae_cifras_de_ejemplo(): void
    {
        $archivos = Finder::create()->files()->name('*.php')->in(array_map(
            fn (string $carpeta): string => app_path($carpeta),
            self::CODIGO_DEL_PANEL,
        ));

        $this->assertNotEmpty(iterator_to_array($archivos));

        foreach ($archivos as $archivo) {
            $codigo = $archivo->getContents();
            $ruta = $archivo->getRelativePathname();

            // Una lista de dos o más números, como [3, 0, 2] o [4.8, 4.2].
            $this->assertDoesNotMatchRegularExpression('/\[\s*-?\d+(\.\d+)?\s*(,\s*-?\d+(\.\d+)?\s*)+,?\s*\]/', $codigo, $ruta);
            // Datos al azar o de prueba.
            $this->assertDoesNotMatchRegularExpression('/\b(fake|faker|rand|mt_rand|random_int|array_rand)\s*\(/i', $codigo, $ruta);
        }
    }

    /**
     * Dos cuentas de usuario registradas el jueves de prueba: la primera hizo dos pedidos, uno entregado y otro activo, y
     * la segunda uno activo; las dos usaron la aplicación ese día y calificaron a la lavandería con 4 y 5 estrellas.
     */
    private function sembrarLaBase(): void
    {
        $this->elJuevesALas('08:00:00');
        $una = $this->cuentaDeUsuario();
        $otra = $this->cuentaDeUsuario();

        $entregado = $this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 08:10:00', $una);
        $this->aceptarComoLaLavanderia($entregado);
        $this->llevarHastaEntregado($entregado);
        $this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 08:20:00', $una);
        $this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 08:30:00', $otra);

        foreach ([[$una, 4], [$otra, 5]] as [$cuenta, $estrellas]) {
            DB::table('actividad_usuarios')->insert(['id_usuarios' => $cuenta->getKey(), 'fecha' => self::JUEVES_DE_PRUEBA]);
            Calificacion::create([
                'id_usuarios' => $cuenta->getKey(),
                'id_lavanderias' => $this->lavanderia->getKey(),
                'estrellas' => $estrellas,
            ]);
        }
    }

    /**
     * La única serie de la gráfica, con los filtros dados o los de diciembre por mes.
     *
     * @param  class-string  $grafica
     * @param  array<string, string>|null  $filtros
     * @return list<int>
     */
    private function serie(string $grafica, ?array $filtros = null): array
    {
        return $this->datosDeLaGrafica($this->widget($grafica, $filtros ?? self::FILTROS))['datasets'][0]['data'];
    }
}
