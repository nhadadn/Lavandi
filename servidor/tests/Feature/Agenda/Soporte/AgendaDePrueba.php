<?php

namespace Tests\Feature\Agenda\Soporte;

use App\Agenda\ParametrosDeAgenda;
use App\Models\Lavanderia;
use App\Models\ParametroPlaza;
use App\Models\Plaza;
use App\Models\Usuario;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas de la agenda: una lavandería de prueba con su horario sembrado en lavanderia_horarios, y
 * la petición de sus ventanas con la sesión de una cuenta de usuario. Se usa junto con LavanderiasDePrueba y
 * PlazasDePrueba.
 *
 * Los días van del 1, el lunes, al 7, el domingo (ISO 8601), como en lavanderia_horarios.
 */
trait AgendaDePrueba
{
    protected const LUNES = 1;

    protected const JUEVES = 4;

    protected const SABADO = 6;

    protected const DOMINGO = 7;

    /**
     * Una plaza de prueba activa con su agenda configurada en parametros_plaza, con valores de prueba: los de
     * producción los fija EL CLIENTE (DD-099).
     */
    protected function plazaConAgenda(
        string $nombre = 'Plaza de prueba 1',
        string $zonaHoraria = 'America/Mexico_City',
        bool $activa = true,
        int $anticipacionMinutos = 0,
        int $diasHaciaAdelante = 7,
    ): Plaza {
        $plaza = $this->plazaDePrueba($nombre, $activa, $zonaHoraria);
        $this->configurarAgenda($plaza, $anticipacionMinutos, $diasHaciaAdelante);

        return $plaza;
    }

    /**
     * Las dos claves de la agenda de la plaza, con la vigencia que se pasa: la anticipación mínima y los días hacia
     * adelante.
     *
     * @return array{ParametroPlaza, ParametroPlaza}
     */
    protected function configurarAgenda(
        Plaza $plaza,
        int $anticipacionMinutos,
        int $diasHaciaAdelante,
        string $desde = '2026-01-01 00:00:00+00',
        ?string $hasta = null,
    ): array {
        return [
            $this->anticipacionMinima($plaza, $anticipacionMinutos, $desde, $hasta),
            $plaza->parametros()->create([
                'clave' => ParametrosDeAgenda::DIAS_HACIA_ADELANTE,
                'valor' => ['dias' => $diasHaciaAdelante],
                'vigente_desde' => $desde,
                'vigente_hasta' => $hasta,
            ]),
        ];
    }

    protected function anticipacionMinima(
        Plaza $plaza,
        int $minutos,
        string $desde = '2026-01-01 00:00:00+00',
        ?string $hasta = null,
    ): ParametroPlaza {
        return $plaza->parametros()->create([
            'clave' => ParametrosDeAgenda::ANTICIPACION_MINIMA,
            'valor' => ['minutos' => $minutos],
            'vigente_desde' => $desde,
            'vigente_hasta' => $hasta,
        ]);
    }

    /**
     * Una lavandería activa de la plaza, con su horario: una franja por renglón, [día, hora de inicio, hora de fin],
     * en la hora de la plaza.
     *
     * @param  list<array{int, string, string}>  $franjas
     */
    protected function lavanderiaConHorario(Plaza $plaza, array $franjas): Lavanderia
    {
        $lavanderia = Lavanderia::factory()->en($plaza)->create();

        foreach ($franjas as [$dia, $inicio, $fin]) {
            $lavanderia->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);
        }

        return $lavanderia;
    }

    /**
     * Las ventanas de la lavandería para una fecha de la plaza, como las pide la app.
     */
    protected function pedirVentanas(Lavanderia $lavanderia, string $fecha, ?Usuario $cuenta = null): TestResponse
    {
        return $this->pedirComoUsuario($this->direccionDeVentanas($lavanderia), ['fecha' => $fecha], $cuenta);
    }

    protected function direccionDeVentanas(Lavanderia $lavanderia): string
    {
        return "/api/lavanderias/{$lavanderia->id_publico}/ventanas";
    }

    /**
     * Las ventanas de la respuesta como "09:00–10:00", en la hora de la plaza.
     *
     * @return list<string>
     */
    protected function horasDe(TestResponse $respuesta): array
    {
        return array_map(
            fn (array $ventana): string => "{$ventana['hora_inicio']}–{$ventana['hora_fin']}",
            $respuesta->json('ventanas'),
        );
    }
}
