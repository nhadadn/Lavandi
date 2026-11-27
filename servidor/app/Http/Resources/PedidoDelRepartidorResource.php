<?php

namespace App\Http\Resources;

use App\Models\EstadoPedido;
use App\Models\Evidencia;
use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\PedidoDireccion;
use App\Models\Repartidor;
use App\Pedidos\CambioDeEstado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El pedido como lo ve el repartidor al que se le ofreció (openspec/changes/repartidor/specs/repartidor/spec.md,
 * repartidor-E10): su folio, su estado, su oferta, su ventana de recolección, la dirección de recogida y la de entrega, y
 * su lavandería. La forma exacta está en openspec/changes/repartidor/design.md, "Contrato de la API".
 *
 * No lleva datos de la cuenta que hizo el pedido, ni la llave interna, la plaza ni los montos: el monto que recibe el
 * repartidor espera DD-019 y DD-013 (repartidor-E26).
 *
 * @mixin Pedido
 */
class PedidoDelRepartidorResource extends JsonResource
{
    /**
     * Cómo nombra la app del repartidor cada estado operativo: "En tránsito" es el mismo paso operativo que la lavandería
     * nombra "En proceso de entrega" (ADR-0021, decisión 2; Módulo C #3). Un estado que no está aquí se nombra como lo ve
     * el usuario.
     */
    private const NOMBRES = [
        EstadoPedido::EN_ENTREGA => 'En tránsito',
    ];

    /** Los botones del repartidor, por el estado al que llevan, con sus nombres del contrato (Módulo C #3). */
    private const BOTONES = [
        EstadoPedido::RECOLECTADO => 'Recolectado',
        EstadoPedido::EN_ENTREGA => 'En tránsito',
        EstadoPedido::ENTREGADO => 'Entregado',
    ];

    public function __construct(Pedido $pedido, private readonly ?PedidoAsignacion $asignacion = null)
    {
        parent::__construct($pedido);
    }

    /**
     * Lo que la respuesta lee del pedido, para cargarlo junto con una lista: la oferta vigente es la de este repartidor.
     *
     * @return array<int|string, mixed>
     */
    public static function relaciones(Repartidor $repartidor): array
    {
        return [
            'plaza',
            'estado',
            'lavanderia',
            'evidencias' => fn (HasMany $evidencias) => $evidencias->orderBy('id_evidencias'),
            'direcciones' => fn (HasMany $direcciones) => $direcciones->conCoordenadas(),
            'asignaciones' => fn (HasMany $asignaciones) => $asignaciones
                ->where('id_repartidores', $repartidor->getKey())
                ->whereIn('estado', [PedidoAsignacion::OFRECIDO, PedidoAsignacion::ACEPTADO])
                ->orderByDesc('id_pedido_asignaciones'),
        ];
    }

    /**
     * Carga las relaciones de la respuesta en una consulta de pedidos del repartidor.
     *
     * @param  Builder<Pedido>  $consulta
     * @return Builder<Pedido>
     */
    public static function conRelaciones(Builder $consulta, Repartidor $repartidor): Builder
    {
        return $consulta->with(self::relaciones($repartidor));
    }

    /**
     * El pedido con lo que muestra la respuesta, ya cargado, para ese repartidor.
     */
    public static function delPedido(Pedido $pedido, Repartidor $repartidor): self
    {
        $pedido->load(self::relaciones($repartidor));

        return new self($pedido, $pedido->asignaciones->first());
    }

    /**
     * Los pedidos de una lista, ya cargados con conRelaciones().
     *
     * @param  iterable<Pedido>  $pedidos
     * @return list<self>
     */
    public static function deLaLista(iterable $pedidos): array
    {
        $recursos = [];

        foreach ($pedidos as $pedido) {
            $recursos[] = new self($pedido, $pedido->asignaciones->first());
        }

        return $recursos;
    }

    /**
     * El botón del paso siguiente, con su nombre, o null si el repartidor no aceptó el pedido o no le toca nada: mientras
     * la lavandería no lo acepta, entre Recolectado y Listo, y después de Entregado.
     *
     * @return array{estado: string, boton: string}|null
     */
    private function siguiente(string $codigo): ?array
    {
        $siguiente = $this->asignacion?->estado === PedidoAsignacion::ACEPTADO
            ? CambioDeEstado::botonSiguienteDelRepartidor($codigo)
            : null;

        return $siguiente === null ? null : ['estado' => $siguiente, 'boton' => self::BOTONES[$siguiente]];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $codigo = $this->estado->codigo;
        $inicio = $this->plaza->horaLocal($this->ventana_inicio);
        $fin = $this->plaza->horaLocal($this->ventana_fin);
        $direcciones = $this->direcciones->keyBy('tipo');

        return [
            'id_publico' => $this->id_publico,
            'folio' => $this->folio,
            'confirmado_en' => $this->confirmado_en?->toIso8601ZuluString(),
            'estado' => [
                'codigo' => $codigo,
                'nombre' => self::NOMBRES[$codigo] ?? $this->estado->nombre_usuario,
            ],
            // La oferta de este repartidor: ofrecida, o aceptada por él.
            'asignacion' => $this->asignacion === null ? null : [
                'estado' => $this->asignacion->estado,
                'ofrecido_en' => $this->asignacion->created_at?->utc()->toIso8601ZuluString(),
                'respondido_en' => $this->asignacion->respondido_en?->utc()->toIso8601ZuluString(),
            ],
            // Solo la oferta que espera su respuesta ofrece Aceptar y Rechazar (repartidor-E15 y E16).
            'por_responder' => $this->asignacion?->estado === PedidoAsignacion::OFRECIDO,
            // El único botón que puede tocar, en un pedido que aceptó: el paso siguiente, o ninguno (repartidor-E19).
            'siguiente' => $this->siguiente($codigo),
            'zona_horaria' => $this->plaza->zona_horaria,
            // Como en el pedido del usuario: instantes con el desfase de la plaza, y la fecha y las horas en su hora.
            'ventana' => [
                'inicio' => $inicio->toIso8601String(),
                'fin' => $fin->toIso8601String(),
                'fecha' => $inicio->toDateString(),
                'hora_inicio' => $inicio->format('H:i'),
                'hora_fin' => $fin->format('H:i'),
            ],
            'recogida' => PedidoResource::direccion($direcciones[PedidoDireccion::RECOLECCION]),
            'entrega' => PedidoResource::direccion($direcciones[PedidoDireccion::ENTREGA]),
            'lavanderia' => [
                'id_publico' => $this->lavanderia->id_publico,
                'nombre' => $this->lavanderia->nombre,
                'calle' => $this->lavanderia->calle,
                'numero' => $this->lavanderia->numero,
                'colonia' => $this->lavanderia->colonia,
                'codigo_postal' => $this->lavanderia->codigo_postal,
            ],
            // Las fotos de evidencia del pedido, sin su ruta ni ninguna URL: la foto no es pública (repartidor-E23).
            'evidencias' => $this->evidencias->map(fn (Evidencia $evidencia): array => [
                'tipo' => $evidencia->tipo,
                'tomada_en' => $evidencia->tomada_en->utc()->toIso8601ZuluString(),
            ])->values()->all(),
        ];
    }
}
