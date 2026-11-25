<?php

namespace App\Filament\Resources\Usuarios;

use App\Autorizacion\Autorizacion;
use App\Filament\EstadosDelPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Support\Number;

/**
 * El historial de pedidos de la ficha del usuario en el panel, fila por fila (administracion-E01;
 * NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3): cada pedido con su folio, su fecha, su lavandería, su
 * estado y su monto, del más reciente al más antiguo.
 *
 * - Los pedidos salen del componente de autorización (Autorizacion::pedidosDelPanel()): los confirmados de la cuenta, y
 *   ninguno si quien mira no es de administración.
 * - La fecha es la de la recolección en la hora de la plaza, como en el historial del usuario
 *   (openspec/changes/historial-y-calificacion/design.md, decisión 1), escrita DD/MM/AAAA.
 * - El estado es el operativo (App\Filament\EstadosDelPedido).
 * - El monto es el final si ya se pesó y, si no, el estimado, en pesos. Hoy los dos nacen vacíos (DD-012, DD-010), y
 *   entonces va null: la ficha dice "Sin monto".
 */
final class HistorialDelUsuario
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    /**
     * @return list<array{folio: string, fecha: string, lavanderia: string, estado: string, monto: string|null}>
     */
    public function de(Usuario $quienMira, Usuario $usuario): array
    {
        return $this->autorizacion->pedidosDelPanel($quienMira)
            ->where('pedidos.id_usuarios', $usuario->getKey())
            ->delMasRecienteAlMasAntiguo()
            ->with(['plaza', 'lavanderia', 'estado'])
            ->get()
            ->map(fn (Pedido $pedido): array => [
                'folio' => (string) $pedido->folio,
                'fecha' => $pedido->plaza->horaLocal($pedido->ventana_inicio)->format('d/m/Y'),
                'lavanderia' => $pedido->lavanderia->nombre,
                'estado' => EstadosDelPedido::nombre($pedido->estado),
                'monto' => self::monto($pedido),
            ])
            ->values()
            ->all();
    }

    private static function monto(Pedido $pedido): ?string
    {
        $monto = $pedido->monto_final ?? $pedido->monto_estimado;

        return $monto === null ? null : Number::currency((float) $monto, in: 'MXN', locale: 'es_MX');
    }
}
