<?php

namespace Tests\Feature\Exportacion;

use App\Exportacion\Exportacion;
use App\Filament\Pages\Indicadores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Exportacion\Soporte\DescargasDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Cada descarga sale para el rango de fechas que elige la administración, con las dos fechas incluidas y sin tope de
 * duración (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, "para cualquier rango de fechas"; design.md, lectura 3). Un
 * rango al revés se rechaza con un mensaje.
 *
 * Por día, el archivo trae exactamente los días del rango. Por semana o por mes, el rango se ajusta a periodos
 * completos, como en el panel (App\Indicadores\Rango), para que el archivo y el panel nunca difieran.
 *
 * Escenarios: exportacion-E04, exportacion-E05 y exportacion-E06 · Spec: openspec/changes/exportacion/specs/
 * exportacion/spec.md · Tarea 2.1
 */
class RangoDeFechasTest extends TestCase
{
    use AgendaDePrueba;
    use DescargasDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('exportacion-E04: con pedidos de cada día del 1 al 31 de marzo, la descarga del 10 al 20 de marzo trae los datos del 10 al 20, incluidos los dos días, y ninguno de fuera')]
    public function test_exportacion_e04_el_rango_incluye_sus_dos_extremos_y_nada_de_fuera(): void
    {
        for ($dia = 1; $dia <= 31; $dia++) {
            $this->pedidoConfirmadoEl(sprintf('2027-03-%02d 10:00:00', $dia));
        }
        $this->comoAdministracion();

        $this->pedirDescarga('descargarPedidosTotales', ['desde' => '2027-03-10', 'hasta' => '2027-03-20', 'granularidad' => 'dia'])
            ->assertHasNoActionErrors();

        $filas = array_slice($this->filasDelCsv($this->ultimaExportacion()), 1);
        $dias = array_map(fn (array $fila): string => substr($fila[0], -10), $filas);

        $this->assertSame(array_map(fn (int $dia): string => sprintf('%02d/03/2027', $dia), range(10, 20)), $dias);
        $this->assertSame(array_fill(0, 11, '1'), array_column($filas, 1));
        $this->assertNotContains('09/03/2027', $dias);
        $this->assertNotContains('21/03/2027', $dias);
    }

    #[TestDox('exportacion-E05: con pedidos de dos años, el panel acepta el rango completo y genera el archivo, por día y por mes')]
    public function test_exportacion_e05_un_rango_de_dos_anos_se_acepta_y_genera_el_archivo(): void
    {
        foreach (['2026-12-03 10:00:00', '2027-06-15 10:00:00', '2028-11-29 10:00:00'] as $fechaYHora) {
            $this->pedidoConfirmadoEl($fechaYHora);
        }
        $this->comoAdministracion();
        $rango = ['desde' => '2026-12-01', 'hasta' => '2028-11-30'];

        $this->pedirDescarga('descargarPedidosTotales', [...$rango, 'granularidad' => 'dia'])->assertHasNoActionErrors();
        $porDia = $this->ultimaExportacion();
        $filas = array_slice($this->filasDelCsv($porDia), 1);
        $this->assertCount(731, $filas);
        $this->assertSame(731, $porDia->successful_rows);
        $this->assertSame(3, array_sum(array_map('intval', array_column($filas, 1))));
        $this->assertSame('pedidos-totales-2026-12-01-al-2028-11-30', $porDia->file_name);
        $this->assertCount(732, $this->valoresDelXlsx($porDia));

        $this->pedirDescarga('descargarPedidosTotales', [...$rango, 'granularidad' => 'mes'])->assertHasNoActionErrors();
        $filas = array_slice($this->filasDelCsv($this->ultimaExportacion()), 1);
        $this->assertCount(24, $filas);
        $this->assertSame(['diciembre 2026', 'junio 2027', 'noviembre 2028'], array_column(array_values(array_filter($filas, fn (array $fila): bool => $fila[1] === '1')), 0));
    }

    #[TestDox('exportacion-E06: con la fecha de inicio posterior a la de fin, el panel rechaza la descarga con un mensaje y no genera nada')]
    public function test_exportacion_e06_un_rango_al_reves_se_rechaza_con_un_mensaje(): void
    {
        $this->comoAdministracion();

        $formulario = $this->pedirDescarga('descargarPedidosTotales', ['desde' => '2027-03-20', 'hasta' => '2027-03-10', 'granularidad' => 'dia'])
            ->assertHasActionErrors(['hasta' => 'after_or_equal']);
        $this->assertContains('La fecha de fin no puede ser anterior a la de inicio.', $formulario->errors()->all());

        // Sin las dos fechas, tampoco.
        Livewire::test(Indicadores::class)
            ->callAction('descargarPedidosTotales', data: ['desde' => null, 'hasta' => null, 'granularidad' => 'dia'])
            ->assertHasActionErrors(['desde' => 'required', 'hasta' => 'required']);

        $this->assertSame(0, Exportacion::count());

        // El mismo día en las dos fechas es un rango de un día.
        $this->pedirDescarga('descargarPedidosTotales', ['desde' => '2027-03-10', 'hasta' => '2027-03-10', 'granularidad' => 'dia'])
            ->assertHasNoActionErrors();
        $this->assertCount(2, $this->filasDelCsv($this->ultimaExportacion()));
    }
}
