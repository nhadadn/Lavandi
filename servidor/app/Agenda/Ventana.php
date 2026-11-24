<?php

namespace App\Agenda;

use Carbon\CarbonImmutable;

/**
 * Una ventana de recolección: exactamente una hora, guardada como instante absoluto y presentada en la zona horaria de
 * la plaza de la lavandería (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7; agenda-E01 y agenda-E02).
 *
 * El inicio y el fin van en UTC, porque así se guardan sin perder el instante: el enlace de valores de Laravel escribe
 * una fecha sin su desfase, y PostgreSQL la lee en la zona de la sesión. Quien guarda la ventana, como
 * pedidos.ventana_inicio y pedidos.ventana_fin de resumen-y-confirmacion, guarda estos dos valores tal cual.
 */
final readonly class Ventana
{
    public CarbonImmutable $inicio;

    public CarbonImmutable $fin;

    /**
     * @param  string  $zonaHoraria  La de la plaza, un nombre de la base de husos IANA.
     */
    public function __construct(CarbonImmutable $inicio, public string $zonaHoraria)
    {
        $this->inicio = $inicio->utc();
        // En UTC no hay cambio de horario: una hora después es siempre una hora absoluta.
        $this->fin = $this->inicio->addHour();
    }

    /**
     * La fecha en que empieza, AAAA-MM-DD, en la hora de la plaza.
     */
    public function fecha(): string
    {
        return $this->inicio->setTimezone($this->zonaHoraria)->toDateString();
    }

    /**
     * Si empieza y termina en estos instantes, se hayan escrito con el desfase que sea.
     */
    public function va(CarbonImmutable $inicio, CarbonImmutable $fin): bool
    {
        return $this->inicio->eq($inicio) && $this->fin->eq($fin);
    }

    /**
     * La ventana como la recibe la app: el inicio y el fin en ISO 8601 con el desfase de la plaza, y la fecha y las
     * horas en la hora de la plaza.
     *
     * @return array{inicio: string, fin: string, fecha: string, hora_inicio: string, hora_fin: string}
     */
    public function toArray(): array
    {
        $inicio = $this->inicio->setTimezone($this->zonaHoraria);
        $fin = $this->fin->setTimezone($this->zonaHoraria);

        return [
            'inicio' => $inicio->toIso8601String(),
            'fin' => $fin->toIso8601String(),
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i'),
            'hora_fin' => $fin->format('H:i'),
        ];
    }
}
