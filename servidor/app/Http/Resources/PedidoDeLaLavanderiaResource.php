<?php

namespace App\Http\Resources;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoServicio;
use App\Pedidos\CambioDeEstado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El pedido como lo ve la lavandería que lo atiende (openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/
 * spec.md, pedidos-lavanderia-E03): su folio, su servicio, su fecha y su ventana de recolección, su estado operativo y
 * si espera su respuesta. La forma exacta está en App\Http\Controllers\Api\PedidosDeLaLavanderiaController.
 *
 * No lleva ningún dato del usuario ni las direcciones: cuáles ve la lavandería lo fijan DD-022 y DD-014
 * (pedidos-lavanderia-E04). Tampoco la llave interna, la plaza ni los montos.
 *
 * @mixin Pedido
 */
class PedidoDeLaLavanderiaResource extends JsonResource
{
    /**
     * Cómo nombra la app de la lavandería cada estado operativo, y cada botón por el estado al que lleva: "Por aceptar"
     * es el de la tarjeta de pedido de socio lavandería (vault/design-system/componentes.md, componente 4); "Aceptado"
     * y "Rechazado", los de su respuesta (Módulo B #2; pedidos-lavanderia-E07 y E12); los cuatro botones, con sus
     * nombres del contrato (Módulo B #3), y Entregado, con el del Módulo A #12. Un estado que no está aquí se nombra como
     * lo ve el usuario.
     */
    private const NOMBRES = [
        EstadoPedido::CONFIRMADO => 'Por aceptar',
        EstadoPedido::ACEPTADO => 'Aceptado',
        EstadoPedido::RECHAZADO => 'Rechazado',
        EstadoPedido::RECOLECTADO => 'Recolectado',
        EstadoPedido::EN_PROCESO => 'En proceso',
        EstadoPedido::LISTO => 'Listo',
        EstadoPedido::EN_ENTREGA => 'En proceso de entrega',
        EstadoPedido::ENTREGADO => 'Entregado',
    ];

    /**
     * El botón del paso siguiente, con su nombre, o null si la lavandería no tiene ninguno que tocar.
     *
     * @return array{estado: string, boton: string}|null
     */
    private function siguiente(string $codigo): ?array
    {
        $siguiente = CambioDeEstado::botonSiguiente($codigo);

        return $siguiente === null ? null : ['estado' => $siguiente, 'boton' => self::NOMBRES[$siguiente]];
    }

    /**
     * El pedido con lo que muestra la respuesta, ya cargado.
     */
    public static function delPedido(Pedido $pedido): self
    {
        return new self($pedido->loadMissing(self::RELACIONES));
    }

    /** Lo que la respuesta lee del pedido, para cargarlo junto con una lista. */
    public const RELACIONES = [
        'plaza',
        'estado',
        'servicios.tipo',
        'servicios.unidad',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $codigo = $this->estado->codigo;
        $inicio = $this->plaza->horaLocal($this->ventana_inicio);
        $fin = $this->plaza->horaLocal($this->ventana_fin);

        return [
            'id_publico' => $this->id_publico,
            'folio' => $this->folio,
            'confirmado_en' => $this->confirmado_en?->toIso8601ZuluString(),
            'estado' => [
                'codigo' => $codigo,
                'nombre' => self::NOMBRES[$codigo] ?? $this->estado->nombre_usuario,
            ],
            // Solo el pedido que espera la respuesta de la lavandería ofrece Aceptar y Rechazar (pedidos-lavanderia-E03).
            'por_responder' => $codigo === EstadoPedido::CONFIRMADO,
            // El único botón que puede tocar: el paso siguiente, o ninguno (estados-E06).
            'siguiente' => $this->siguiente($codigo),
            'servicios' => $this->servicios->sortBy('id_pedido_servicios')->map(fn (PedidoServicio $servicio): array => [
                'codigo' => $servicio->tipo->codigo,
                'nombre' => $servicio->tipo->nombre,
                'unidad' => [
                    'codigo' => $servicio->unidad->codigo,
                    'nombre' => $servicio->unidad->nombre,
                ],
            ])->values()->all(),
            'zona_horaria' => $this->plaza->zona_horaria,
            // Como en el pedido del usuario: instantes con el desfase de la plaza, y la fecha y las horas en su hora.
            'ventana' => [
                'inicio' => $inicio->toIso8601String(),
                'fin' => $fin->toIso8601String(),
                'fecha' => $inicio->toDateString(),
                'hora_inicio' => $inicio->format('H:i'),
                'hora_fin' => $fin->format('H:i'),
            ],
        ];
    }
}
