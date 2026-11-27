<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Validacion\RangoDeFechas;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as Validador;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * La regla del rango de fechas, fuera de Filament: la clase de dominio App\Validacion\RangoDeFechas, que usan el
 * formulario de la conciliación del panel y el de la descarga de los reportes, y que usará la API de la administración
 * (ADR-0022, decisión 9; openspec/changes/administracion-en-la-app/design.md, sección 3). Las dos fechas, y la de fin no
 * anterior a la de inicio, con su mensaje.
 *
 * Las pruebas del panel siguen sin cambios: tests/Feature/Administracion/ConciliacionTest.php (administracion-E20) y
 * tests/Feature/Exportacion/RangoDeFechasTest.php (exportacion-E06).
 *
 * Escenario: administracion-app-E11 · Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/
 * spec.md · Tareas 13.2 y 19.2, solo su regla del rango
 */
class ReglaDelRangoTest extends TestCase
{
    #[TestDox('administracion-app-E11: un rango con la fecha de fin posterior a la de inicio pasa la regla')]
    public function test_administracion_app_e11_un_rango_valido_pasa_la_regla(): void
    {
        $validador = $this->validar(['desde' => '2027-03-01', 'hasta' => '2027-03-31']);

        $this->assertTrue($validador->passes());
        $this->assertSame(['desde' => '2027-03-01', 'hasta' => '2027-03-31'], $validador->validated());
    }

    #[TestDox('administracion-app-E11: el mismo día en las dos fechas es un rango de un día y pasa la regla')]
    public function test_administracion_app_e11_el_mismo_dia_en_las_dos_fechas_pasa_la_regla(): void
    {
        $this->assertTrue($this->validar(['desde' => '2027-03-10', 'hasta' => '2027-03-10'])->passes());
    }

    #[TestDox('administracion-app-E11: con la fecha de fin anterior a la de inicio, la regla falla en la de fin con "La fecha de fin no puede ser anterior a la de inicio."')]
    public function test_administracion_app_e11_la_fecha_de_fin_anterior_a_la_de_inicio_falla_con_su_mensaje(): void
    {
        $validador = $this->validar(['desde' => '2027-03-31', 'hasta' => '2027-03-01']);

        $this->assertTrue($validador->fails());
        $this->assertSame(['hasta' => ['AfterOrEqual' => ['desde']]], $validador->failed());
        $this->assertSame(
            ['hasta' => ['La fecha de fin no puede ser anterior a la de inicio.']],
            $validador->errors()->toArray(),
        );
    }

    #[TestDox('administracion-app-E11: sin las dos fechas, o con las dos vacías, la regla falla en las dos porque son obligatorias')]
    public function test_administracion_app_e11_sin_las_dos_fechas_falla_en_las_dos(): void
    {
        foreach ([[], ['desde' => null, 'hasta' => null], ['desde' => '', 'hasta' => '']] as $datos) {
            $this->assertSame(['desde' => ['Required' => []], 'hasta' => ['Required' => []]], $this->fallas($datos));
        }

        // Con solo una de las dos, falla la que falta.
        $this->assertSame(['hasta' => ['Required' => []]], $this->fallas(['desde' => '2027-03-01']));
        $this->assertSame(['desde' => ['Required' => []]], $this->fallas(['hasta' => '2027-03-31']));
    }

    /**
     * El validador de Laravel con las reglas y los mensajes de la clase de dominio, como los usará la API.
     *
     * @param  array<string, mixed>  $datos
     */
    private function validar(array $datos): Validador
    {
        return Validator::make($datos, RangoDeFechas::reglas(), RangoDeFechas::mensajes());
    }

    /**
     * Las reglas que fallan para esos datos, por campo.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, array<string, list<string>>>
     */
    private function fallas(array $datos): array
    {
        $validador = $this->validar($datos);
        $this->assertTrue($validador->fails());

        return $validador->failed();
    }
}
