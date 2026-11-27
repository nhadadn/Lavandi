<?php

namespace App\Cobro;

use App\Models\EventoPasarela;
use App\Models\Pago;
use App\Pedidos\ConfirmacionDePedido;
use Carbon\CarbonImmutable;

/**
 * Lo que un aviso de la pasarela le hace al cobro de su orden (openspec/changes/pago/design.md, decisiones 3 y 4). Corre
 * dentro de la transacción en que App\Cobro\RecepcionDeAvisos procesa el aviso, una sola vez por aviso.
 *
 * - Aprobado: el cobro pendiente queda pagado, con su pagado_en (pago-E03), y su pedido pasa a Confirmado con la
 *   operación de resumen-y-confirmacion, en la misma transacción (pago-E18). Un cobro fallido también se paga: la
 *   pasarela cobró de verdad, en un intento posterior del mismo checkout.
 * - Rechazado: el cobro pendiente queda fallido, sin pagar, y se puede volver a intentar (pago-E04). Un rechazo que llega
 *   después del pago no lo deshace.
 *
 * El cobro se busca por su orden en la pasarela que mandó el aviso, y se bloquea: dos avisos a la vez del mismo cobro no
 * lo pagan dos veces. Lo que no cambia nada se devuelve como nota, que se guarda en eventos_pasarela.error.
 */
final class EfectoDelAviso
{
    public function __construct(private readonly ConfirmacionDePedido $confirmacion) {}

    /**
     * @return string|null la nota de por qué el aviso no cambió nada, o null
     */
    public function aplicar(Pasarela $pasarela, AvisoDePasarela $aviso): ?string
    {
        if (! $aviso->esDe(EventoPasarela::APROBADO) && ! $aviso->esDe(EventoPasarela::RECHAZADO)) {
            return null;
        }

        $cobro = $aviso->idOrden === null ? null : Pago::query()
            ->where('pasarela', $pasarela->codigo())
            ->where('id_pasarela_orden', $aviso->idOrden)
            ->lockForUpdate()
            ->first();

        if ($cobro === null) {
            return 'No hay un cobro con esa orden.';
        }

        return $aviso->esDe(EventoPasarela::APROBADO) ? $this->aprobar($cobro) : $this->rechazar($cobro);
    }

    private function aprobar(Pago $cobro): ?string
    {
        if ($cobro->estado === Pago::PAGADO) {
            return null;
        }

        if (! in_array($cobro->estado, [Pago::PENDIENTE, Pago::FALLIDO], true)) {
            return "El cobro está {$cobro->estado}: el pago no se aplicó.";
        }

        $cobro->forceFill(['estado' => Pago::PAGADO, 'pagado_en' => CarbonImmutable::now()->utc()])->save();

        // El cobro pagado confirma su pedido; un ajuste (DD-012) no.
        if ($cobro->tipo === Pago::COBRO) {
            $this->confirmacion->confirmar($cobro->pedido);
        }

        return null;
    }

    private function rechazar(Pago $cobro): ?string
    {
        if ($cobro->estado === Pago::PENDIENTE) {
            $cobro->forceFill(['estado' => Pago::FALLIDO])->save();
        }

        return null;
    }
}
