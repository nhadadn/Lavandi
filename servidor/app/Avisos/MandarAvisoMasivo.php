<?php

namespace App\Avisos;

use App\Autorizacion\Autorizacion;
use App\Models\AvisoMasivo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * La salida de un aviso masivo, en cola: el panel guarda el aviso y no espera a Google (administracion-E23;
 * openspec/changes/administracion/design.md, "Risks / Trade-offs").
 *
 * Al correr, pregunta a App\Autorizacion\Autorizacion qué teléfonos reciben el aviso, guarda en destinatarios a cuántos
 * se manda y pone en cola un envío por cada uno (MandarAvisoMasivoAlTelefono), con sus propios reintentos. FCM HTTP v1
 * manda a un token por petición (adenda de ADR-0008), así que un teléfono que falla no detiene a los demás.
 *
 * Corre una sola vez: repetirlo pondría en cola los envíos otra vez y el aviso llegaría dos veces.
 */
final class MandarAvisoMasivo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly AvisoMasivo $aviso) {}

    public function handle(Autorizacion $autorizacion): void
    {
        $telefonos = $autorizacion->telefonosQueRecibenElAvisoMasivo($this->aviso)
            ->orderBy('id_dispositivos')
            ->pluck('id_dispositivos');

        $this->aviso->forceFill(['destinatarios' => $telefonos->count()])->save();

        $telefonos->each(fn (int $telefono) => MandarAvisoMasivoAlTelefono::dispatch($this->aviso, $telefono));
    }
}
