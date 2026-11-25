<?php

namespace App\Agenda;

use App\Models\Lavanderia;
use App\Models\LavanderiaHorario;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El componente agenda de la API (vault/arquitectura/c3-api.md): las ventanas de una hora de una lavandería para una
 * fecha, calculadas en el servidor desde su horario en lavanderia_horarios y en la hora local de su plaza, y la
 * validación de una ventana elegida con esa misma lógica (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7;
 * :17:Módulo B #1 y #6; openspec/changes/agenda-de-recoleccion/design.md, decisiones 1, 2, 3 y 5).
 *
 * Cada franja del horario se parte en tramos de una hora desde su inicio, y solo cuentan los tramos completos: con
 * 9:00 a 13:30, la última ventana es 12:00 a 13:00 (agenda-E03). De ellos se ofrecen los que caben en el plazo de la
 * plaza: que empiecen después de ahora más la anticipación mínima, y no después del último día (Plazo; agenda-E11 y
 * agenda-E12). Las ventanas no se guardan: se calculan en cada consulta.
 *
 * La agenda no mira si la lavandería está abierta en este momento (design.md, decisión 4). El cupo por ventana
 * (agenda-E05, DD-099) y los festivos (agenda-E07, DD-078) no entran todavía.
 */
final class Agenda
{
    /** El formato en que viaja una ventana: ISO 8601 con desfase, como lo da Ventana::toArray(). Acepta también Z. */
    private const FORMATO = 'Y-m-d\TH:i:sP';

    /**
     * Las ventanas que la lavandería ofrece en esa fecha de su plaza.
     *
     * @param  string  $fecha  AAAA-MM-DD, en la hora de la plaza.
     *
     * @throws AgendaSinConfigurar
     */
    public function dia(Lavanderia $lavanderia, string $fecha): DiaDeAgenda
    {
        $zona = $lavanderia->plaza->zona_horaria;
        $plazo = Plazo::deLaPlaza($lavanderia->plaza);
        $dia = CarbonImmutable::createFromFormat('!Y-m-d', $fecha, $zona);
        $franjas = $this->franjas($lavanderia, $dia);

        return new DiaDeAgenda(
            fecha: $dia->toDateString(),
            zonaHoraria: $zona,
            sinServicio: $franjas->isEmpty(),
            ventanas: $this->ofrecidas($dia, $franjas, $zona, $plazo),
            primerDia: $plazo->primerDia,
            ultimoDia: $plazo->ultimoDia,
        );
    }

    /**
     * La ventana elegida, si es una de las que la lavandería ofrece en este momento; si no, un error de validación en
     * "ventana" que nombra el motivo (agenda-E09). Una ventana vale si y solo si dia() la ofrecería: los motivos solo
     * explican el rechazo.
     *
     * Es la regla que reusa la creación del pedido (resumen-y-confirmacion, confirmacion-E08). Lo que devuelve, en UTC,
     * es lo que se guarda en pedidos.ventana_inicio y pedidos.ventana_fin.
     *
     * @param  mixed  $inicio  ISO 8601 con desfase, como "2026-12-03T11:00:00-06:00".
     * @param  mixed  $fin  Igual.
     *
     * @throws ValidationException
     * @throws AgendaSinConfigurar
     */
    public function validar(Lavanderia $lavanderia, mixed $inicio, mixed $fin): Ventana
    {
        $desde = $this->instante($inicio);
        $hasta = $this->instante($fin);

        if ($desde === null || $hasta === null) {
            throw $this->rechazo('La ventana va con su inicio y su fin en ISO 8601, con su desfase, como los da la agenda.');
        }

        $zona = $lavanderia->plaza->zona_horaria;
        $plazo = Plazo::deLaPlaza($lavanderia->plaza);
        $dia = CarbonImmutable::createFromFormat('!Y-m-d', $desde->setTimezone($zona)->toDateString(), $zona);

        $ofrecida = $this->ofrecidas($dia, $this->franjas($lavanderia, $dia), $zona, $plazo)
            ->first(fn (Ventana $ventana): bool => $ventana->va($desde, $hasta));

        return $ofrecida ?? throw $this->rechazo($this->motivo($desde, $hasta, $plazo, $zona));
    }

    /**
     * Las franjas del horario de la lavandería en ese día de la semana.
     *
     * @return Collection<int, LavanderiaHorario>
     */
    private function franjas(Lavanderia $lavanderia, CarbonImmutable $dia): Collection
    {
        return $lavanderia->horarios()->where('dia_semana', $dia->isoWeekday())->get();
    }

    /**
     * Los tramos de una hora de las franjas que caben en el plazo, de la más temprana a la más tarde.
     *
     * @param  Collection<int, LavanderiaHorario>  $franjas
     * @return Collection<int, Ventana>
     */
    private function ofrecidas(CarbonImmutable $dia, Collection $franjas, string $zona, Plazo $plazo): Collection
    {
        return $franjas
            ->flatMap(fn (LavanderiaHorario $franja): Collection => $this->partir($dia, $franja, $zona))
            ->filter(fn (Ventana $ventana): bool => $plazo->admite($ventana))
            // Dos franjas encimadas pueden dar la misma ventana: sale una vez.
            ->unique(fn (Ventana $ventana): int => $ventana->inicio->getTimestamp())
            ->sortBy(fn (Ventana $ventana): int => $ventana->inicio->getTimestamp())
            ->values();
    }

    /**
     * Los tramos completos de una hora de la franja, desde su inicio.
     *
     * @return Collection<int, Ventana>
     */
    private function partir(CarbonImmutable $dia, LavanderiaHorario $franja, string $zona): Collection
    {
        $fin = $this->aLaHora($dia, $franja->hora_fin);
        $ventanas = new Collection;

        for ($inicio = $this->aLaHora($dia, $franja->hora_inicio); $inicio->addHour()->lte($fin); $inicio = $inicio->addHour()) {
            $ventanas->push(new Ventana($inicio, $zona));
        }

        return $ventanas;
    }

    /**
     * El instante de esa hora del día (HH:MM:SS, como la guarda la columna time) en la fecha de la plaza, en UTC para
     * sumar horas absolutas. Las 24:00 son la medianoche del día siguiente.
     */
    private function aLaHora(CarbonImmutable $dia, string $hora): CarbonImmutable
    {
        [$horas, $minutos, $segundos] = array_map('intval', explode(':', $hora) + [0, 0, 0]);

        return $dia->setTime($horas, $minutos, $segundos)->utc();
    }

    /**
     * El instante de un texto ISO 8601 con desfase, en UTC; null si no lo es, o si la fecha no existe.
     */
    private function instante(mixed $texto): ?CarbonImmutable
    {
        if (! is_string($texto)) {
            return null;
        }

        $instante = DateTimeImmutable::createFromFormat(self::FORMATO, $texto);
        $errores = DateTimeImmutable::getLastErrors();

        if ($instante === false || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))) {
            return null;
        }

        return CarbonImmutable::instance($instante)->utc();
    }

    /**
     * Por qué no se ofrece una ventana bien escrita, del motivo más general al más particular.
     */
    private function motivo(CarbonImmutable $inicio, CarbonImmutable $fin, Plazo $plazo, string $zona): string
    {
        return match (true) {
            $fin->getTimestamp() - $inicio->getTimestamp() !== 3600 => 'La ventana debe durar exactamente una hora.',
            $fin->lte($plazo->ahora) => 'La ventana ya terminó.',
            $inicio->lte($plazo->ahora) => 'La ventana ya empezó.',
            $inicio->lte($plazo->desde) => 'La ventana empieza antes de la anticipación mínima de la plaza.',
            $inicio->setTimezone($zona)->toDateString() > $plazo->ultimoDia => 'La ventana está después de los días que se pueden agendar.',
            default => 'La lavandería no ofrece esa ventana en su horario.',
        };
    }

    private function rechazo(string $motivo): ValidationException
    {
        return ValidationException::withMessages(['ventana' => $motivo]);
    }
}
