<?php

namespace App\Agenda;

use Illuminate\Support\Collection;

/**
 * Las ventanas que la agenda ofrece en una fecha de la plaza, y si ese día de la semana la lavandería no tiene horario
 * (agenda-E04): así la app distingue "ese día no da servicio" de un día sin ventanas por otra razón. Trae también el
 * primer y el último día que se pueden agendar (agenda-E12), para que la app sepa entre qué días navegar.
 */
final readonly class DiaDeAgenda
{
    /**
     * @param  string  $fecha  AAAA-MM-DD, en la hora de la plaza.
     * @param  Collection<int, Ventana>  $ventanas  De la más temprana a la más tarde.
     * @param  string  $primerDia  Hoy en la plaza, AAAA-MM-DD.
     * @param  string  $ultimoDia  Hoy más los días hacia adelante, AAAA-MM-DD.
     */
    public function __construct(
        public string $fecha,
        public string $zonaHoraria,
        public bool $sinServicio,
        public Collection $ventanas,
        public string $primerDia,
        public string $ultimoDia,
    ) {}

    /**
     * @return array{
     *     fecha: string,
     *     zona_horaria: string,
     *     primer_dia: string,
     *     ultimo_dia: string,
     *     dia_sin_servicio: bool,
     *     ventanas: list<array<string, string>>
     * }
     */
    public function toArray(): array
    {
        return [
            'fecha' => $this->fecha,
            'zona_horaria' => $this->zonaHoraria,
            'primer_dia' => $this->primerDia,
            'ultimo_dia' => $this->ultimoDia,
            'dia_sin_servicio' => $this->sinServicio,
            'ventanas' => $this->ventanas->map(fn (Ventana $ventana): array => $ventana->toArray())->values()->all(),
        ];
    }
}
