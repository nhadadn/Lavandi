<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDelPedido;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un cambio de estado del pedido, en su historial (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, "historial completo";
 * ADR-0016, segunda excepción). Se guarda en la misma transacción que el estado actual de pedidos.
 *
 * Solo se agrega: la base rechaza editarlo o borrarlo con el trigger impedir_cambio() (modelo-E12). id_usuarios es quien
 * hizo el cambio, y queda vacía cuando lo hace el sistema, como la confirmación por el pago.
 */
class PedidoEvento extends Modelo
{
    use HeredaLaPlazaDelPedido;

    protected $table = 'pedido_eventos';

    protected $primaryKey = 'id_pedido_eventos';

    protected $fillable = ['id_plazas', 'id_estados_pedido', 'id_usuarios', 'motivo', 'ocurrido_en'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ocurrido_en' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<EstadoPedido, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoPedido::class, 'id_estados_pedido');
    }

    /**
     * Quien hizo el cambio; null si lo hizo el sistema.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios');
    }
}
