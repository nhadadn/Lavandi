<?php

namespace App\Avisos;

/**
 * Los intentos y las esperas de un envío a FCM que no salió porque Google no respondió (config/avisos.php,
 * avisos.esperas; avisos-E02): el primer intento y uno por cada espera; después del último, el envío queda en
 * failed_jobs. Lo comparten el aviso del pedido (MandarAvisoAlTelefono) y el aviso masivo (MandarAvisoMasivoAlTelefono).
 *
 * Se usa en un trabajo de la cola con InteractsWithQueue, que da attempts().
 */
trait EsperasDeReintento
{
    /**
     * Los intentos antes de dejar el envío en failed_jobs: el primero y uno por cada espera.
     */
    public function tries(): int
    {
        return count($this->esperas()) + 1;
    }

    /**
     * La espera, en segundos, antes del siguiente intento: la de este intento en avisos.esperas, o la última. Si
     * avisos.esperas viene vacía, un minuto.
     */
    private function espera(): int
    {
        $esperas = $this->esperas();

        return $esperas === []
            ? 60
            : $esperas[min(max($this->attempts(), 1), count($esperas)) - 1];
    }

    /**
     * @return list<int>
     */
    private function esperas(): array
    {
        return array_values(array_map('intval', (array) config('avisos.esperas', [])));
    }
}
