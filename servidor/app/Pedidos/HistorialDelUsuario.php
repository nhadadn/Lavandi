<?php

namespace App\Pedidos;

use App\Autorizacion\Autorizacion;
use App\Models\Pedido;
use App\Models\Usuario;
use Carbon\CarbonImmutable;

/**
 * El historial de pedidos de un usuario en su ficha de la administración, fila por fila (administracion-E01;
 * NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3): cada pedido con su folio, su fecha, su lavandería, su
 * estado y su monto, del más reciente al más antiguo.
 *
 * - Los pedidos salen del componente de autorización (Autorizacion::pedidosDelPanel()): los confirmados de la cuenta, y
 *   ninguno si quien mira no es de administración.
 * - La fecha es la de la recolección en la hora de la plaza, como en el historial del usuario
 *   (openspec/changes/historial-y-calificacion/design.md, decisión 1).
 * - El estado es el operativo (NombresDelEstado).
 * - El monto es el final si ya se pesó y, si no, el estimado, en pesos con sus dos decimales. Hoy los dos nacen vacíos
 *   (DD-012, DD-010), y entonces va null.
 *
 * Vivía en el panel (App\Filament\Resources\Usuarios\HistorialDelUsuario) y se movió aquí sin cambiar qué pedidos van,
 * en qué orden, ni su fecha, su estado o su monto, para que la ficha del panel y la de la API de la administración den
 * lo mismo (ADR-0022, decisión 9; openspec/changes/administracion-en-la-app/, tarea 7.1). Cómo se escriben la fecha y el
 * monto es de cada lado: el panel, DD/MM/AAAA y en pesos (App\Filament\Resources\Usuarios\HistorialDelUsuario); la API,
 * AAAA-MM-DD y el número tal cual (App\Http\Resources\Administracion\UsuarioDeLaAdministracionResource).
 */
final class HistorialDelUsuario
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    /**
     * @return list<array{folio: string, fecha: CarbonImmutable, lavanderia: string, estado: string, monto: string|null}>
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
                'fecha' => $pedido->plaza->horaLocal($pedido->ventana_inicio),
                'lavanderia' => $pedido->lavanderia->nombre,
                'estado' => NombresDelEstado::nombre($pedido->estado),
                'monto' => $pedido->monto_final ?? $pedido->monto_estimado,
            ])
            ->values()
            ->all();
    }
}
