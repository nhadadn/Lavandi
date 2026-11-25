<?php

namespace App\Http\Controllers\Api;

use App\Cobro\RecepcionDeAvisos;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El webhook de la pasarela de pago (openspec/changes/pago/; ADR-0010, decisión 2). No lleva sesión de usuario: no hay
 * cuenta detrás, lo manda la pasarela. Lo declara público tests/Feature/Registro/AutorizacionTest.php (autorizacion-E01).
 *
 * POST /api/pasarela/avisos
 *
 *     Cuerpo y cabeceras, los de la pasarela configurada en el entorno (config/pasarela.php). Se lee el cuerpo tal como
 *     llega, sin decodificarlo antes.
 *
 *     200  { "recibido": true }: el aviso quedó guardado y procesado, o ya lo estaba (pago-E10)
 *     401  { "message": "El aviso no viene de la pasarela." }: sin su prueba de autenticidad; no se guarda (pago-E07)
 *     400  { "message": "El aviso no se reconoce." }: auténtico, pero no es un aviso que se entienda; no se guarda
 *     500  el procesamiento falló: el aviso queda guardado con su error y la pasarela lo reintenta
 *     503  { "message": "El pago no está disponible por ahora." }: sin pasarela en el entorno
 *
 * Todo lo hace App\Cobro\RecepcionDeAvisos.
 */
class AvisosDeLaPasarelaController extends Controller
{
    public function __invoke(Request $peticion, RecepcionDeAvisos $recepcion): JsonResponse
    {
        $recepcion->recibir($peticion->getContent(), $this->cabeceras($peticion));

        return response()->json(['recibido' => true]);
    }

    /**
     * Las cabeceras de la petición, por nombre en minúsculas, con su primer valor.
     *
     * @return array<string, string>
     */
    private function cabeceras(Request $peticion): array
    {
        return array_map(
            fn (array $valores): string => (string) ($valores[0] ?? ''),
            $peticion->headers->all(),
        );
    }
}
