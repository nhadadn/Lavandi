<?php

namespace App\Soporte;

use App\Autorizacion\Autorizacion;
use App\Models\Pedido;
use App\Models\ReporteSoporte;
use App\Models\Usuario;
use App\Pedidos\PedidoNoVisible;
use Illuminate\Support\Str;

/**
 * Guarda el reporte del formulario de Soporte, ligado a la cuenta y, si ella quiere, a uno de sus pedidos
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #16, "formulario de reporte"; perfil-usuario-E11, E13 y E17;
 * openspec/changes/perfil-y-soporte/design.md, decisión 3).
 *
 * Qué pedidos y qué direcciones son de la cuenta lo dice el componente de autorización: un pedido de otra cuenta
 * responde igual que uno que no existe (ADR-0019, decisión 3).
 *
 * La plaza del reporte (decisión 27 de acta-2026-11-20-modelo-y-pasarela), porque la cuenta no lleva plaza:
 *
 * 1. con pedido, la del pedido;
 * 2. sin pedido, la del último pedido de la cuenta;
 * 3. sin pedidos, la de su dirección guardada más reciente que tiene plaza: una borrada ya no está guardada, y una
 *    fuera de toda zona no tiene plaza que dar;
 * 4. sin ninguna, se guarda igual, sin plaza: el contrato pide el formulario sin condiciones (perfil-usuario-E17). Ese
 *    reporte no es de ninguna plaza, y del componente de autorización solo lo recibe la administración, que las ve
 *    todas (Autorizacion::reportesDeSoporteDelPanel()).
 *
 * A quién le llega el reporte es DD-108 (perfil-usuario-E14): aquí solo se guarda.
 */
final class RecepcionDeReporte
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    /**
     * @param  string|null  $pedido  El id_publico de un pedido de la cuenta, o null si no eligió ninguno.
     *
     * @throws PedidoNoVisible
     */
    public function recibir(Usuario $usuario, string $descripcion, ?string $pedido): ReporteSoporte
    {
        $delPedido = $pedido === null ? null : $this->pedidoDeLaCuenta($usuario, $pedido);

        return ReporteSoporte::create([
            'id_usuarios' => $usuario->getKey(),
            'id_pedidos' => $delPedido?->getKey(),
            'id_plazas' => $delPedido?->id_plazas ?? $this->plazaDeLaCuenta($usuario),
            'mensaje' => $descripcion,
        ])->setRelation('pedido', $delPedido);
    }

    private function pedidoDeLaCuenta(Usuario $usuario, string $pedido): Pedido
    {
        if (! Str::isUlid($pedido)) {
            throw new PedidoNoVisible;
        }

        return $this->autorizacion->pedidosVisibles($usuario)
            // HasUlids guarda el ULID en minúsculas.
            ->where('id_publico', Str::lower($pedido))
            ->first() ?? throw new PedidoNoVisible;
    }

    /**
     * La plaza del último pedido de la cuenta o, si no tiene, la de su dirección guardada más reciente con plaza; sin
     * ninguna de las dos, null.
     */
    private function plazaDeLaCuenta(Usuario $usuario): ?int
    {
        $plaza = $this->autorizacion->pedidosVisibles($usuario)
            ->latest('created_at')
            ->latest('id_pedidos')
            ->value('id_plazas');

        return $plaza ?? $this->autorizacion->direccionesVisibles($usuario)
            ->whereNotNull('id_plazas')
            ->latest('created_at')
            ->latest('id_direcciones')
            ->value('id_plazas');
    }
}
