<?php

namespace App\Console\Commands;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Cobro\PasarelaNoDisponible;
use App\Models\Pedido;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Herramienta de desarrollo: le crea a un pedido el cobro pendiente con tarjeta, en la pasarela simulada, por un monto
 * ficticio (openspec/changes/pago/design.md, "Decisiones de la implementación"). En el flujo real ese cobro lo creará
 * pago-E14, cuando DD-012 fije el monto; mientras tanto, sin él la app no puede pedir el checkout.
 *
 *     php artisan pago:cobro-de-prueba 01jd8x6m3v2c9q4w5e7r8t0y1z 150
 *
 * Solo corre en local y en las pruebas, y necesita la pasarela simulada (PASARELA=simulada y PASARELA_SIMULADA_LLAVE).
 * No es una ruta ni una pantalla del producto.
 */
class CobroDePrueba extends Command
{
    /** Los entornos donde corre. */
    private const ENTORNOS = ['local', 'testing'];

    protected $signature = 'pago:cobro-de-prueba
        {pedido : El id_publico del pedido, en el estado previo al pago}
        {monto : El monto ficticio del cobro, en pesos, mayor que cero}';

    protected $description = 'Desarrollo: le crea a un pedido su cobro pendiente en la pasarela simulada, por un monto ficticio';

    public function handle(CobroSimulado $cobroSimulado): int
    {
        if (! app()->environment(self::ENTORNOS)) {
            $this->components->error('Este comando solo corre en local y en las pruebas.');

            return self::FAILURE;
        }

        $monto = (string) $this->argument('monto');

        if (! is_numeric($monto) || (float) $monto <= 0) {
            $this->components->error('El monto debe ser un número mayor que cero, en pesos.');

            return self::FAILURE;
        }

        $pedido = Pedido::query()->where('id_publico', Str::lower((string) $this->argument('pedido')))->first();

        if ($pedido === null) {
            $this->components->error('No existe el pedido.');

            return self::FAILURE;
        }

        if ($pedido->cobro()->exists() || $pedido->confirmado_en !== null) {
            $this->components->error('El pedido ya tiene su cobro.');

            return self::FAILURE;
        }

        try {
            $cobro = $cobroSimulado->prepararCobro($pedido, number_format((float) $monto, 2, '.', ''));
        } catch (PasarelaNoDisponible $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Cobro pendiente de \${$cobro->monto} para el pedido {$pedido->id_publico}: {$cobro->id_publico}.");

        return self::SUCCESS;
    }
}
