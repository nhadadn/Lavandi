<?php

namespace App\Console\Commands;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Cobro\PasarelaNoDisponible;
use App\Models\EventoPasarela;
use App\Models\Pedido;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Herramienta de desarrollo: la pasarela simulada manda su aviso de pago aprobado o rechazado para el cobro de un pedido,
 * por el mismo camino del webhook (App\Cobro\RecepcionDeAvisos), para probar el flujo en local sin pasarela y sin el
 * teléfono (openspec/changes/pago/design.md, "Decisiones de la implementación"). Si el cobro todavía no tiene checkout,
 * lo abre antes, como lo pide la app.
 *
 *     php artisan pago:simular-aviso 01jd8x6m3v2c9q4w5e7r8t0y1z aprobado
 *     php artisan pago:simular-aviso 01jd8x6m3v2c9q4w5e7r8t0y1z rechazado
 *
 * Solo corre en local y en las pruebas, y necesita la pasarela simulada. El cobro, si el pedido no lo tiene, se crea
 * antes con pago:cobro-de-prueba. No es una ruta ni una pantalla del producto.
 */
class SimularAvisoDePago extends Command
{
    /** Los entornos donde corre. */
    private const ENTORNOS = ['local', 'testing'];

    /** Los resultados que manda. */
    private const RESULTADOS = [EventoPasarela::APROBADO, EventoPasarela::RECHAZADO];

    protected $signature = 'pago:simular-aviso
        {pedido : El id_publico del pedido}
        {resultado : aprobado o rechazado}';

    protected $description = 'Desarrollo: manda el aviso simulado de pago aprobado o rechazado de un pedido por el camino del webhook';

    public function handle(CobroSimulado $cobroSimulado): int
    {
        if (! app()->environment(self::ENTORNOS)) {
            $this->components->error('Este comando solo corre en local y en las pruebas.');

            return self::FAILURE;
        }

        $resultado = (string) $this->argument('resultado');

        if (! in_array($resultado, self::RESULTADOS, true)) {
            $this->components->error('El resultado es aprobado o rechazado.');

            return self::FAILURE;
        }

        $pedido = Pedido::query()->where('id_publico', Str::lower((string) $this->argument('pedido')))->first();

        if ($pedido === null) {
            $this->components->error('No existe el pedido.');

            return self::FAILURE;
        }

        $cobro = $pedido->cobro()->first();

        if ($cobro === null) {
            $this->components->error('El pedido no tiene cobro: créalo antes con pago:cobro-de-prueba.');

            return self::FAILURE;
        }

        try {
            $evento = $cobroSimulado->avisar($cobro, $resultado);
        } catch (PasarelaNoDisponible $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }

        $pedido = $pedido->fresh();
        $this->components->info("Aviso {$resultado} {$evento->id_pasarela_evento}: el cobro quedó {$cobro->fresh()->estado}.");
        $this->components->info(sprintf(
            'El pedido quedó %s%s.',
            $pedido->estado->codigo,
            $pedido->folio === null ? ', sin folio' : ", con el folio {$pedido->folio}",
        ));

        if ($evento->error !== null) {
            $this->components->warn($evento->error);
        }

        return self::SUCCESS;
    }
}
