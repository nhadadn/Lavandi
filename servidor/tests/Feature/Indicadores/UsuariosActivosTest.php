<?php

namespace Tests\Feature\Indicadores;

use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Indicadores\UsuariosActivosGrafica;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\TestCase;

/**
 * Los usuarios activos por día, semana y mes, en una línea de tendencia: cuántas personas distintas usaron la
 * aplicación en cada periodo (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Usuarios;
 * NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5, usuarios activos). Una persona que la usó varios días cuenta una sola
 * vez en su semana y en su mes.
 *
 * Se leen de actividad_usuarios, una fila por persona y día. Qué la escribe, y por eso quién cuenta, es DD-111
 * (indicadores-E07, bloqueado): aquí la prueba siembra sus filas, y el indicador cuenta todas las que haya.
 *
 * Escenarios: indicadores-E06 · Spec: openspec/changes/indicadores/specs/indicadores/spec.md · Tarea 2.1
 */
class UsuariosActivosTest extends TestCase
{
    use IndicadoresDePrueba;
    use PanelDePrueba;
    use RefreshDatabase;

    /** El lunes de la semana de prueba; el jueves es el 3 de diciembre y el domingo, el 6. */
    private const LUNES = '2026-11-30';

    private const DOMINGO = '2026-12-06';

    #[TestDox('indicadores-E06: con un usuario activo el lunes, el martes y el jueves, y otro solo el martes, el lunes da 1, el martes 2 y el jueves 1')]
    public function test_indicadores_e06_por_dia_el_lunes_da_1_el_martes_2_y_el_jueves_1(): void
    {
        $this->sembrarElEscenario();
        $this->comoAdministracion();

        $datos = $this->datosDeLaGrafica(
            $this->widget(UsuariosActivosGrafica::class, $this->filtros(self::LUNES, self::DOMINGO)),
        );

        $this->assertSame([1, 2, 0, 1, 0, 0, 0], $datos['datasets'][0]['data']);
        $this->assertCount(7, $datos['labels']);
        $this->assertStringContainsString('30/11/2026', $datos['labels'][0]);
    }

    #[TestDox('indicadores-E06: la semana da 2 personas, no 4 días de actividad')]
    public function test_indicadores_e06_la_semana_da_2_no_4(): void
    {
        $this->sembrarElEscenario();
        $this->comoAdministracion();

        $datos = $this->datosDeLaGrafica(
            $this->widget(UsuariosActivosGrafica::class, $this->filtros(self::LUNES, self::DOMINGO, 'semana')),
        );

        $this->assertSame([2], $datos['datasets'][0]['data']);
        $this->assertSame(['Semana del 30/11/2026'], $datos['labels']);
    }

    #[TestDox('indicadores-E06: por mes, cada persona cuenta una vez en su mes: noviembre da 1 y diciembre 2')]
    public function test_indicadores_e06_por_mes_cada_persona_cuenta_una_vez_en_su_mes(): void
    {
        $this->sembrarElEscenario();
        $this->comoAdministracion();

        $datos = $this->datosDeLaGrafica(
            $this->widget(UsuariosActivosGrafica::class, $this->filtros('2026-11-01', '2026-12-31', 'mes')),
        );

        $this->assertSame([1, 2], $datos['datasets'][0]['data']);
        $this->assertSame(['noviembre 2026', 'diciembre 2026'], $datos['labels']);
    }

    #[TestDox('indicadores-E06: es una línea de tendencia en la página de indicadores, con sus cifras también en texto')]
    public function test_indicadores_e06_es_una_linea_en_la_pagina_con_sus_cifras_en_texto(): void
    {
        $this->sembrarElEscenario();
        $this->comoAdministracion();

        $grafica = $this->widget(UsuariosActivosGrafica::class, $this->filtros(self::LUNES, self::DOMINGO, 'semana'))
            ->assertSee('Usuarios activos')
            ->assertSee('Semana del 30/11/2026: 2');

        $this->assertSame('line', (fn (): string => $this->getType())->call($grafica->instance()));
        $this->assertContains(UsuariosActivosGrafica::class, (new Indicadores)->getWidgets());
    }

    /**
     * Un usuario con actividad el lunes, el martes y el jueves de la semana de prueba, y otro solo el martes.
     */
    private function sembrarElEscenario(): void
    {
        $uno = Usuario::factory()->conRol(Rol::USUARIO)->create();
        $otro = Usuario::factory()->conRol(Rol::USUARIO)->create();

        foreach (['2026-11-30', '2026-12-01', '2026-12-03'] as $dia) {
            DB::table('actividad_usuarios')->insert(['id_usuarios' => $uno->getKey(), 'fecha' => $dia]);
        }

        DB::table('actividad_usuarios')->insert(['id_usuarios' => $otro->getKey(), 'fecha' => '2026-12-01']);
    }
}
