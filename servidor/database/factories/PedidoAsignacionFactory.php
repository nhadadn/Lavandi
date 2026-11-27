<?php

namespace Database\Factories;

use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\Repartidor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Las asignaciones de las pruebas y del sembrado de desarrollo. Quién asigna un pedido a un repartidor, y desde dónde,
 * espera DD-014 (repartidor-E13), así que no hay operación de asignar: mientras tanto, la oferta se crea directo en la
 * base con esta fábrica (openspec/changes/repartidor/tasks.md). Nace ofrecida: aceptarla o rechazarla va por la
 * operación real del repartidor, App\Repartidores\RespuestaDelRepartidor, nunca por la fábrica.
 *
 *     PedidoAsignacion::factory()->para($pedido, $repartidor)->create();
 *
 * El pedido y el repartidor tienen que ser de la misma plaza: la base rechaza otra (modelo-E06).
 *
 * @extends Factory<PedidoAsignacion>
 */
class PedidoAsignacionFactory extends Factory
{
    protected $model = PedidoAsignacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estado' => PedidoAsignacion::OFRECIDO,
            'motivo' => null,
            'respondido_en' => null,
        ];
    }

    /**
     * La oferta de ese pedido a ese repartidor, en la plaza del pedido.
     */
    public function para(Pedido $pedido, Repartidor $repartidor): static
    {
        return $this->state([
            'id_pedidos' => $pedido->getKey(),
            'id_plazas' => $pedido->id_plazas,
            'id_repartidores' => $repartidor->getKey(),
        ]);
    }
}
