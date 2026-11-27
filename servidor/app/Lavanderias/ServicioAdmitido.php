<?php

namespace App\Lavanderias;

use App\Models\TipoServicio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El tipo de un servicio que la lavandería declara: uno de los tres del contrato, activo en el catálogo tipos_servicio
 * y con su unidad de precio definida (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #6; alta-lavanderias-E06).
 *
 * - Un código fuera del catálogo, como el calzado, se rechaza nombrándolo y diciendo cuáles sí se admiten. Agregarlo
 *   es Orden de Cambio (DD-046).
 * - El lavado en seco está en el catálogo, pero su unidad espera DD-010 (alta-lavanderias-E07): no se registra hasta
 *   que ServiciosYVentanas::UNIDAD_POR_TIPO la tenga, en lugar de suponerle un precio por kilo.
 */
final class ServicioAdmitido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tipo = is_string($value)
            ? TipoServicio::query()->where('codigo', $value)->where('activo', true)->first()
            : null;

        if ($tipo === null) {
            $fail(sprintf(
                'El servicio «%s» no se admite. Los servicios son: %s.',
                is_scalar($value) ? (string) $value : '',
                $this->nombresDelCatalogo(),
            ));

            return;
        }

        if (! array_key_exists($tipo->codigo, ServiciosYVentanas::UNIDAD_POR_TIPO)) {
            $fail("{$tipo->nombre} todavía no se puede registrar: falta decidir en qué unidad se cobra.");
        }
    }

    /**
     * "Lavado estándar, Lavado con planchado y Lavado en seco": los del catálogo, en su orden.
     */
    private function nombresDelCatalogo(): string
    {
        $nombres = TipoServicio::query()->orderBy('id_tipos_servicio')->pluck('nombre')->all();
        $ultimo = array_pop($nombres);

        return $nombres === [] ? (string) $ultimo : implode(', ', $nombres).' y '.$ultimo;
    }
}
