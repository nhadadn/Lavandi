<?php

namespace App\Cobro;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * El aviso no trae la prueba de que viene de la pasarela configurada: sin firma, con otra, o con la de otro cuerpo
 * (pago-E07; ADR-0010, decisión 2). No se guarda ni cambia nada; responde 401, nunca 200.
 */
final class AvisoNoAutentico extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El aviso no viene de la pasarela.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'El aviso no viene de la pasarela.'], 401);
    }
}
