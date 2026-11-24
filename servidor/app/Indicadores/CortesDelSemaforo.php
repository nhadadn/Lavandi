<?php

namespace App\Indicadores;

/**
 * Los dos cortes del semáforo del ranking de lavanderías, de config/indicadores.php (indicadores-E13). Sus valores los
 * da EL CLIENTE (indicadores-E14; DD-114).
 *
 * Verde desde el corte alto; amarillo desde el corte bajo y por debajo del alto; rojo por debajo del bajo. Un promedio
 * igual a un corte alcanza el color de ese corte.
 */
final readonly class CortesDelSemaforo
{
    /** Las estrellas posibles de una calificación (perfil-lavanderia-E16). */
    private const MINIMO = 1.0;

    private const MAXIMO = 5.0;

    private function __construct(
        public float $alto,
        public float $bajo,
    ) {}

    /**
     * Los cortes de la configuración, o null si falta alguno, si no son números entre 1 y 5 o si el bajo queda arriba
     * del alto: sin cortes que sirvan no hay semáforo.
     */
    public static function deLaConfiguracion(): ?self
    {
        $alto = self::corte(config('indicadores.semaforo.corte_alto'));
        $bajo = self::corte(config('indicadores.semaforo.corte_bajo'));

        if ($alto === null || $bajo === null || $bajo > $alto) {
            return null;
        }

        return new self($alto, $bajo);
    }

    public function color(float $promedio): Semaforo
    {
        return match (true) {
            $promedio >= $this->alto => Semaforo::Verde,
            $promedio >= $this->bajo => Semaforo::Amarillo,
            default => Semaforo::Rojo,
        };
    }

    private static function corte(mixed $valor): ?float
    {
        if (! is_numeric($valor)) {
            return null;
        }

        $corte = (float) $valor;

        return $corte >= self::MINIMO && $corte <= self::MAXIMO ? $corte : null;
    }
}
