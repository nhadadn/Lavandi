<?php

namespace Tests\Feature\Agenda;

use App\Agenda\Agenda;
use App\Models\Lavanderia;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Seeders\LavanderiasDePruebaSeeder;
use Database\Seeders\PlazasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las ventanas de una hora de una lavandería para una fecha, dentro de su horario sembrado en lavanderia_horarios y en
 * la hora local de su plaza, por GET /api/lavanderias/{id_publico}/ventanas, con sesión y por el componente de
 * autorización (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7; :17:Módulo B #1 y #6; ADR-0004; ADR-0019).
 *
 * El reloj se fija antes de la fecha que se pide, y cada plaza lleva su agenda configurada con anticipación de cero y
 * siete días hacia adelante, para que todas las ventanas del horario se ofrezcan: lo que pasa con las que ya empezaron,
 * la anticipación y el horizonte es agenda-E11 y agenda-E12 (ValidarVentanaTest).
 *
 * Escenarios: agenda-E01, agenda-E02, agenda-E03 y agenda-E04 · Spec:
 * openspec/changes/agenda-de-recoleccion/specs/agenda/spec.md
 */
class VentanasTest extends TestCase
{
    use AgendaDePrueba;
    use LavanderiasDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Un jueves y el domingo siguiente. */
    private const JUEVES_DE_PRUEBA = '2026-12-03';

    private const DOMINGO_DE_PRUEBA = '2026-12-06';

    protected function setUp(): void
    {
        parent::setUp();

        // El martes anterior, a mediodía en UTC.
        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00', 'UTC'));
    }

    #[TestDox('agenda-E01: con horario de 9:00 a 13:00 el jueves, el servidor responde 9:00–10:00, 10:00–11:00, 11:00–12:00 y 12:00–13:00')]
    public function test_agenda_e01_con_horario_de_9_a_13_el_jueves_responde_las_cuatro_ventanas(): void
    {
        $lavanderia = $this->lavanderiaConHorario($this->plazaConAgenda(), [[self::JUEVES, '09:00', '13:00']]);

        $respuesta = $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk();

        $this->assertSame(['09:00–10:00', '10:00–11:00', '11:00–12:00', '12:00–13:00'], $this->horasDe($respuesta));
        $respuesta->assertJsonPath('fecha', self::JUEVES_DE_PRUEBA);
        $respuesta->assertJsonPath('dia_sin_servicio', false);

        foreach ($respuesta->json('ventanas') as $ventana) {
            $this->assertSame(self::JUEVES_DE_PRUEBA, $ventana['fecha']);
        }
    }

    #[TestDox('agenda-E01: ninguna ventana dura más ni menos de una hora, tampoco con dos franjas el mismo día')]
    public function test_agenda_e01_ninguna_ventana_dura_mas_ni_menos_de_una_hora(): void
    {
        $lavanderia = $this->lavanderiaConHorario($this->plazaConAgenda(), [
            [self::JUEVES, '16:00', '18:00'],
            [self::JUEVES, '09:00', '11:00'],
        ]);

        $respuesta = $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk();

        // De la más temprana a la más tarde, aunque las franjas se hayan guardado en otro orden.
        $this->assertSame(['09:00–10:00', '10:00–11:00', '16:00–17:00', '17:00–18:00'], $this->horasDe($respuesta));

        foreach ($respuesta->json('ventanas') as $ventana) {
            $duracion = CarbonImmutable::parse($ventana['inicio'])->diffInSeconds(CarbonImmutable::parse($ventana['fin']));
            $this->assertSame(3600.0, (float) $duracion, "{$ventana['inicio']} a {$ventana['fin']}");
        }
    }

    #[TestDox('agenda-E02: en una plaza con zona horaria distinta de UTC, la ventana de 9:00 a 10:00 sale en la hora de la plaza, con su desfase')]
    public function test_agenda_e02_la_ventana_sale_en_la_hora_de_la_plaza_con_su_desfase(): void
    {
        $plaza = $this->plazaConAgenda(zonaHoraria: 'America/Mexico_City');
        $lavanderia = $this->lavanderiaConHorario($plaza, [[self::JUEVES, '09:00', '10:00']]);

        $respuesta = $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk();

        $respuesta->assertJsonPath('zona_horaria', 'America/Mexico_City');
        $respuesta->assertJsonPath('ventanas', [[
            'inicio' => '2026-12-03T09:00:00-06:00',
            'fin' => '2026-12-03T10:00:00-06:00',
            'fecha' => '2026-12-03',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
        ]]);

        // El texto es un instante absoluto: las 15:00 en UTC.
        $this->assertTrue(CarbonImmutable::parse($respuesta->json('ventanas.0.inicio'))->eq('2026-12-03 15:00:00+00'));
        $this->assertTrue(CarbonImmutable::parse($respuesta->json('ventanas.0.fin'))->eq('2026-12-03 16:00:00+00'));
    }

    #[TestDox('agenda-E02: el mismo horario en plazas con otra zona horaria es otro instante absoluto')]
    public function test_agenda_e02_el_mismo_horario_en_otra_zona_horaria_es_otro_instante(): void
    {
        $centro = $this->lavanderiaConHorario(
            $this->plazaConAgenda('Plaza de prueba centro', zonaHoraria: 'America/Mexico_City'),
            [[self::JUEVES, '09:00', '10:00']],
        );
        $sureste = $this->lavanderiaConHorario(
            $this->plazaConAgenda('Plaza de prueba sureste', zonaHoraria: 'America/Cancun'),
            [[self::JUEVES, '09:00', '10:00']],
        );

        $enElCentro = $this->pedirVentanas($centro, self::JUEVES_DE_PRUEBA)->assertOk();
        $enElSureste = $this->pedirVentanas($sureste, self::JUEVES_DE_PRUEBA)->assertOk();

        // Las dos dicen 9:00 a 10:00, cada una en su hora.
        $this->assertSame(['09:00–10:00'], $this->horasDe($enElCentro));
        $this->assertSame(['09:00–10:00'], $this->horasDe($enElSureste));
        $enElSureste->assertJsonPath('zona_horaria', 'America/Cancun');
        $enElSureste->assertJsonPath('ventanas.0.inicio', '2026-12-03T09:00:00-05:00');

        // Pero son instantes distintos: las 15:00 y las 14:00 en UTC.
        $this->assertTrue(CarbonImmutable::parse($enElCentro->json('ventanas.0.inicio'))->eq('2026-12-03 15:00:00+00'));
        $this->assertTrue(CarbonImmutable::parse($enElSureste->json('ventanas.0.inicio'))->eq('2026-12-03 14:00:00+00'));
    }

    #[TestDox('agenda-E02: la ventana que entrega la agenda se guarda en timestamptz como el instante absoluto de esa hora local')]
    public function test_agenda_e02_la_ventana_se_guarda_como_el_instante_absoluto(): void
    {
        $plaza = $this->plazaConAgenda(zonaHoraria: 'America/Mexico_City');
        $lavanderia = $this->lavanderiaConHorario($plaza, [[self::JUEVES, '09:00', '10:00']]);

        $ventana = app(Agenda::class)->dia($lavanderia, self::JUEVES_DE_PRUEBA)->ventanas->sole();

        // Una columna timestamptz como la de pedidos.ventana_inicio (resumen-y-confirmacion), con el mismo enlace de
        // valores que usa Eloquent, que escribe la fecha sin desfase.
        DB::statement('create temporary table ventana_guardada (inicio timestamptz not null, fin timestamptz not null)');
        DB::table('ventana_guardada')->insert(['inicio' => $ventana->inicio, 'fin' => $ventana->fin]);

        $guardada = DB::selectOne(
            "select inicio = '2026-12-03 15:00:00+00'::timestamptz as inicio_exacto,
                    fin = '2026-12-03 16:00:00+00'::timestamptz as fin_exacto
               from ventana_guardada",
        );

        $this->assertTrue($guardada->inicio_exacto);
        $this->assertTrue($guardada->fin_exacto);
    }

    #[TestDox('agenda-E03: con horario de 9:00 a 13:30, la última ventana es 12:00–13:00 y ninguna sale del horario')]
    public function test_agenda_e03_con_horario_de_9_a_13_30_la_ultima_ventana_es_de_12_a_13(): void
    {
        $lavanderia = $this->lavanderiaConHorario($this->plazaConAgenda(), [[self::JUEVES, '09:00', '13:30']]);

        $respuesta = $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk();

        $this->assertSame(['09:00–10:00', '10:00–11:00', '11:00–12:00', '12:00–13:00'], $this->horasDe($respuesta));

        $apertura = CarbonImmutable::parse('2026-12-03 09:00:00', 'America/Mexico_City');
        $cierre = CarbonImmutable::parse('2026-12-03 13:30:00', 'America/Mexico_City');

        foreach ($respuesta->json('ventanas') as $ventana) {
            $this->assertFalse(CarbonImmutable::parse($ventana['inicio'])->lt($apertura), $ventana['inicio']);
            $this->assertFalse(CarbonImmutable::parse($ventana['fin'])->gt($cierre), $ventana['fin']);
        }
    }

    #[TestDox('agenda-E03: cada periodo se parte en tramos de una hora desde su inicio')]
    public function test_agenda_e03_cada_periodo_se_parte_desde_su_inicio(): void
    {
        $lavanderia = $this->lavanderiaConHorario($this->plazaConAgenda(), [
            [self::JUEVES, '09:30', '12:00'],
            [self::JUEVES, '16:15', '17:15'],
            // Una franja de menos de una hora no da ninguna ventana.
            [self::JUEVES, '19:00', '19:45'],
        ]);

        $respuesta = $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk();

        $this->assertSame(['09:30–10:30', '10:30–11:30', '16:15–17:15'], $this->horasDe($respuesta));
    }

    #[TestDox('agenda-E03: solo cuenta el horario de ese día de la semana y de esa lavandería')]
    public function test_agenda_e03_solo_cuenta_el_horario_de_ese_dia_y_de_esa_lavanderia(): void
    {
        $plaza = $this->plazaConAgenda();
        $lavanderia = $this->lavanderiaConHorario($plaza, [
            [self::LUNES, '08:00', '09:00'],
            [self::JUEVES, '10:00', '11:00'],
        ]);
        // Otra lavandería de la misma plaza, con otro horario el jueves.
        $this->lavanderiaConHorario($plaza, [[self::JUEVES, '15:00', '16:00']]);

        $respuesta = $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk();

        $this->assertSame(['10:00–11:00'], $this->horasDe($respuesta));
    }

    #[TestDox('agenda-E04: sin horario los domingos, el servidor no devuelve ninguna ventana y dice que ese día no da servicio')]
    public function test_agenda_e04_sin_horario_el_domingo_no_hay_ventanas_y_dice_que_no_da_servicio(): void
    {
        $lavanderia = $this->lavanderiaConHorario($this->plazaConAgenda(), array_map(
            fn (int $dia): array => [$dia, '09:00', '13:00'],
            range(self::LUNES, self::SABADO),
        ));

        $domingo = $this->pedirVentanas($lavanderia, self::DOMINGO_DE_PRUEBA)->assertOk();

        $domingo->assertJsonPath('fecha', self::DOMINGO_DE_PRUEBA);
        $domingo->assertJsonPath('ventanas', []);
        $domingo->assertJsonPath('dia_sin_servicio', true);

        // El lunes siguiente sí da servicio.
        $lunes = $this->pedirVentanas($lavanderia, '2026-12-07')->assertOk();
        $lunes->assertJsonPath('dia_sin_servicio', false);
        $this->assertCount(4, $lunes->json('ventanas'));
    }

    #[TestDox('agenda-E03 y agenda-E04: las lavanderías de prueba sembradas traen su horario de prueba, con ventanas entre semana y el domingo sin servicio')]
    public function test_agenda_e03_y_e04_las_lavanderias_de_prueba_sembradas_traen_su_horario(): void
    {
        $this->seed([PlazasDePruebaSeeder::class, LavanderiasDePruebaSeeder::class]);

        $lavanderias = Lavanderia::query()->visiblesParaElUsuario()->get();
        $this->assertNotEmpty($lavanderias);

        foreach ($lavanderias as $lavanderia) {
            // El jueves, dos franjas: de 9:00 a 14:00 y de 16:00 a 19:30, cuya última ventana completa es 18:00–19:00.
            $this->assertSame(
                ['09:00–10:00', '10:00–11:00', '11:00–12:00', '12:00–13:00', '13:00–14:00', '16:00–17:00', '17:00–18:00', '18:00–19:00'],
                $this->horasDe($this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk()),
                $lavanderia->nombre,
            );

            $domingo = $this->pedirVentanas($lavanderia, self::DOMINGO_DE_PRUEBA)->assertOk();
            $domingo->assertJsonPath('dia_sin_servicio', true);
            $domingo->assertJsonPath('ventanas', []);
        }

        // Las tres de Durango, en la plaza de prueba con tierra, también (plazas-E16).
        $this->assertSame(3, $lavanderias->filter(fn (Lavanderia $lavanderia): bool => Str::contains($lavanderia->nombre, 'Durango'))->count());
    }

    #[TestDox('agenda-E01: sin sesión responde que no hay sesión, y una cuenta sin el rol de usuario no ve la agenda')]
    public function test_agenda_e01_sin_sesion_o_sin_el_rol_de_usuario_no_se_ve_la_agenda(): void
    {
        $lavanderia = $this->lavanderiaConHorario($this->plazaConAgenda(), [[self::JUEVES, '09:00', '13:00']]);
        $parametros = ['fecha' => self::JUEVES_DE_PRUEBA];

        $this->pedirSinSesion($this->direccionDeVentanas($lavanderia), $parametros)
            ->assertUnauthorized()
            ->assertJsonMissingPath('ventanas');

        foreach ([Usuario::factory()->create(), Usuario::factory()->conRol(Rol::LAVANDERIA)->create()] as $cuenta) {
            $this->pedirComoUsuario($this->direccionDeVentanas($lavanderia), $parametros, $cuenta)
                ->assertForbidden()
                ->assertJsonMissingPath('ventanas');
        }
    }

    #[TestDox('agenda-E01: la agenda de una lavandería que el usuario no ve responde que no existe, sin ventanas')]
    public function test_agenda_e01_la_agenda_de_una_lavanderia_que_el_usuario_no_ve_responde_que_no_existe(): void
    {
        $plaza = $this->plazaConAgenda();
        $franjas = [[self::JUEVES, '09:00', '13:00']];
        $suspendida = $this->lavanderiaConHorario($plaza, $franjas);
        $suspendida->update(['estado' => Lavanderia::SUSPENDIDA]);
        $deBaja = $this->lavanderiaConHorario($plaza, $franjas);
        $deBaja->update(['estado' => Lavanderia::BAJA]);
        $borrada = $this->lavanderiaConHorario($plaza, $franjas);
        $borrada->delete();
        $deOtraPlaza = $this->lavanderiaConHorario($this->plazaConAgenda('Plaza de prueba inactiva', activa: false), $franjas);

        foreach ([$suspendida, $deBaja, $borrada, $deOtraPlaza] as $lavanderia) {
            $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)
                ->assertNotFound()
                ->assertJsonPath('message', 'No existe la lavandería.')
                ->assertJsonMissingPath('ventanas');
        }

        // Un identificador con forma de ULID que no es de nadie responde igual.
        $this->pedirComoUsuario('/api/lavanderias/'.Str::lower((string) Str::ulid()).'/ventanas', ['fecha' => self::JUEVES_DE_PRUEBA])
            ->assertNotFound()
            ->assertJsonPath('message', 'No existe la lavandería.');

        // La misma petición a una activa de la misma plaza sí trae sus ventanas: el 404 no es de la ruta.
        $this->assertCount(4, $this->pedirVentanas($this->lavanderiaConHorario($plaza, $franjas), self::JUEVES_DE_PRUEBA)
            ->assertOk()
            ->json('ventanas'));
    }

    #[TestDox('agenda-E01: sin fecha, o con una fecha que no es AAAA-MM-DD, responde un error de validación que lo dice')]
    public function test_agenda_e01_sin_fecha_o_con_una_fecha_mal_escrita_responde_un_error_de_validacion(): void
    {
        $lavanderia = $this->lavanderiaConHorario($this->plazaConAgenda(), [[self::JUEVES, '09:00', '13:00']]);

        $this->pedirComoUsuario($this->direccionDeVentanas($lavanderia))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha' => 'Falta la fecha.']);

        foreach (['03/12/2026', '2026-02-30', 'jueves', '2026-12-03T09:00:00'] as $fecha) {
            $this->pedirVentanas($lavanderia, $fecha)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['fecha' => 'La fecha va como AAAA-MM-DD, en la hora de la plaza.']);
        }
    }
}
