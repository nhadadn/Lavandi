<?php

namespace App\Cobro;

use App\Models\Pago;

/**
 * La dirección de regreso a la app desde el checkout, con el resultado y el cobro (openspec/changes/pago/design.md,
 * "Contrato de la API"; adenda del 2026-11-22 de ADR-0010). Sale de PASARELA_REGRESO, el esquema de la app de la
 * configuración, nunca del código (CLAUDE.md §5):
 *
 *     <regreso>?resultado=exito&pago=<id_publico del cobro>
 *     <regreso>?resultado=error&pago=<id_publico del cobro>
 *
 * La usan la página de checkout de desarrollo y, con la sección 3, las direcciones de éxito y de error del checkout de
 * Conekta. Regresar por ella no confirma ningún pago: la app consulta el cobro (pago-E07 y pago-E08).
 */
final class DireccionDeRegreso
{
    public function exito(Pago $cobro): ?string
    {
        return $this->con('exito', $cobro);
    }

    public function error(Pago $cobro): ?string
    {
        return $this->con('error', $cobro);
    }

    private function con(string $resultado, Pago $cobro): ?string
    {
        $regreso = (string) config('pasarela.regreso');

        if ($regreso === '') {
            return null;
        }

        $separador = str_contains($regreso, '?') ? '&' : '?';

        return $regreso.$separador.http_build_query(['resultado' => $resultado, 'pago' => $cobro->id_publico]);
    }
}
