<?php

namespace Tests\Feature\AltaLavanderias;

use App\Models\Lavanderia;
use App\Models\LavanderiaHorario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\AltaDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las ventanas de disponibilidad: para cada día de la semana, las ventanas de una hora en que la lavandería tiene
 * disponibilidad; una que no dura exactamente una hora se rechaza (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo
 * B #6; :16:Módulo A #7, "ventanas de 1 hora"). Se guardan en lavanderia_horarios, una fila por ventana
 * (openspec/changes/alta-de-lavanderias/design.md, "Horario y ventanas en una sola tabla").
 *
 * Escenarios: alta-lavanderias-E21 y alta-lavanderias-E22 · Tarea 2.2 · Spec:
 * openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class VentanasTest extends TestCase
{
    use AltaDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const JUEVES = 4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararAlta();
    }

    #[TestDox('alta-lavanderias-E21: la lavandería marca para los jueves las ventanas de 10:00 a 11:00 y de 11:00 a 12:00, y el servidor guarda esas dos')]
    public function test_alta_lavanderias_e21_la_lavanderia_marca_dos_ventanas_para_los_jueves(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);

        $this->editar($cuenta, $lavanderia->id_publico, ['ventanas' => [
            ['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
            ['dia_semana' => self::JUEVES, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
            ['dia_semana' => self::JUEVES, 'hora_inicio' => '11:00', 'hora_fin' => '12:00'],
        ]])->assertOk()->assertJsonPath('lavanderia.ventanas', [
            ['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
            ['dia_semana' => self::JUEVES, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
            ['dia_semana' => self::JUEVES, 'hora_inicio' => '11:00', 'hora_fin' => '12:00'],
        ]);

        $this->assertSame(
            [['10:00:00', '11:00:00'], ['11:00:00', '12:00:00']],
            $this->ventanasDe($lavanderia, self::JUEVES),
        );
        // Llevan la plaza de su lavandería.
        $this->assertSame([$this->plaza->getKey()], DB::table('lavanderia_horarios')->distinct()->pluck('id_plazas')->all());
    }

    #[TestDox('alta-lavanderias-E21: la última ventana del día puede terminar a las 24:00')]
    public function test_alta_lavanderias_e21_la_ultima_ventana_del_dia_puede_terminar_a_las_24(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);

        $this->editar($cuenta, $lavanderia->id_publico, ['ventanas' => [
            ['dia_semana' => 7, 'hora_inicio' => '23:00', 'hora_fin' => '24:00'],
        ]])->assertOk()->assertJsonPath('lavanderia.ventanas', [
            ['dia_semana' => 7, 'hora_inicio' => '23:00', 'hora_fin' => '24:00'],
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ventanasQueNoDuranUnaHora(): array
    {
        return [
            'de 10:00 a 11:30' => ['10:00', '11:30'],
            'de media hora' => ['10:00', '10:30'],
            'de dos horas' => ['10:00', '12:00'],
            'que termina antes de empezar' => ['11:00', '10:00'],
            'que termina cuando empieza' => ['10:00', '10:00'],
        ];
    }

    #[DataProvider('ventanasQueNoDuranUnaHora')]
    #[TestDox('alta-lavanderias-E22: una ventana $inicio-$fin se rechaza y las ventanas guardadas no cambian')]
    public function test_alta_lavanderias_e22_una_ventana_que_no_dura_una_hora_se_rechaza(string $inicio, string $fin): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $antes = $this->todasLasVentanas($lavanderia);

        $respuesta = $this->editar($cuenta, $lavanderia->id_publico, ['ventanas' => [
            ['dia_semana' => self::JUEVES, 'hora_inicio' => '09:00', 'hora_fin' => '10:00'],
            ['dia_semana' => self::JUEVES, 'hora_inicio' => $inicio, 'hora_fin' => $fin],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['ventanas.1']);

        $this->assertSame('Cada ventana dura exactamente una hora.', $respuesta->json('errors')['ventanas.1'][0]);
        $this->assertArrayNotHasKey('ventanas.0', $respuesta->json('errors'));
        $this->assertSame($antes, $this->todasLasVentanas($lavanderia));
    }

    #[TestDox('alta-lavanderias-E22: el registro también rechaza una ventana que no dura una hora, y no guarda nada')]
    public function test_alta_lavanderias_e22_el_registro_tambien_rechaza_una_ventana_que_no_dura_una_hora(): void
    {
        $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto(['ventanas' => [
            ['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '11:30'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['ventanas.0']);

        $this->assertSame(0, Lavanderia::count());
        $this->assertSame(0, LavanderiaHorario::count());
        $this->assertSame([], $this->archivosDeFotos());
    }

    /**
     * @return list<array{string, string}>
     */
    private function ventanasDe(Lavanderia $lavanderia, int $dia): array
    {
        return $lavanderia->horarios()->where('dia_semana', $dia)->orderBy('hora_inicio')->get()
            ->map(fn (LavanderiaHorario $ventana): array => [$ventana->hora_inicio, $ventana->hora_fin])->all();
    }

    /**
     * @return list<array{int, string, string}>
     */
    private function todasLasVentanas(Lavanderia $lavanderia): array
    {
        return $lavanderia->horarios()->orderBy('dia_semana')->orderBy('hora_inicio')->get()
            ->map(fn (LavanderiaHorario $ventana): array => [$ventana->dia_semana, $ventana->hora_inicio, $ventana->hora_fin])->all();
    }
}
