<?php

namespace App\Indicadores;

/**
 * Cómo se agrupa el mapa de calor de los pedidos: por colonia o por zona de servicio, a elección de la administración
 * (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, "Mapa interactivo de calor"; indicadores-E19 e indicadores-E20).
 *
 * La regla vale igual en el widget del mapa del panel y en la API de la administración (ADR-0022, decisión 9): por
 * colonia por omisión, y una agrupación que no existe se toma como por colonia.
 */
enum AgrupacionDelMapa: string
{
    case Colonia = 'colonia';
    case Zona = 'zona';

    /**
     * La agrupación elegida, o por colonia si no se eligió una que exista.
     */
    public static function elegida(?string $valor): self
    {
        return self::tryFrom((string) $valor) ?? self::Colonia;
    }

    /**
     * Las opciones del selector del mapa, con su nombre.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return array_column(array_map(
            fn (self $agrupacion): array => [$agrupacion->value, $agrupacion->nombre()],
            self::cases(),
        ), 1, 0);
    }

    public function nombre(): string
    {
        return match ($this) {
            self::Colonia => 'Colonia',
            self::Zona => 'Zona de servicio',
        };
    }
}
