<?php

namespace App\Http\Resources;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoDireccion;
use App\Models\PedidoServicio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El pedido como lo ve la cuenta que lo hizo: el resumen, el folio cuando ya está Confirmado
 * (openspec/changes/resumen-y-confirmacion/specs/confirmacion/spec.md), y su estado con los cinco del seguimiento
 * (openspec/changes/estados-del-pedido/specs/estados/spec.md). La forma exacta está en
 * App\Http\Controllers\Api\PedidosController.
 *
 * Lleva la copia del pedido, nunca la dirección guardada ni el precio actual de la lavandería (confirmacion-E14). No
 * lleva la llave interna, ni la cuenta, ni la plaza, ni de qué dirección guardada salió la copia.
 *
 * @mixin Pedido
 */
class PedidoResource extends JsonResource
{
    /**
     * El pedido con lo que muestra la respuesta, ya cargado.
     */
    public static function delPedido(Pedido $pedido): self
    {
        return new self($pedido->load([
            'plaza',
            'lavanderia',
            'estado.paso',
            'eventos',
            'servicios' => fn ($servicios) => $servicios->with(['tipo', 'unidad'])->orderBy('id_pedido_servicios'),
            'direcciones' => fn ($direcciones) => $direcciones->conCoordenadas(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $zona = $this->plaza->zona_horaria;
        $inicio = $this->ventana_inicio->setTimezone($zona);
        $fin = $this->ventana_fin->setTimezone($zona);
        $direcciones = $this->direcciones->keyBy('tipo');

        return [
            'id_publico' => $this->id_publico,
            'folio' => $this->folio,
            'confirmado_en' => $this->confirmado_en?->toIso8601ZuluString(),
            'estado' => self::estadoParaElUsuario($this->estado),
            // Los cinco estados que ve el usuario, en el orden del contrato, para pintar el seguimiento (estados-E01),
            // cada uno con la hora en que el pedido llegó a él, en la hora de la plaza, o null si no ha llegado
            // (estados-E24).
            'seguimiento' => EstadoPedido::query()->delSeguimiento()->get()->map(fn (EstadoPedido $paso): array => [
                'codigo' => $paso->codigo,
                'nombre' => $paso->nombre_usuario,
                'ocurrido_en' => $this->horaDelEstado($paso, $zona),
            ])->all(),
            'lavanderia' => [
                'id_publico' => $this->lavanderia->id_publico,
                'nombre' => $this->lavanderia->nombre,
            ],
            'servicios' => $this->servicios->map(fn (PedidoServicio $servicio): array => [
                'codigo' => $servicio->tipo->codigo,
                'nombre' => $servicio->tipo->nombre,
                'unidad' => [
                    'codigo' => $servicio->unidad->codigo,
                    'nombre' => $servicio->unidad->nombre,
                ],
                // Texto con dos decimales, en pesos: el dinero nunca viaja como flotante.
                'precio_unitario' => $servicio->precio_unitario,
                // Vacíos hasta DD-012.
                'cantidad_estimada' => $servicio->cantidad_estimada,
                'monto_estimado' => $servicio->monto_estimado,
            ])->values()->all(),
            'zona_horaria' => $zona,
            // Como la da la agenda: instantes con el desfase de la plaza, y la fecha y las horas en su hora.
            'ventana' => [
                'inicio' => $inicio->toIso8601String(),
                'fin' => $fin->toIso8601String(),
                'fecha' => $inicio->toDateString(),
                'hora_inicio' => $inicio->format('H:i'),
                'hora_fin' => $fin->format('H:i'),
            ],
            'recogida' => self::direccion($direcciones[PedidoDireccion::RECOLECCION]),
            'entrega' => self::direccion($direcciones[PedidoDireccion::ENTREGA]),
            // Vacíos hasta DD-012 y DD-010, y DD-019 y DD-025.
            'monto_estimado' => $this->monto_estimado,
            'envio' => $this->envio,
        ];
    }

    /**
     * El estado actual como lo ve el usuario: el paso del seguimiento en que lo ve, con el nombre del estado. Un estado
     * operativo que se ve como uno de los cinco, como aceptado, sale con el código de ese paso, para que la app lo
     * ubique en el seguimiento; uno fuera del flujo, como por_pagar, sale con su código y visible_usuario falso (adenda
     * del 2026-11-22 de ADR-0021, punto 4). Espera cargado estado.paso.
     *
     * @return array{codigo: string, nombre: string, visible_usuario: bool}
     */
    public static function estadoParaElUsuario(EstadoPedido $estado): array
    {
        $paso = $estado->paso;

        return [
            'codigo' => $paso?->codigo ?? $estado->codigo,
            'nombre' => $estado->nombre_usuario,
            'visible_usuario' => $paso !== null,
        ];
    }

    /**
     * La hora en que el pedido llegó a ese estado, en la hora de la plaza: la de su evento más reciente en ese estado
     * (estados-E24). Sin evento, el pedido todavía no llega a él.
     */
    private function horaDelEstado(EstadoPedido $paso, string $zona): ?string
    {
        return $this->eventos
            ->where('id_estados_pedido', $paso->getKey())
            ->sortBy([['ocurrido_en', 'asc'], ['id_pedido_eventos', 'asc']])
            ->last()
            ?->ocurrido_en
            ->setTimezone($zona)
            ->toIso8601String();
    }

    /**
     * La copia de una dirección del pedido, leída con PedidoDireccion::conCoordenadas(). La usa también el pedido del
     * repartidor (PedidoDelRepartidorResource), con la misma forma.
     *
     * @return array<string, mixed>
     */
    public static function direccion(PedidoDireccion $copia): array
    {
        return [
            'alias' => $copia->alias,
            'calle' => $copia->calle,
            'numero_exterior' => $copia->numero_exterior,
            'numero_interior' => $copia->numero_interior,
            'colonia' => $copia->colonia,
            'codigo_postal' => $copia->codigo_postal,
            'municipio' => $copia->municipio,
            'referencias' => $copia->referencias,
            'latitud' => (float) $copia->latitud,
            'longitud' => (float) $copia->longitud,
        ];
    }
}
