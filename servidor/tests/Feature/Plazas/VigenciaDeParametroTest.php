<?php

namespace Tests\Feature\Plazas;

use App\Models\ParametroPlaza;
use App\Models\Plaza;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Una vigencia termina después de empezar: la base rechaza un parámetro de la plaza cuyo fin de vigencia es anterior a
 * su inicio, o el mismo instante (DD-134, punto 5; C-234). Impedir que dos vigencias de la misma clave se traslapen
 * espera al primer cambio que agregue un parámetro.
 *
 * Escenario: plazas-E15 · Spec: openspec/changes/plazas-y-zonas/specs/plazas/spec.md
 */
class VigenciaDeParametroTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    private const DESDE = '2026-12-01 00:00:00+00';

    #[TestDox('plazas-E15: la base rechaza un parámetro cuya vigencia termina antes de empezar, o en el mismo instante')]
    public function test_plazas_e15_la_base_rechaza_un_parametro_cuya_vigencia_termina_antes_de_empezar_o_en_el_mismo_instante(): void
    {
        $plaza = $this->plazaDePrueba();

        // Un segundo antes del inicio, un día antes y en el mismo instante.
        foreach (['2026-11-30 23:59:59+00', '2026-11-30 00:00:00+00', self::DESDE] as $hasta) {
            $this->assertRechazado(fn () => $this->guardarParametro($plaza, self::DESDE, $hasta), $hasta);
        }

        $this->assertSame(0, ParametroPlaza::count());
    }

    #[TestDox('plazas-E15: un parámetro sin fin de vigencia, o con fin posterior al inicio, se guarda')]
    public function test_plazas_e15_un_parametro_sin_fin_de_vigencia_o_con_fin_posterior_al_inicio_se_guarda(): void
    {
        $plaza = $this->plazaDePrueba();

        $sinFin = $this->guardarParametro($plaza, self::DESDE, null);
        // Un segundo después del inicio ya es una vigencia.
        $conFin = $this->guardarParametro($plaza, '2027-01-01 00:00:00+00', '2027-01-01 00:00:01+00');

        $this->assertNull($sinFin->fresh()->vigente_hasta);
        $this->assertTrue($conFin->fresh()->vigente_hasta->eq('2027-01-01 00:00:01+00'));
        $this->assertSame(2, $plaza->parametros()->count());
    }

    private function guardarParametro(Plaza $plaza, string $desde, ?string $hasta): ParametroPlaza
    {
        return $plaza->parametros()->create([
            'clave' => 'clave_de_prueba',
            'valor' => ['monto' => '10.00'],
            'vigente_desde' => $desde,
            'vigente_hasta' => $hasta,
        ]);
    }

    /**
     * La base rechaza la operación por su CHECK. Corre en una transacción anidada, un savepoint, para que el error no
     * aborte la transacción de la prueba.
     */
    private function assertRechazado(callable $operacion, string $caso): void
    {
        try {
            DB::transaction($operacion);
            $this->fail("La base debió rechazar la vigencia ({$caso}).");
        } catch (QueryException $error) {
            $this->assertSame(self::CHECK_INCUMPLIDO, $error->getCode(), "{$caso}: {$error->getMessage()}");
            $this->assertStringContainsString('parametros_plaza_vigencia_check', $error->getMessage(), $caso);
        }
    }
}
