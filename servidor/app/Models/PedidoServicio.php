<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDelPedido;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un servicio del pedido, con su unidad y el precio unitario copiado de lavanderia_servicios.precio al crear el pedido:
 * si la lavandería cambia su precio después, el pedido no cambia (ADR-0016, primera excepción).
 *
 * monto_estimado y monto_real son columnas generadas por la base, cantidad por precio unitario: se leen, nunca se
 * escriben. La cantidad estimada espera DD-012; uno o varios servicios por pedido, DD-098.
 */
class PedidoServicio extends Modelo
{
    use HeredaLaPlazaDelPedido;

    protected $table = 'pedido_servicios';

    protected $primaryKey = 'id_pedido_servicios';

    protected $fillable = [
        'id_plazas',
        'id_tipos_servicio',
        'id_unidades_medida',
        'cantidad_estimada',
        'cantidad_real',
        'precio_unitario',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_estimada' => 'decimal:2',
            'cantidad_real' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'monto_estimado' => 'decimal:2',
            'monto_real' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<TipoServicio, $this>
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoServicio::class, 'id_tipos_servicio');
    }

    /**
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidades_medida');
    }
}
