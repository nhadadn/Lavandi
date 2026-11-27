<?php

namespace Tests\Feature\Plazas;

use App\Models\Plaza;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La ficha de la plaza: nombre, zona horaria y estado. La zona horaria es obligatoria porque las ventanas de
 * recolección son de una hora local y la República tiene más de un huso (NSV-2026-001-v8.pdf:4:Cláusula I y
 * NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7). Los instantes se guardan como absolutos y se presentan en
 * la hora de la plaza (vault/arquitectura/modelo-multi-plaza.md).
 *
 * Escenarios: plazas-E01 y plazas-E02 · Spec: openspec/changes/plazas-y-zonas/specs/plazas/spec.md
 */
class FichaDePlazaTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    /**
     * Una zona horaria que no es la de ningún municipio de Durango ni de Coahuila: Quintana Roo, a cinco horas de UTC
     * todo el año.
     */
    private const OTRO_HUSO = 'America/Cancun';

    /** SQLSTATE de PostgreSQL cuando una columna no nula queda vacía. */
    private const VALOR_VACIO = '23502';

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[TestDox('plazas-E01: la plaza guarda su zona horaria')]
    public function test_plazas_e01_la_plaza_guarda_su_zona_horaria(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1', zonaHoraria: self::OTRO_HUSO);

        $this->assertSame(self::OTRO_HUSO, $plaza->fresh()->zona_horaria);
    }

    #[TestDox('plazas-E01: el instante queda guardado como absoluto y se presenta en la hora de la plaza')]
    public function test_plazas_e01_el_instante_queda_guardado_como_absoluto_y_se_presenta_en_la_hora_de_la_plaza(): void
    {
        $instante = CarbonImmutable::parse('2026-12-01 18:00:00', 'UTC');
        CarbonImmutable::setTestNow($instante);

        $plaza = $this->plazaDePrueba('Plaza de prueba 1', zonaHoraria: self::OTRO_HUSO);

        // Guardado como absoluto: la base da el mismo instante aunque la sesión esté en otro huso.
        DB::statement("set local timezone to 'Asia/Tokyo'");
        $this->assertSame(
            $instante->getTimestamp(),
            (int) DB::scalar('select extract(epoch from created_at) from plazas where id_plazas = ?', [$plaza->getKey()]),
        );
        $this->assertTrue($plaza->fresh()->created_at->eq($instante));

        // Presentado en la hora de la plaza: las 18:00 de UTC son las 13:00 en Cancún.
        $local = $plaza->horaLocal($plaza->fresh()->created_at);

        $this->assertSame('2026-12-01 13:00:00 -05:00', $local->format('Y-m-d H:i:s P'));
        $this->assertSame(self::OTRO_HUSO, $local->getTimezone()->getName());
        $this->assertTrue($local->eq($instante), 'Presentarlo en otra hora no cambia el instante.');
    }

    #[TestDox('plazas-E01: el mismo instante se presenta en la hora de cada plaza')]
    public function test_plazas_e01_el_mismo_instante_se_presenta_en_la_hora_de_cada_plaza(): void
    {
        $instante = CarbonImmutable::parse('2026-12-01 18:00:00', 'UTC');

        $enCancun = $this->plazaDePrueba('Plaza de prueba 1', zonaHoraria: self::OTRO_HUSO);
        $enTijuana = $this->plazaDePrueba('Plaza de prueba 2', zonaHoraria: 'America/Tijuana');

        $this->assertSame('13:00', $enCancun->horaLocal($instante)->format('H:i'));
        $this->assertSame('10:00', $enTijuana->horaLocal($instante)->format('H:i'));
    }

    #[TestDox('plazas-E02: sin zona horaria, o con una que no existe, no hay plaza')]
    public function test_plazas_e02_sin_zona_horaria_o_con_una_que_no_existe_no_hay_plaza(): void
    {
        $fichas = [
            'sin zona horaria' => ['nombre' => 'Plaza de prueba 1', 'activa' => true],
            'zona horaria vacía' => ['nombre' => 'Plaza de prueba 2', 'zona_horaria' => '', 'activa' => true],
            'zona horaria que no existe' => [
                'nombre' => 'Plaza de prueba 3',
                'zona_horaria' => 'America/Ciudad_Inventada',
                'activa' => true,
            ],
            // Una abreviatura o un desfase no son nombres de la base de husos IANA.
            'abreviatura' => ['nombre' => 'Plaza de prueba 4', 'zona_horaria' => 'CST', 'activa' => true],
            'desfase' => ['nombre' => 'Plaza de prueba 5', 'zona_horaria' => '-06:00', 'activa' => true],
        ];

        foreach ($fichas as $caso => $ficha) {
            try {
                Plaza::create($ficha);
                $this->fail("Debió rechazarse la plaza con {$caso}.");
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('zona_horaria', $error->errors(), $caso);
            }
        }

        $this->assertSame(0, Plaza::count());
    }

    #[TestDox('plazas-E02: una plaza guardada no puede cambiar a una zona horaria que no existe')]
    public function test_plazas_e02_una_plaza_guardada_no_puede_cambiar_a_una_zona_horaria_que_no_existe(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1', zonaHoraria: self::OTRO_HUSO);

        $this->expectException(ValidationException::class);

        try {
            $plaza->update(['zona_horaria' => 'America/Ciudad_Inventada']);
        } finally {
            $this->assertSame(self::OTRO_HUSO, $plaza->fresh()->zona_horaria);
        }
    }

    #[TestDox('plazas-E01: la ficha guarda su estado, activa o no, y sin estado la base la rechaza')]
    public function test_plazas_e01_la_ficha_guarda_su_estado_activa_o_no_y_sin_estado_la_base_la_rechaza(): void
    {
        $activa = $this->plazaDePrueba('Plaza de prueba 1', activa: true);
        $inactiva = $this->plazaDePrueba('Plaza de prueba 2', activa: false);

        $this->assertTrue($activa->fresh()->activa);
        $this->assertFalse($inactiva->fresh()->activa);

        try {
            DB::transaction(fn () => Plaza::create(['nombre' => 'Plaza de prueba 3', 'zona_horaria' => self::OTRO_HUSO]));
            $this->fail('Debió rechazarse la plaza sin estado.');
        } catch (QueryException $error) {
            $this->assertSame(self::VALOR_VACIO, $error->getCode(), $error->getMessage());
        }

        $this->assertSame(2, Plaza::count());
    }
}
