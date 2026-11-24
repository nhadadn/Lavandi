<?php

namespace App\Agenda;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A la plaza le falta, vigente, alguna de las dos claves de su agenda (ParametrosDeAgenda). Sin ellas la agenda no
 * ofrece ni acepta ninguna ventana, porque ningún valor está escrito en el código (agenda-E12). Es un dato que falta
 * cargar, no un error de quien pregunta: responde 503 y queda en el registro.
 */
final class AgendaSinConfigurar extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'La agenda de la plaza no está configurada.'], 503);
    }
}
