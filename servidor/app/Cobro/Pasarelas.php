<?php

namespace App\Cobro;

/**
 * Elige la pasarela de cada entorno, por el código de la configuración (config/pasarela.php; pago-E17; ADR-0012).
 *
 * - conekta, la del contrato (ADR-0010), existe en todos los entornos: si cobra en su sandbox o en producción lo dice
 *   su llave (App\Cobro\PasarelaConekta).
 * - La simulada solo existe en local y en las pruebas: en cualquier otro entorno no se usa aunque esté configurada y
 *   tenga su llave, para que un aviso simulado nunca valga como pago (openspec/changes/pago/design.md, "Decisiones de la
 *   implementación", punto 3).
 */
final class Pasarelas
{
    /** Los entornos donde existe la pasarela simulada. */
    private const ENTORNOS_DE_LA_SIMULADA = ['local', 'testing'];

    /**
     * La pasarela que cobra en este entorno.
     *
     * @throws PasarelaNoDisponible si no hay una configurada que exista en este entorno
     */
    public function configurada(): Pasarela
    {
        return $this->porCodigo((string) config('pasarela.pasarela'));
    }

    /**
     * @throws PasarelaNoDisponible si no existe una pasarela con ese código en este entorno
     */
    public function porCodigo(string $codigo): Pasarela
    {
        return match ($codigo) {
            PasarelaConekta::CODIGO => app(PasarelaConekta::class),
            PasarelaSimulada::CODIGO => $this->simulada(),
            default => throw new PasarelaNoDisponible("No hay una pasarela configurada con el código «{$codigo}» (config/pasarela.php)."),
        };
    }

    private function simulada(): PasarelaSimulada
    {
        if (! app()->environment(self::ENTORNOS_DE_LA_SIMULADA)) {
            throw new PasarelaNoDisponible('La pasarela simulada solo existe en local y en las pruebas.');
        }

        return app(PasarelaSimulada::class);
    }
}
