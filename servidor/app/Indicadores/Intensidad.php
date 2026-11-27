<?php

namespace App\Indicadores;

/**
 * La intensidad de una colonia o de una zona en el mapa de calor (indicadores-E19 e indicadores-E20): su número de
 * pedidos frente al de la que tiene más, de 0 a 1, con dos decimales, y el color que le toca en el mapa.
 *
 * El color sale de una rampa secuencial de un solo tono, de claro a oscuro: el azul de la paleta validada de las
 * gráficas del panel, la misma de PedidosPorEstadoGrafica. Más oscuro es más pedidos, y cada figura del mapa dice además
 * su número: la intensidad nunca va solo con el color.
 */
final class Intensidad
{
    /** Los pasos 150, 300, 400, 550 y 650 de la rampa azul, de menos a más pedidos. */
    public const RAMPA = ['#b7d3f6', '#6da7ec', '#3987e5', '#1c5cab', '#104281'];

    public static function de(int $pedidos, int $maximo): float
    {
        return $maximo <= 0 ? 0.0 : round($pedidos / $maximo, 2);
    }

    /**
     * El color de la rampa para esa intensidad: el paso más cercano.
     */
    public static function color(float $intensidad): string
    {
        $pasos = count(self::RAMPA) - 1;
        $paso = (int) round(max(0.0, min(1.0, $intensidad)) * $pasos);

        return self::RAMPA[$paso];
    }
}
