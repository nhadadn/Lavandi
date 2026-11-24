<?php

namespace App\Calificaciones;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * El pedido propio no se califica, por la lectura provisional de BR-25: todavía no está Entregado, o ya está calificado
 * (openspec/changes/historial-y-calificacion/design.md, "La calificación: lectura provisional"). No se guardó nada.
 *
 * Responde 409. Un pedido ajeno nunca llega aquí: responde 404 antes, por el componente de autorización (ADR-0019,
 * decisión 3).
 */
final class CalificacionNoPermitida extends RuntimeException
{
    public static function todaviaNo(): self
    {
        return new self('Este pedido todavía no se puede calificar.');
    }

    public static function yaCalificado(): self
    {
        return new self('Este pedido ya está calificado.');
    }

    public function report(): void
    {
        //
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
