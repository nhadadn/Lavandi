<?php

namespace App\Pedidos;

use App\Models\EstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Confirmar el pedido, la segunda de las dos operaciones del pedido en la API
 * (openspec/changes/resumen-y-confirmacion/design.md, decisiones 1, 4, 6 y 7; C-188).
 *
 * Pasa el pedido a Confirmado, con un folio que no se repite en su plaza y con confirmado_en, guarda el evento en la
 * misma transacción y, ya guardado, publica PedidoConfirmado (confirmacion-E13).
 *
 * No la llama la app ni ninguna ruta (confirmacion-E07). La llama el componente cobro cuando la pasarela confirma el pago
 * (ADR-0010, punto 1), en la misma transacción en que el cobro queda pagado (App\Cobro\EfectoDelAviso; pago-E18). Y
 * rechaza el pedido cuyo cobro no está pagado, la llame quien la llame (pago-E19; C-188).
 *
 * Un aviso repetido no confirma dos veces: un pedido que ya tiene confirmado_en se devuelve como está, sin otro folio,
 * otro evento ni otra publicación.
 */
final class ConfirmacionDePedido
{
    /** El único de la base que impide repetir un folio en la plaza (design.md, decisión 6). */
    private const UNICO_DEL_FOLIO = 'pedidos_id_plazas_folio_unique';

    /** Cuántos folios se proponen antes de rendirse, si la base sigue rechazándolos por repetidos. */
    private const INTENTOS = 5;

    public function __construct(private readonly FolioDelPedido $folios) {}

    /**
     * @throws PedidoSinPagoAcreditado si el pedido no tiene su cobro pagado
     * @throws UniqueConstraintViolationException si ninguno de los folios propuestos quedó libre en la plaza
     */
    public function confirmar(Pedido $pedido): Pedido
    {
        return DB::transaction(function () use ($pedido): Pedido {
            // Bloquea el pedido: dos avisos a la vez del mismo cobro no lo confirman dos veces.
            $bloqueado = Pedido::query()->whereKey($pedido->getKey())->lockForUpdate()->sole();

            if ($bloqueado->confirmado_en !== null) {
                return $bloqueado;
            }

            // Sin pago acreditado no hay confirmación (pago-E19): solo el cobro, no un ajuste, confirma el pedido.
            $pagado = Pago::query()
                ->where('id_pedidos', $bloqueado->getKey())
                ->where('tipo', Pago::COBRO)
                ->where('estado', Pago::PAGADO)
                ->exists();

            if (! $pagado) {
                throw new PedidoSinPagoAcreditado;
            }

            $confirmado = EstadoPedido::query()->where('codigo', EstadoPedido::CONFIRMADO)->sole();
            // En UTC, como toda fecha que se guarda (App\Agenda\Ventana).
            $ahora = CarbonImmutable::now()->utc();

            $this->asignarFolio($bloqueado, [
                'id_estados_pedido' => $confirmado->getKey(),
                'confirmado_en' => $ahora,
            ]);

            $bloqueado->eventos()->create([
                'id_estados_pedido' => $confirmado->getKey(),
                // Lo hace el sistema, por el pago: sin cuenta.
                'id_usuarios' => null,
                'ocurrido_en' => $ahora,
            ]);

            PedidoConfirmado::dispatch($bloqueado);

            return $bloqueado;
        });
    }

    /**
     * Guarda el pedido con un folio nuevo y los cambios que se pasan. Si la base rechaza el folio por repetido en la
     * plaza, como cuando otra confirmación al mismo tiempo dio con el mismo, propone otro. Cada intento va en su
     * propio savepoint, para que el rechazo no aborte la transacción de la confirmación.
     *
     * @param  array<string, mixed>  $cambios
     *
     * @throws UniqueConstraintViolationException
     */
    private function asignarFolio(Pedido $pedido, array $cambios): void
    {
        for ($intento = 1; ; $intento++) {
            try {
                DB::transaction(fn () => $pedido->forceFill([...$cambios, 'folio' => $this->folios->nuevo()])->save());

                return;
            } catch (UniqueConstraintViolationException $error) {
                if ($intento >= self::INTENTOS || ! str_contains($error->getMessage(), self::UNICO_DEL_FOLIO)) {
                    throw $error;
                }
            }
        }
    }
}
