<?php

namespace App\Agenda;

use App\Models\Plaza;
use Carbon\CarbonImmutable;

/**
 * Qué ventanas se pueden agendar en la plaza en este momento: las que empiezan después de ahora más la anticipación
 * mínima (agenda-E11), y de hoy hasta hoy más los días hacia adelante, con hoy en la hora de la plaza (agenda-E12).
 *
 * Una ventana que empieza en este mismo instante ya empezó: el inicio tiene que ser posterior, no igual.
 */
final readonly class Plazo
{
    /**
     * @param  CarbonImmutable  $ahora  En UTC.
     * @param  CarbonImmutable  $desde  Ahora más la anticipación mínima, en UTC.
     * @param  string  $primerDia  Hoy en la plaza, AAAA-MM-DD.
     * @param  string  $ultimoDia  Hoy más los días hacia adelante, AAAA-MM-DD.
     */
    public function __construct(
        public CarbonImmutable $ahora,
        public CarbonImmutable $desde,
        public string $primerDia,
        public string $ultimoDia,
    ) {}

    /**
     * @throws AgendaSinConfigurar
     */
    public static function deLaPlaza(Plaza $plaza): self
    {
        $ahora = CarbonImmutable::now()->utc();
        $parametros = ParametrosDeAgenda::deLaPlaza($plaza, $ahora);
        $hoy = $ahora->setTimezone($plaza->zona_horaria);

        return new self(
            ahora: $ahora,
            desde: $ahora->addMinutes($parametros->anticipacionMinutos),
            primerDia: $hoy->toDateString(),
            ultimoDia: $hoy->addDays($parametros->diasHaciaAdelante)->toDateString(),
        );
    }

    public function admite(Ventana $ventana): bool
    {
        return $ventana->inicio->gt($this->desde) && $ventana->fecha() <= $this->ultimoDia;
    }
}
