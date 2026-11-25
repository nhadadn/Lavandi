<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDelPedido;
use Database\Factories\PedidoAsignacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una oferta de un pedido a un repartidor, con su respuesta (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #2,
 * "Aceptar o rechazar con motivo"; openspec/changes/repartidor/design.md, "Tablas y migración").
 *
 * Nace ofrecida; el repartidor la acepta o la rechaza con su motivo, y la respuesta queda con su hora en respondido_en.
 * created_at es la hora en que se ofreció. Cada intento es una fila.
 *
 * Quién crea la oferta, y desde dónde, espera DD-014 (repartidor-E13): no hay operación ni ruta que asigne. Mientras
 * tanto, las pruebas y el sembrado de desarrollo la crean con su fábrica (PedidoAsignacionFactory).
 *
 * Toma la plaza de su pedido, y la base rechaza un repartidor de otra plaza (modelo-E06).
 */
class PedidoAsignacion extends Modelo
{
    /** @use HasFactory<PedidoAsignacionFactory> */
    use HasFactory;

    use HeredaLaPlazaDelPedido;

    /** Se le ofreció el pedido y todavía no responde. */
    public const OFRECIDO = 'ofrecido';

    /** Lo aceptó: el pedido es suyo y tiene sus botones (repartidor-E15). */
    public const ACEPTADO = 'aceptado';

    /** Lo rechazó con su motivo: el pedido deja de estar asignado a él (repartidor-E16). */
    public const RECHAZADO = 'rechazado';

    protected $table = 'pedido_asignaciones';

    protected $primaryKey = 'id_pedido_asignaciones';

    protected $fillable = ['id_pedidos', 'id_plazas', 'id_repartidores', 'estado', 'motivo', 'respondido_en'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'respondido_en' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * El repartidor al que se ofreció, aunque después se dé de baja: la oferta es historial.
     *
     * @return BelongsTo<Repartidor, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class, 'id_repartidores')->withTrashed();
    }
}
