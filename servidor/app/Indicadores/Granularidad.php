<?php

namespace App\Indicadores;

use Carbon\CarbonImmutable;

/**
 * Cómo se agrupa un indicador en el tiempo: por día, por semana o por mes (NSV-2026-001-v8.pdf:22:ANEXO A, Sección
 * A.5: "por día, semana y mes"). La semana va de lunes a domingo, ISO 8601 (openspec/changes/indicadores/design.md,
 * pregunta 3).
 *
 * Trabaja con días del calendario, sin hora ni zona horaria: el día de cada fila ya viene en la hora de su plaza.
 */
enum Granularidad: string
{
    case Dia = 'dia';
    case Semana = 'semana';
    case Mes = 'mes';

    /**
     * Las opciones del filtro de la página, con su nombre.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return array_column(array_map(
            fn (self $granularidad): array => [$granularidad->value, $granularidad->nombre()],
            self::cases(),
        ), 1, 0);
    }

    public function nombre(): string
    {
        return match ($this) {
            self::Dia => 'Día',
            self::Semana => 'Semana',
            self::Mes => 'Mes',
        };
    }

    /**
     * La unidad de date_trunc() de PostgreSQL. Su 'week' empieza en lunes, como la semana de aquí.
     */
    public function unidadSql(): string
    {
        return match ($this) {
            self::Dia => 'day',
            self::Semana => 'week',
            self::Mes => 'month',
        };
    }

    /**
     * El primer día del periodo que contiene esa fecha.
     */
    public function inicioDe(CarbonImmutable $fecha): CarbonImmutable
    {
        return match ($this) {
            self::Dia => $fecha->startOfDay(),
            self::Semana => $fecha->startOfWeek(CarbonImmutable::MONDAY),
            self::Mes => $fecha->startOfMonth(),
        };
    }

    /**
     * El último día del periodo que empieza en ese día.
     */
    public function finDe(CarbonImmutable $inicio): CarbonImmutable
    {
        return $this->siguiente($inicio)->subDay();
    }

    public function siguiente(CarbonImmutable $inicio): CarbonImmutable
    {
        return match ($this) {
            self::Dia => $inicio->addDay(),
            self::Semana => $inicio->addWeek(),
            self::Mes => $inicio->addMonthNoOverflow(),
        };
    }

    public function anterior(CarbonImmutable $inicio): CarbonImmutable
    {
        return match ($this) {
            self::Dia => $inicio->subDay(),
            self::Semana => $inicio->subWeek(),
            self::Mes => $inicio->subMonthNoOverflow(),
        };
    }

    /**
     * Cómo se lee el periodo que empieza en ese día: "lun 30/11/2026", "Semana del 30/11/2026", "noviembre 2026".
     */
    public function etiqueta(CarbonImmutable $inicio): string
    {
        $fecha = $inicio->locale('es');

        return match ($this) {
            self::Dia => $fecha->isoFormat('ddd DD/MM/YYYY'),
            self::Semana => 'Semana del '.$fecha->isoFormat('DD/MM/YYYY'),
            self::Mes => $fecha->isoFormat('MMMM YYYY'),
        };
    }
}
