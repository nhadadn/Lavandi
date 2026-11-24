<?php

namespace App\Models;

use App\Models\Concerns\TieneIdPublico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un pedido: siempre de la plaza de su lavandería, de una cuenta, con su ventana de recolección, su estado actual y sus
 * hijas, los servicios con su precio copiado, las dos direcciones copiadas y su historial de eventos
 * (openspec/changes/resumen-y-confirmacion/design.md; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * La API lo identifica por su id_publico, un ULID, nunca por id_pedidos ni por su folio (modelo-E15). No se borra
 * nunca: el trigger impedir_cambio() lo impide (modelo-E11). El folio y confirmado_en quedan vacíos hasta que la
 * pasarela confirma el pago (C-188).
 *
 * La ventana se guarda en UTC, como la da App\Agenda\Agenda::validar(), y se presenta en la hora de la plaza.
 */
class Pedido extends Modelo
{
    use TieneIdPublico;

    protected $table = 'pedidos';

    protected $primaryKey = 'id_pedidos';

    protected $fillable = [
        'id_plazas',
        'folio',
        'id_usuarios',
        'id_lavanderias',
        'id_estados_pedido',
        'ventana_inicio',
        'ventana_fin',
        'confirmado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ventana_inicio' => 'immutable_datetime',
            'ventana_fin' => 'immutable_datetime',
            'monto_estimado' => 'decimal:2',
            'monto_final' => 'decimal:2',
            'envio' => 'decimal:2',
            'comision_porcentaje' => 'decimal:2',
            'confirmado_en' => 'immutable_datetime',
            'cancelado_en' => 'immutable_datetime',
            // No es columna: la trae entregados(), de pedido_eventos.
            'entregado_en' => 'immutable_datetime',
        ];
    }

    /**
     * Los pedidos que ya entraron al flujo: el pago quedó acreditado y tienen folio (C-188; ADR-0021). Uno que espera su
     * pago no tiene estado que nombrar al usuario.
     *
     * @param  Builder<Pedido>  $consulta
     */
    public function scopeConfirmados(Builder $consulta): void
    {
        $consulta->whereNotNull($consulta->qualifyColumn('confirmado_en'));
    }

    /**
     * Los pedidos activos de la lavandería: los confirmados que esperan su respuesta y los que aceptó y aún no llegan a
     * Entregado (pedidos-lavanderia-E01). Nunca uno entregado, rechazado o que espera su pago; tampoco uno de un estado
     * que se agregue después, como cancelado, mientras no se nombre aquí.
     *
     * @param  Builder<Pedido>  $consulta
     */
    public function scopeActivosDeLaLavanderia(Builder $consulta): void
    {
        $consulta->whereHas('estado', fn (Builder $estado) => $estado->whereIn('codigo', [
            EstadoPedido::CONFIRMADO,
            EstadoPedido::ACEPTADO,
            EstadoPedido::RECOLECTADO,
            EstadoPedido::EN_PROCESO,
            EstadoPedido::LISTO,
            EstadoPedido::EN_ENTREGA,
        ]));
    }

    /**
     * Los pedidos activos del repartidor, los de su lista en tiempo real: los confirmados que todavía no llegan a
     * Entregado, de confirmado a en_entrega, se los haya ofrecido o los haya aceptado (repartidor-E10). Nunca uno
     * entregado, que va a su historial (repartidor-E25), ni uno que la lavandería rechazó; tampoco uno de un estado que
     * se agregue después, como cancelado, mientras no se nombre aquí.
     *
     * @param  Builder<Pedido>  $consulta
     */
    public function scopeActivosDelRepartidor(Builder $consulta): void
    {
        $consulta->whereHas('estado', fn (Builder $estado) => $estado->whereIn('codigo', [
            EstadoPedido::CONFIRMADO,
            EstadoPedido::ACEPTADO,
            EstadoPedido::RECOLECTADO,
            EstadoPedido::EN_PROCESO,
            EstadoPedido::LISTO,
            EstadoPedido::EN_ENTREGA,
        ]));
    }

    /**
     * Los pedidos activos del monitoreo del panel: los que entraron al flujo y todavía no se entregan ni se cancelan
     * (openspec/changes/administracion/design.md, lectura 1; administracion-E06). Además de los activos de la
     * lavandería, entra el rechazado: está pagado y no se entregó ni se canceló, y lo que pasa con él esperan DD-103 y
     * DD-016. Nunca uno entregado ni uno que espera su pago; tampoco uno de un estado que se agregue después, como
     * cancelado, mientras no se nombre aquí (administracion-E08).
     *
     * @param  Builder<Pedido>  $consulta
     */
    public function scopeActivosDelMonitoreo(Builder $consulta): void
    {
        $consulta->whereHas('estado', fn (Builder $estado) => $estado->whereIn('codigo', [
            EstadoPedido::CONFIRMADO,
            EstadoPedido::ACEPTADO,
            EstadoPedido::RECHAZADO,
            EstadoPedido::RECOLECTADO,
            EstadoPedido::EN_PROCESO,
            EstadoPedido::LISTO,
            EstadoPedido::EN_ENTREGA,
        ]));
    }

    /**
     * Los pedidos completados: los que están en Entregado, el último de los cinco estados (Módulo A #12), con el instante
     * de su paso a Entregado en entregado_en, leído de su historial en pedido_eventos
     * (openspec/changes/historial-de-la-lavanderia/design.md, decisión 1). Entregado no tiene salida (ADR-0021).
     *
     * @param  Builder<Pedido>  $consulta
     */
    public function scopeEntregados(Builder $consulta): void
    {
        $consulta->whereHas('estado', fn (Builder $estado) => $estado->where('codigo', EstadoPedido::ENTREGADO))
            ->addSelect([
                'entregado_en' => PedidoEvento::query()
                    ->selectRaw('max(pedido_eventos.ocurrido_en)')
                    ->whereColumn('pedido_eventos.id_pedidos', $consulta->qualifyColumn('id_pedidos'))
                    ->whereHas('estado', fn (Builder $estado) => $estado->where('codigo', EstadoPedido::ENTREGADO)),
            ]);
    }

    /**
     * De la entrega más reciente a la más antigua; con la misma, del pedido más nuevo al más viejo. Va después de
     * entregados(), que trae entregado_en.
     *
     * @param  Builder<Pedido>  $consulta
     */
    public function scopeDeLaEntregaMasReciente(Builder $consulta): void
    {
        $consulta->orderByDesc('entregado_en')->orderByDesc($consulta->qualifyColumn('id_pedidos'));
    }

    /**
     * De la recolección más próxima a la más lejana; con la misma recolección, del pedido más viejo al más nuevo.
     *
     * @param  Builder<Pedido>  $consulta
     */
    public function scopeDeLaRecoleccionMasProxima(Builder $consulta): void
    {
        $consulta->orderBy($consulta->qualifyColumn('ventana_inicio'))
            ->orderBy($consulta->qualifyColumn('id_pedidos'));
    }

    /**
     * Los pedidos con la recolección entre esos dos días, los dos incluidos, contados en la hora de la plaza de cada
     * pedido: la fecha que muestra su resumen (historial-E03; openspec/changes/historial-y-calificacion/design.md,
     * decisión 1). Sin uno de los dos, el rango queda abierto de ese lado.
     *
     * @param  Builder<Pedido>  $consulta
     */
    public function scopeRecolectadosEntre(Builder $consulta, ?string $desde, ?string $hasta): void
    {
        $fecha = sprintf(
            '(%s at time zone (select plazas.zona_horaria from plazas where plazas.id_plazas = %s))::date',
            $consulta->qualifyColumn('ventana_inicio'),
            $consulta->qualifyColumn('id_plazas'),
        );

        if ($desde !== null) {
            $consulta->whereRaw("{$fecha} >= cast(? as date)", [$desde]);
        }

        if ($hasta !== null) {
            $consulta->whereRaw("{$fecha} <= cast(? as date)", [$hasta]);
        }
    }

    /**
     * Del pedido con la recolección más reciente al de la más antigua; con la misma recolección, del más nuevo al más
     * viejo.
     *
     * @param  Builder<Pedido>  $consulta
     */
    public function scopeDelMasRecienteAlMasAntiguo(Builder $consulta): void
    {
        $consulta->orderByDesc($consulta->qualifyColumn('ventana_inicio'))
            ->orderByDesc($consulta->qualifyColumn('id_pedidos'));
    }

    /**
     * @return BelongsTo<Plaza, $this>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'id_plazas');
    }

    /**
     * La cuenta que hizo el pedido.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios');
    }

    /**
     * La lavandería, aunque después se borre en suave: el pedido es historial.
     *
     * @return BelongsTo<Lavanderia, $this>
     */
    public function lavanderia(): BelongsTo
    {
        return $this->belongsTo(Lavanderia::class, 'id_lavanderias')->withTrashed();
    }

    /**
     * El repartidor que aceptó el pedido, aunque después se dé de baja: el pedido es historial (repartidor-E07). Vacío
     * mientras ninguno lo acepte.
     *
     * @return BelongsTo<Repartidor, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class, 'id_repartidores')->withTrashed();
    }

    /**
     * Las ofertas del pedido a los repartidores, con su respuesta (repartidor-E15 y E16).
     *
     * @return HasMany<PedidoAsignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(PedidoAsignacion::class, 'id_pedidos');
    }

    /**
     * Las fotos de evidencia del pedido (repartidor-E23).
     *
     * @return HasMany<Evidencia, $this>
     */
    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class, 'id_pedidos');
    }

    /**
     * Los pagos del pedido: su cobro y, si DD-012 lo pide, un ajuste (openspec/changes/pago/).
     *
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_pedidos');
    }

    /**
     * El cobro del pedido, el único pago de tipo cobro. Vacío mientras pago-E14 no lo cree (DD-012).
     *
     * @return HasOne<Pago, $this>
     */
    public function cobro(): HasOne
    {
        return $this->hasOne(Pago::class, 'id_pedidos')->where('tipo', Pago::COBRO);
    }

    /**
     * El estado actual; su historia está en eventos().
     *
     * @return BelongsTo<EstadoPedido, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoPedido::class, 'id_estados_pedido');
    }

    /**
     * @return HasMany<PedidoServicio, $this>
     */
    public function servicios(): HasMany
    {
        return $this->hasMany(PedidoServicio::class, 'id_pedidos');
    }

    /**
     * La recogida y la entrega, copiadas al crear el pedido.
     *
     * @return HasMany<PedidoDireccion, $this>
     */
    public function direcciones(): HasMany
    {
        return $this->hasMany(PedidoDireccion::class, 'id_pedidos');
    }

    /**
     * El historial de cambios de estado.
     *
     * @return HasMany<PedidoEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(PedidoEvento::class, 'id_pedidos');
    }

    /**
     * Las calificaciones del pedido, una por calificado: la de su lavandería y, si DD-052 lo pide, la de su repartidor
     * (historial-E13).
     *
     * @return HasMany<Calificacion, $this>
     */
    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class, 'id_pedidos');
    }

    /**
     * La calificación del servicio que hizo el usuario: la de la lavandería del pedido (historial-E12;
     * App\Calificaciones\CalificacionDelServicio). Vacía mientras no lo califique. La del repartidor, si DD-052 la
     * pide, no lleva lavandería y no entra.
     *
     * @return HasOne<Calificacion, $this>
     */
    public function calificacionDelServicio(): HasOne
    {
        return $this->hasOne(Calificacion::class, 'id_pedidos')->whereNotNull('id_lavanderias');
    }
}
