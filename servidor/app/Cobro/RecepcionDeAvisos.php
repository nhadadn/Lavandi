<?php

namespace App\Cobro;

use App\Models\EventoPasarela;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recibe los avisos (webhooks) de la pasarela configurada (openspec/changes/pago/design.md, decisiones 3 y 4; ADR-0010,
 * decisión 2). Es el único camino por el que un aviso cambia algo, el de la ruta del webhook y el de las herramientas de
 * desarrollo con la pasarela simulada.
 *
 * 1. La pasarela comprueba que el aviso es suyo (pago-E07): uno que no lo es no se guarda ni cambia nada. Después lo
 *    traduce a un evento sin proveedor.
 * 2. Se guarda en eventos_pasarela, sin datos de tarjeta. Si ya estaba, por el único (pasarela, id_pasarela_evento), no
 *    se guarda otra vez (pago-E10).
 * 3. Si todavía no se procesó, se procesa una vez, en una transacción: App\Cobro\EfectoDelAviso le aplica al cobro de su
 *    orden lo que dice el aviso. Si falla, el aviso queda con su error y sin procesar, y el error sube: la respuesta no
 *    es 200 y la pasarela lo reintenta.
 */
final class RecepcionDeAvisos
{
    public function __construct(
        private readonly Pasarelas $pasarelas,
        private readonly EfectoDelAviso $efecto,
    ) {}

    /**
     * @param  array<string, string>  $cabeceras  por nombre en minúsculas
     *
     * @throws AvisoNoAutentico si el aviso no trae la prueba de que viene de la pasarela
     * @throws AvisoNoReconocido si la pasarela no reconoce el aviso
     * @throws PasarelaNoDisponible si no hay pasarela en este entorno
     */
    public function recibir(string $cuerpo, array $cabeceras): EventoPasarela
    {
        $pasarela = $this->pasarelas->configurada();

        if (! $pasarela->esAutentico($cuerpo, $cabeceras)) {
            throw new AvisoNoAutentico;
        }

        $aviso = $pasarela->traducir($cuerpo);
        $evento = $this->registrar($pasarela, $aviso);

        if ($evento->procesado_en === null) {
            $this->procesar($evento, $pasarela, $aviso);
        }

        return $evento->refresh();
    }

    /**
     * El aviso guardado: el que se guarda ahora, o el que ya estaba con el mismo identificador en esa pasarela. Lo decide
     * el único de la base, también si llegan dos iguales a la vez.
     */
    private function registrar(Pasarela $pasarela, AvisoDePasarela $aviso): EventoPasarela
    {
        return EventoPasarela::query()->createOrFirst(
            ['pasarela' => $pasarela->codigo(), 'id_pasarela_evento' => $aviso->idEvento],
            [
                'tipo' => $aviso->tipo,
                'contenido' => SinDatosDeTarjeta::limpiar($aviso->contenido),
                'recibido_en' => $this->ahora(),
            ],
        );
    }

    /**
     * Procesa el aviso una vez: lo bloquea, y si otro ya lo procesó, no hace nada.
     *
     * @throws Throwable lo que haya fallado, después de guardar el error en el aviso
     */
    private function procesar(EventoPasarela $evento, Pasarela $pasarela, AvisoDePasarela $aviso): void
    {
        try {
            DB::transaction(function () use ($evento, $pasarela, $aviso): void {
                $bloqueado = EventoPasarela::query()->whereKey($evento->getKey())->lockForUpdate()->sole();

                if ($bloqueado->procesado_en !== null) {
                    return;
                }

                $nota = $this->efecto->aplicar($pasarela, $aviso);

                $bloqueado->forceFill(['procesado_en' => $this->ahora(), 'error' => $nota])->save();
            });
        } catch (Throwable $error) {
            $evento->forceFill(['error' => Str::limit($error->getMessage(), 1000)])->save();

            throw $error;
        }
    }

    /** En UTC, como toda fecha que se guarda. */
    private function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now()->utc();
    }
}
