<?php

namespace App\Avisos;

use App\Autorizacion\Autorizacion;
use App\Avisos\Fcm\CanalDeFcm;
use App\Avisos\Fcm\FcmNoDisponible;
use App\Avisos\Fcm\TokenRechazado;
use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * El envío de un mensaje del pedido a un teléfono, en cola: el botón de la lavandería no espera a Google
 * (openspec/changes/avisos/design.md, "El envío va en cola").
 *
 * Al correr, vuelve a preguntar a App\Autorizacion\Autorizacion si el teléfono todavía recibe los avisos de ese pedido:
 * si se cerró la sesión, se borró el token o el teléfono pasó a otra cuenta mientras el envío esperaba en la cola, no
 * se manda nada (avisos-E05 y avisos-E21).
 *
 * Si FCM dice que el token ya no sirve, el teléfono se borra y no se reintenta (avisos-E06).
 *
 * Un fallo de FCM nunca sale de aquí (avisos-E02; ADR-0008): queda como advertencia en la bitácora, con el pedido, el
 * estado y el motivo, y sin el token del teléfono. Si puede pasar solo, el envío vuelve a la cola con la espera de
 * avisos.esperas; después del último intento queda en failed_jobs. Así el cambio de estado nunca se detiene, corra la
 * cola en un proceso aparte o en la misma petición (QUEUE_CONNECTION=sync).
 */
final class MandarAvisoAlTelefono implements ShouldQueue
{
    use Dispatchable;
    use EsperasDeReintento;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $estado  El código del estado nuevo del pedido, el del cambio que se avisa.
     * @param  int  $telefono  La llave del teléfono en dispositivos.
     */
    public function __construct(
        public readonly Pedido $pedido,
        public readonly string $estado,
        public readonly int $telefono,
    ) {}

    public function handle(Autorizacion $autorizacion, CanalDeFcm $canal): void
    {
        $telefono = $autorizacion->telefonosQueRecibenAvisosDe($this->pedido)->find($this->telefono);

        if ($telefono === null) {
            return;
        }

        try {
            $canal->mandar($telefono->token_fcm, MensajeDelPedido::armar($this->pedido, $this->estado));
        } catch (TokenRechazado $rechazo) {
            // El teléfono ya no recibe avisos con ese token: se borra y no se reintenta (avisos-E06).
            $telefono->delete();

            Log::info('Se borró un teléfono porque FCM rechazó su token.', [
                'pedido' => $this->pedido->id_publico,
                'estado' => $this->estado,
                'telefono' => $telefono->getKey(),
                'codigo' => $rechazo->codigo,
            ]);
        } catch (FcmNoDisponible $falla) {
            Log::warning('El aviso del pedido no salió por Firebase Cloud Messaging.', [
                'pedido' => $this->pedido->id_publico,
                'estado' => $this->estado,
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
