<?php

namespace App\Indicadores;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * El rango y la granularidad que elige la administración en la página de indicadores
 * (openspec/changes/indicadores/tasks.md, tarea 1.1).
 *
 * El rango se ajusta a periodos completos: por semana, del lunes de la semana del primer día al domingo de la del
 * último; por mes, del día 1 del primer mes al último día del último. Así cada periodo se compara entero con el
 * anterior. Los dos días son del calendario, sin hora: el día de cada pedido ya viene en la hora de su plaza.
 *
 * Sin filtros, es el mes en curso hasta hoy, por día. Un día que no se entiende se toma como no dado, y un rango al
 * revés se voltea.
 */
final readonly class Rango
{
    private function __construct(
        public CarbonImmutable $desde,
        public CarbonImmutable $hasta,
        public Granularidad $granularidad,
    ) {}

    /**
     * El rango de los filtros de la página: desde, hasta y granularidad, como los guarda su formulario.
     *
     * @param  array<string, mixed>|null  $filtros
     */
    public static function deLosFiltros(?array $filtros, ?CarbonImmutable $hoy = null): self
    {
        $hoy = self::dia($hoy?->toDateString()) ?? self::hoy();
        $granularidad = Granularidad::tryFrom((string) ($filtros['granularidad'] ?? '')) ?? Granularidad::Dia;
        $desde = self::dia($filtros['desde'] ?? null) ?? self::desdePorOmision($hoy);
        $hasta = self::dia($filtros['hasta'] ?? null) ?? $hoy;

        if ($desde->greaterThan($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return new self(
            $granularidad->inicioDe($desde),
            $granularidad->finDe($granularidad->inicioDe($hasta)),
            $granularidad,
        );
    }

    /**
     * Hoy, como día del calendario, en la zona horaria de la aplicación: las cuentas no tienen plaza
     * (design.md, pregunta 8).
     */
    public static function hoy(): CarbonImmutable
    {
        return self::dia(CarbonImmutable::now(config('app.timezone'))->toDateString());
    }

    /**
     * El primer día del rango sin filtros: el día 1 del mes de hoy.
     */
    public static function desdePorOmision(?CarbonImmutable $hoy = null): CarbonImmutable
    {
        return ($hoy ?? self::hoy())->startOfMonth();
    }

    /**
     * El primer día de cada periodo del rango, en orden.
     *
     * @return list<CarbonImmutable>
     */
    public function periodos(): array
    {
        $periodos = [];

        for ($inicio = $this->desde; $inicio->lessThanOrEqualTo($this->hasta); $inicio = $this->granularidad->siguiente($inicio)) {
            $periodos[] = $inicio;
        }

        return $periodos;
    }

    /**
     * El primer día del periodo anterior al primero del rango: el que va junto al primero en la tabla comparativa.
     */
    public function inicioDelAnterior(): CarbonImmutable
    {
        return $this->granularidad->anterior($this->desde);
    }

    /**
     * Los mismos días del rango, justo antes de que empiece: el periodo anterior de la misma duración, para la tendencia
     * del indicador destacado (design.md, lectura 4; indicadores-E09). Lleva la misma granularidad.
     */
    public function anteriorDeLaMismaDuracion(): self
    {
        $dias = (int) $this->desde->diffInDays($this->hasta) + 1;

        return new self($this->desde->subDays($dias), $this->desde->subDay(), $this->granularidad);
    }

    /**
     * Cómo se lee el rango con su granularidad: "Del 30/11/2026 al 06/12/2026, por día".
     */
    public function descripcion(): string
    {
        return $this->dias().', por '.mb_strtolower($this->granularidad->nombre());
    }

    /**
     * Cómo se leen solo los días del rango, para el indicador que no se agrupa por periodo: "Del 30/11/2026 al
     * 06/12/2026".
     */
    public function dias(): string
    {
        return sprintf('Del %s al %s', $this->desde->format('d/m/Y'), $this->hasta->format('d/m/Y'));
    }

    private static function dia(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || blank($valor)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', substr(trim($valor), 0, 10), 'UTC') ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
