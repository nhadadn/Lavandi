<?php

namespace App\Models\Concerns;

use App\Models\Pedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una hija de pedidos: un servicio, una dirección copiada o un evento. Al crearse sin plaza, toma la de su pedido, así
 * que $pedido->eventos()->create([...]) basta.
 *
 * Una plaza distinta de la de su pedido no se corrige aquí: la rechaza la base con la llave compuesta
 * (id_pedidos, id_plazas) (modelo-E06).
 */
trait HeredaLaPlazaDelPedido
{
    protected static function bootHeredaLaPlazaDelPedido(): void
    {
        static::creating(function (Model $fila): void {
            if ($fila->getAttribute('id_plazas') === null && $fila->getAttribute('id_pedidos') !== null) {
                $fila->setAttribute('id_plazas', Pedido::query()
                    ->whereKey($fila->getAttribute('id_pedidos'))
                    ->value('id_plazas'));
            }
        });
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedidos');
    }
}
