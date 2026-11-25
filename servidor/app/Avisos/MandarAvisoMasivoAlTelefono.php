<?php

namespace App\Avisos;

use App\Autorizacion\Autorizacion;
use App\Avisos\Fcm\CanalDeFcm;
use App\Avisos\Fcm\FcmNoDisponible;
use App\Avisos\Fcm\TokenRechazado;
use App\Models\AvisoMasivo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * El envío de un aviso masivo a un teléfono, en cola, como el aviso del pedido (MandarAvisoAlTelefono;
 * administracion-E23).
 *
 * - Al correr, vuelve a preguntar a App\Autorizacion\Autorizacion si el teléfono todavía recibe el aviso: si se cerró
 *   la sesión, se borró el token o la cuenta se bloqueó mientras el envío esperaba en la cola, no se manda nada.
 * - Si FCM dice que el token ya no sirve, el teléfono se borra y no se reintenta (avisos-E06).
 * - Un fallo de FCM queda como advertencia en la bitácora, con el aviso y el teléfono por su llave, sin el token. Si
 *   puede pasar solo, el envío vuelve a la cola con las esperas de avisos.esperas.
 */
final class MandarAvisoMasivoAlTelefono implements ShouldQueue
{
    use Dispatchable;
    use EsperasDeReintento;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  int  $telefono  La llave del teléfono en dispositivos.
     */
    public function __construct(
        public readonly AvisoMasivo $aviso,
        public readonly int $telefono,
    ) {}

    public function handle(Autorizacion $autorizacion, CanalDeFcm $canal): void
    {
        $telefono = $autorizacion->telefonosQueRecibenElAvisoMasivo($this->aviso)->find($this->telefono);

        if ($telefono === null) {
            return;
        }

        try {
            $canal->mandar($telefono->token_fcm, MensajeMasivo::armar($this->aviso));
        } catch (TokenRechazado $rechazo) {
            $telefono->delete();

            Log::info('Se borró un teléfono porque FCM rechazó su token.', [
                'aviso_masivo' => $this->aviso->getKey(),
                'telefono' => $telefono->getKey(),
                'codigo' => $rechazo->codigo,
            ]);
        } catch (FcmNoDisponible $falla) {
            Log::warning('El aviso masivo no salió por Firebase Cloud Messaging.', [
                'aviso_masivo' => $this->aviso->getKey(),
                'telefono' => $telefono->getKey(),
                'intento' => $this->attempts(),
                'reintentable' => $falla->reintentable,
                'motivo' => $falla->getMessage(),
            ]);

            if ($falla->reintentable) {
                $this->release($this->espera());
            }
        }
    }
}
