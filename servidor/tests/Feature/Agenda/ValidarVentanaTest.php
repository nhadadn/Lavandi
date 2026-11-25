<?php

namespace Tests\Feature\Agenda;

use App\Agenda\Agenda;
use App\Agenda\AgendaSinConfigurar;
use App\Agenda\ParametrosDeAgenda;
use App\Models\Lavanderia;
use App\Models\ParametroPlaza;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Solo ventanas futuras, con la anticipación mínima y los días hacia adelante leídos de dos claves de parametros_plaza,
 * y la validación de una ventana elegida con la misma lógica que la consulta (NSV-2026-001-v8.pdf:16:ANEXO A, Sección
 * A.3, Módulo A #7; :21:A.4.3; :4:Cláusula I; openspec/changes/agenda-de-recoleccion/design.md, decisiones 1 y 3).
 *
 * Los valores de la anticipación y del horizonte son de prueba: los de producción los fija EL CLIENTE (DD-099). La
 * validación la reusa la creación del pedido (resumen-y-confirmacion, confirmacion-E08), que todavía no existe.
 *
 * Escenarios: agenda-E09, agenda-E11 y agenda-E12 · Spec: openspec/changes/agenda-de-recoleccion/specs/agenda/spec.md
 */
class ValidarVentanaTest extends TestCase
{
    use AgendaDePrueba;
    use LavanderiasDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** La zona de la plaza de prueba: UTC-6, distinta de UTC. */
    private const ZONA = 'America/Mexico_City';

    private const JUEVES_DE_PRUEBA = '2026-12-03';

    /**
     * El reloj en esa hora del jueves de prueba, en la hora de la plaza.
     */
    private function elJuevesALas(string $hora): void
    {
        $this->travelTo(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' '.$hora, self::ZONA));
    }

    /**
     * Todos los días, de 9:00 a 13:00.
     *
     * @return list<array{int, string, string}>
     */
    private function todaLaSemanaDe9A13(): array
    {
        return array_map(fn (int $dia): array => [$dia, '09:00', '13:00'], range(self::LUNES, self::DOMINGO));
    }

    #[TestDox('agenda-E11: a las 10:20 del jueves en la hora de la plaza, con anticipación de cero, la primera ventana que se ofrece es 11:00–12:00')]
    public function test_agenda_e11_a_las_10_20_la_primera_ventana_que_se_ofrece_es_de_11_a_12(): void
    {
        $this->elJuevesALas('10:20:00');
        $plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA, anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $lavanderia = $this->lavanderiaConHorario($plaza, [[self::JUEVES, '09:00', '13:00']]);

        $respuesta = $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk();

        $this->assertSame(['11:00–12:00', '12:00–13:00'], $this->horasDe($respuesta));
        // Ese día sí da servicio: solo ya pasaron las primeras.
        $respuesta->assertJsonPath('dia_sin_servicio', false);
    }

    #[TestDox('agenda-E11: una ventana que empieza en este mismo instante ya empezó y no se ofrece')]
    public function test_agenda_e11_una_ventana_que_empieza_en_este_instante_ya_empezo(): void
    {
        $this->elJuevesALas('11:00:00');
        $plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA, anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $lavanderia = $this->lavanderiaConHorario($plaza, [[self::JUEVES, '09:00', '13:00']]);

        $this->assertSame(['12:00–13:00'], $this->horasDe($this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk()));
    }

    #[TestDox('agenda-E12: con anticipación de 2 horas y 3 días hacia adelante, no se ofrece ninguna ventana que empiece antes de 2 horas desde ahora ni después del tercer día')]
    public function test_agenda_e12_con_anticipacion_de_2_horas_y_3_dias_hacia_adelante(): void
    {
        $this->elJuevesALas('09:30:00');
        $plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA, anticipacionMinutos: 120, diasHaciaAdelante: 3);
        $lavanderia = $this->lavanderiaConHorario($plaza, $this->todaLaSemanaDe9A13());

        // El jueves, nada antes de las 11:30.
        $this->assertSame(['12:00–13:00'], $this->horasDe($this->pedirVentanas($lavanderia, '2026-12-03')->assertOk()));

        // Viernes, sábado y domingo, el tercer día, completos.
        foreach (['2026-12-04', '2026-12-05', '2026-12-06'] as $fecha) {
            $this->assertSame(
                ['09:00–10:00', '10:00–11:00', '11:00–12:00', '12:00–13:00'],
                $this->horasDe($this->pedirVentanas($lavanderia, $fecha)->assertOk()),
                $fecha,
            );
        }

        // El lunes ya no: da servicio, pero está después del tercer día.
        $lunes = $this->pedirVentanas($lavanderia, '2026-12-07')->assertOk();
        $lunes->assertJsonPath('ventanas', []);
        $lunes->assertJsonPath('dia_sin_servicio', false);

        // Ni un día que ya pasó.
        $this->pedirVentanas($lavanderia, '2026-12-02')->assertOk()->assertJsonPath('ventanas', []);

        // La app sabe entre qué días navegar.
        $lunes->assertJsonPath('primer_dia', '2026-12-03');
        $lunes->assertJsonPath('ultimo_dia', '2026-12-06');

        $desde = CarbonImmutable::now()->addHours(2);
        foreach (['2026-12-03', '2026-12-04', '2026-12-05', '2026-12-06'] as $fecha) {
            foreach ($this->pedirVentanas($lavanderia, $fecha)->json('ventanas') as $ventana) {
                $this->assertTrue(CarbonImmutable::parse($ventana['inicio'])->gt($desde), $ventana['inicio']);
                $this->assertLessThanOrEqual('2026-12-06', $ventana['fecha']);
            }
        }
    }

    #[TestDox('agenda-E12: los días hacia adelante se cuentan desde el hoy de la plaza, no desde el de UTC')]
    public function test_agenda_e12_los_dias_se_cuentan_desde_el_hoy_de_la_plaza(): void
    {
        // Las 21:10 del jueves en la plaza son ya las 3:10 del viernes en UTC.
        $this->elJuevesALas('21:10:00');
        $this->assertSame('2026-12-04', CarbonImmutable::now('UTC')->toDateString());

        $plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA, anticipacionMinutos: 0, diasHaciaAdelante: 0);
        $lavanderia = $this->lavanderiaConHorario($plaza, [
            [self::JUEVES, '20:00', '23:00'],
            [5, '09:00', '13:00'],
        ]);

        $jueves = $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)->assertOk();
        $this->assertSame(['22:00–23:00'], $this->horasDe($jueves));
        $jueves->assertJsonPath('primer_dia', self::JUEVES_DE_PRUEBA);
        $jueves->assertJsonPath('ultimo_dia', self::JUEVES_DE_PRUEBA);

        // Con cero días hacia adelante, solo hoy: el viernes de la plaza todavía no.
        $this->pedirVentanas($lavanderia, '2026-12-04')->assertOk()->assertJsonPath('ventanas', []);
    }

    #[TestDox('agenda-E12: al cambiar la configuración cambia la respuesta, sin desplegar código ni correr migraciones')]
    public function test_agenda_e12_al_cambiar_la_configuracion_cambia_la_respuesta(): void
    {
        $this->elJuevesALas('09:30:00');
        $plaza = $this->plazaDePrueba(zonaHoraria: self::ZONA);
        [$anticipacion, $dias] = $this->configurarAgenda($plaza, anticipacionMinutos: 120, diasHaciaAdelante: 3);
        $lavanderia = $this->lavanderiaConHorario($plaza, $this->todaLaSemanaDe9A13());
        $migraciones = DB::table('migrations')->count();

        $this->assertSame(['12:00–13:00'], $this->horasDe($this->pedirVentanas($lavanderia, '2026-12-03')));
        $this->pedirVentanas($lavanderia, '2026-12-07')->assertJsonPath('ventanas', []);

        // Solo se cambian las dos filas.
        $anticipacion->update(['valor' => ['minutos' => 0]]);
        $dias->update(['valor' => ['dias' => 4]]);

        $this->assertSame(
            ['10:00–11:00', '11:00–12:00', '12:00–13:00'],
            $this->horasDe($this->pedirVentanas($lavanderia, '2026-12-03')->assertOk()),
        );
        $lunes = $this->pedirVentanas($lavanderia, '2026-12-07')->assertOk();
        $this->assertCount(4, $lunes->json('ventanas'));
        $lunes->assertJsonPath('ultimo_dia', '2026-12-07');

        $this->assertSame($migraciones, DB::table('migrations')->count());
    }

    #[TestDox('agenda-E12: una vigencia nueva de la configuración rige desde que empieza')]
    public function test_agenda_e12_una_vigencia_nueva_rige_desde_que_empieza(): void
    {
        $plaza = $this->plazaDePrueba(zonaHoraria: self::ZONA);
        $cambio = '2026-12-03 18:00:00+00';
        // Cero minutos hasta el mediodía del jueves en la plaza; desde ahí, dos horas.
        $this->anticipacionMinima($plaza, 0, hasta: $cambio);
        $this->anticipacionMinima($plaza, 120, desde: $cambio);
        $plaza->parametros()->create([
            'clave' => ParametrosDeAgenda::DIAS_HACIA_ADELANTE,
            'valor' => ['dias' => 3],
            'vigente_desde' => '2026-01-01 00:00:00+00',
        ]);
        $lavanderia = $this->lavanderiaConHorario($plaza, $this->todaLaSemanaDe9A13());

        // Las 9:30 del jueves: rige la de cero minutos.
        $this->elJuevesALas('09:30:00');
        $this->assertSame(
            ['10:00–11:00', '11:00–12:00', '12:00–13:00'],
            $this->horasDe($this->pedirVentanas($lavanderia, '2026-12-03')->assertOk()),
        );

        // Las 8:00 del viernes: rige la de dos horas, y no se ofrece nada antes de las 10:00.
        $this->travelTo(CarbonImmutable::parse('2026-12-04 08:00:00', self::ZONA));
        $this->assertSame(
            ['11:00–12:00', '12:00–13:00'],
            $this->horasDe($this->pedirVentanas($lavanderia, '2026-12-04')->assertOk()),
        );
    }

    #[TestDox('agenda-E12: sin la configuración de la plaza no se ofrece ninguna ventana, porque ningún valor está escrito en el código')]
    public function test_agenda_e12_sin_la_configuracion_de_la_plaza_no_se_ofrece_ninguna_ventana(): void
    {
        $this->elJuevesALas('09:30:00');
        $horario = [[self::JUEVES, '09:00', '13:00']];

        $sinNada = $this->lavanderiaConHorario($this->plazaDePrueba('Plaza de prueba sin agenda', zonaHoraria: self::ZONA), $horario);

        $plazaAMedias = $this->plazaDePrueba('Plaza de prueba con media agenda', zonaHoraria: self::ZONA);
        $this->anticipacionMinima($plazaAMedias, 0);
        $aMedias = $this->lavanderiaConHorario($plazaAMedias, $horario);

        $plazaVencida = $this->plazaDePrueba('Plaza de prueba con la agenda vencida', zonaHoraria: self::ZONA);
        $this->configurarAgenda($plazaVencida, 0, 3, hasta: '2026-12-01 00:00:00+00');
        $vencida = $this->lavanderiaConHorario($plazaVencida, $horario);

        foreach ([$sinNada, $aMedias, $vencida] as $lavanderia) {
            $this->pedirVentanas($lavanderia, self::JUEVES_DE_PRUEBA)
                ->assertServiceUnavailable()
                ->assertExactJson(['message' => 'La agenda de la plaza no está configurada.']);

            $this->assertThrows(
                fn () => app(Agenda::class)->validar($lavanderia, '2026-12-03T11:00:00-06:00', '2026-12-03T12:00:00-06:00'),
                AgendaSinConfigurar::class,
            );
        }

        // La misma lavandería, con la clave que le faltaba, sí ofrece sus ventanas.
        $plazaAMedias->parametros()->create([
            'clave' => ParametrosDeAgenda::DIAS_HACIA_ADELANTE,
            'valor' => ['dias' => 3],
            'vigente_desde' => '2026-12-02 00:00:00+00',
        ]);
        $this->assertCount(3, $this->pedirVentanas($aMedias, self::JUEVES_DE_PRUEBA)->assertOk()->json('ventanas'));
    }

    #[TestDox('agenda-E12: las dos claves se validan al guardarse: minutos y días, enteros de cero en adelante')]
    public function test_agenda_e12_las_dos_claves_se_validan_al_guardarse(): void
    {
        $plaza = $this->plazaDePrueba(zonaHoraria: self::ZONA);
        $casos = [
            [ParametrosDeAgenda::ANTICIPACION_MINIMA, ['minutos' => -1], 'valor.minutos'],
            [ParametrosDeAgenda::ANTICIPACION_MINIMA, ['minutos' => '120'], 'valor.minutos'],
            [ParametrosDeAgenda::ANTICIPACION_MINIMA, ['minutos' => 1.5], 'valor.minutos'],
            [ParametrosDeAgenda::ANTICIPACION_MINIMA, ['horas' => 2], 'valor'],
            [ParametrosDeAgenda::ANTICIPACION_MINIMA, ['minutos' => 120, 'dias' => 3], 'valor'],
            [ParametrosDeAgenda::ANTICIPACION_MINIMA, 120, 'valor'],
            [ParametrosDeAgenda::DIAS_HACIA_ADELANTE, ['dias' => -1], 'valor.dias'],
            [ParametrosDeAgenda::DIAS_HACIA_ADELANTE, ['dias' => '3'], 'valor.dias'],
            [ParametrosDeAgenda::DIAS_HACIA_ADELANTE, [], 'valor'],
        ];

        foreach ($casos as $numero => [$clave, $valor, $campo]) {
            try {
                $plaza->parametros()->create(['clave' => $clave, 'valor' => $valor, 'vigente_desde' => '2026-01-01 00:00:00+00']);
                $this->fail("Caso {$numero}: la clave {$clave} debió rechazar ".json_encode($valor).'.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey($campo, $error->errors(), "Caso {$numero}");
            }
        }

        $this->assertSame(0, ParametroPlaza::count());

        // El motivo se dice con la unidad.
        try {
            $this->anticipacionMinima($plaza, -5);
        } catch (ValidationException $error) {
            $this->assertSame(
                ['La anticipación mínima va en minutos: un número entero de 0 en adelante.'],
                $error->errors()['valor.minutos'],
            );
        }

        // Cero minutos y cero días son valores válidos.
        $this->configurarAgenda($plaza, 0, 0);
        $this->assertSame(2, ParametroPlaza::count());
    }

    #[TestDox('agenda-E09: con horario de 9:00 a 13:00, el servidor rechaza 14:00–15:00, una de media hora y una que ya terminó, con un error de validación que nombra el motivo')]
    public function test_agenda_e09_rechaza_una_ventana_fuera_del_horario_una_de_media_hora_y_una_que_ya_termino(): void
    {
        $this->elJuevesALas('10:20:00');
        $plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA, anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $lavanderia = $this->lavanderiaConHorario($plaza, [[self::JUEVES, '09:00', '13:00']]);

        $this->assertRechazada($lavanderia, '2026-12-03T14:00:00-06:00', '2026-12-03T15:00:00-06:00', 'La lavandería no ofrece esa ventana en su horario.');
        $this->assertRechazada($lavanderia, '2026-12-03T11:00:00-06:00', '2026-12-03T11:30:00-06:00', 'La ventana debe durar exactamente una hora.');
        $this->assertRechazada($lavanderia, '2026-12-03T09:00:00-06:00', '2026-12-03T10:00:00-06:00', 'La ventana ya terminó.');

        // Una que sí ofrece se acepta, y sale como instante absoluto.
        $aceptada = app(Agenda::class)->validar($lavanderia, '2026-12-03T11:00:00-06:00', '2026-12-03T12:00:00-06:00');
        $this->assertTrue($aceptada->inicio->eq('2026-12-03 17:00:00+00'));
        $this->assertTrue($aceptada->fin->eq('2026-12-03 18:00:00+00'));
        $this->assertSame('UTC', $aceptada->inicio->getTimezone()->getName());
    }

    #[TestDox('agenda-E09: también rechaza una que ya empezó, una antes de la anticipación, una después de los días hacia adelante, una que no empieza donde empiezan las de la lavandería y una mal escrita')]
    public function test_agenda_e09_rechaza_las_demas_ventanas_que_no_ofrece_y_nombra_el_motivo(): void
    {
        $this->elJuevesALas('10:20:00');
        // Nada antes de las 11:50.
        $plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA, anticipacionMinutos: 90, diasHaciaAdelante: 3);
        $lavanderia = $this->lavanderiaConHorario($plaza, $this->todaLaSemanaDe9A13());

        $this->assertRechazada($lavanderia, '2026-12-03T10:00:00-06:00', '2026-12-03T11:00:00-06:00', 'La ventana ya empezó.');
        $this->assertRechazada($lavanderia, '2026-12-03T11:00:00-06:00', '2026-12-03T12:00:00-06:00', 'La ventana empieza antes de la anticipación mínima de la plaza.');
        $this->assertRechazada($lavanderia, '2026-12-07T10:00:00-06:00', '2026-12-07T11:00:00-06:00', 'La ventana está después de los días que se pueden agendar.');
        $this->assertRechazada($lavanderia, '2026-12-04T09:30:00-06:00', '2026-12-04T10:30:00-06:00', 'La lavandería no ofrece esa ventana en su horario.');

        $malEscrita = 'La ventana va con su inicio y su fin en ISO 8601, con su desfase, como los da la agenda.';
        foreach ([
            ['2026-12-04 10:00', '2026-12-04 11:00'],
            ['2026-12-04T10:00:00', '2026-12-04T11:00:00'],
            ['2026-02-30T10:00:00-06:00', '2026-02-30T11:00:00-06:00'],
            ['viernes', 'a las once'],
            [null, null],
            [1764864000, 1764867600],
            [['2026-12-04T10:00:00-06:00'], '2026-12-04T11:00:00-06:00'],
        ] as [$inicio, $fin]) {
            $this->assertRechazada($lavanderia, $inicio, $fin, $malEscrita);
        }

        // La de las 12:00 del jueves sí.
        $this->assertTrue(app(Agenda::class)
            ->validar($lavanderia, '2026-12-03T12:00:00-06:00', '2026-12-03T13:00:00-06:00')
            ->inicio->eq('2026-12-03 18:00:00+00'));
    }

    #[TestDox('agenda-E09: la validación acepta exactamente las ventanas que ofrece la consulta, con la misma lógica, se escriban con el desfase que sea')]
    public function test_agenda_e09_la_validacion_acepta_exactamente_las_ventanas_que_ofrece_la_consulta(): void
    {
        $this->elJuevesALas('10:20:00');
        $plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA, anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $lavanderia = $this->lavanderiaConHorario($plaza, [
            ...$this->todaLaSemanaDe9A13(),
            [5, '16:15', '17:15'],
        ]);
        $agenda = app(Agenda::class);
        $ofrecidas = 0;

        foreach (['2026-12-02', '2026-12-03', '2026-12-04', '2026-12-05', '2026-12-06', '2026-12-07'] as $fecha) {
            foreach ($this->pedirVentanas($lavanderia, $fecha)->assertOk()->json('ventanas') as $ventana) {
                $ofrecidas++;
                $this->assertSame($ventana, $agenda->validar($lavanderia, $ventana['inicio'], $ventana['fin'])->toArray());

                // La misma, escrita en UTC.
                $enUtc = fn (string $instante): string => CarbonImmutable::parse($instante)->utc()->format('Y-m-d\TH:i:s\Z');
                $this->assertSame($ventana, $agenda->validar($lavanderia, $enUtc($ventana['inicio']), $enUtc($ventana['fin']))->toArray());

                // Media hora después ya no es una de las que ofrece.
                $corrida = fn (string $instante): string => CarbonImmutable::parse($instante)->addMinutes(30)->toIso8601String();
                $this->assertRechazada($lavanderia, $corrida($ventana['inicio']), $corrida($ventana['fin']));
            }
        }

        // Jueves 2, viernes 5, sábado 4 y domingo 4; ni el miércoles que pasó ni el lunes después del tercer día.
        $this->assertSame(15, $ofrecidas);
    }

    #[TestDox('agenda-E09: una ventana que ofrece otra lavandería no vale para esta')]
    public function test_agenda_e09_una_ventana_de_otra_lavanderia_no_vale_para_esta(): void
    {
        $this->elJuevesALas('10:20:00');
        $plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA, anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $mananas = $this->lavanderiaConHorario($plaza, [[self::JUEVES, '09:00', '13:00']]);
        $tardes = $this->lavanderiaConHorario($plaza, [[self::JUEVES, '14:00', '18:00']]);

        $this->assertTrue(app(Agenda::class)
            ->validar($tardes, '2026-12-03T14:00:00-06:00', '2026-12-03T15:00:00-06:00')
            ->inicio->eq('2026-12-03 20:00:00+00'));
        $this->assertRechazada($mananas, '2026-12-03T14:00:00-06:00', '2026-12-03T15:00:00-06:00', 'La lavandería no ofrece esa ventana en su horario.');
    }

    #[TestDox('agenda-E09: el rechazo llega a la app como un error de validación 422 que nombra el motivo en errors.ventana')]
    public function test_agenda_e09_el_rechazo_llega_como_un_error_de_validacion_422(): void
    {
        $this->elJuevesALas('10:20:00');
        $plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA, anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $lavanderia = $this->lavanderiaConHorario($plaza, [[self::JUEVES, '09:00', '13:00']]);

        // Una operación de prueba que valida la ventana como lo hará la creación del pedido (confirmacion-E08).
        Route::post('prueba/ventana', fn (Request $peticion): array => app(Agenda::class)
            ->validar($lavanderia, $peticion->input('inicio'), $peticion->input('fin'))
            ->toArray());

        $this->postJson('/prueba/ventana', ['inicio' => '2026-12-03T09:00:00-06:00', 'fin' => '2026-12-03T10:00:00-06:00'])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['ventana' => ['La ventana ya terminó.']]);

        $this->postJson('/prueba/ventana', ['inicio' => '2026-12-03T11:00:00-06:00', 'fin' => '2026-12-03T12:00:00-06:00'])
            ->assertOk()
            ->assertJsonPath('hora_inicio', '11:00');
    }

    /**
     * La validación rechaza la ventana con un error de validación en "ventana"; si se pasa el motivo, con ese texto.
     */
    private function assertRechazada(Lavanderia $lavanderia, mixed $inicio, mixed $fin, ?string $motivo = null): void
    {
        $caso = json_encode([$inicio, $fin]);

        try {
            app(Agenda::class)->validar($lavanderia, $inicio, $fin);
            $this->fail("La ventana {$caso} debió rechazarse.");
        } catch (ValidationException $error) {
            $this->assertSame(['ventana'], array_keys($error->errors()), $caso);

            if ($motivo !== null) {
                $this->assertSame([$motivo], $error->errors()['ventana'], $caso);
            }
        }
    }
}
