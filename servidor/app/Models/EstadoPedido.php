<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un estado del pedido, del catálogo estados_pedido (openspec/changes/resumen-y-confirmacion/design.md, "Tablas y
 * migración"; ADR-0016, regla 2).
 *
 * La migración siembra los cinco estados que ve el usuario, en su orden: Confirmado, Recolectado, En proceso, Listo y
 * Entregado (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #12). Otra siembra por_pagar, el estado previo al
 * pago, que el usuario no ve (ADR-0021); su código se lee de config/pedidos.php. Los demás internos los siembra el
 * cambio que los usa (ADR-0021, decisión 3).
 *
 * visible_usuario dice si el estado es uno de los cinco pasos del seguimiento. Un estado operativo que el usuario ve
 * como uno de ellos, como aceptado, lleva visible_usuario falso y el nombre y el orden de ese paso: el usuario ve el
 * paso (paso()). Uno sin paso de su orden, como por_pagar, está fuera del flujo (adenda del 2026-11-22 de ADR-0021,
 * punto 4).
 */
class EstadoPedido extends Modelo
{
    /** El pedido nació al aceptar el resumen y espera su pago. El usuario no lo ve (ADR-0021; C-188). */
    public const POR_PAGAR = 'por_pagar';

    /** El primer estado que ve el usuario: el pago quedó acreditado y el pedido tiene folio (C-188). */
    public const CONFIRMADO = 'confirmado';

    /** La lavandería aceptó el pedido (Módulo B #2). El usuario lo sigue viendo en Confirmado. */
    public const ACEPTADO = 'aceptado';

    public const RECOLECTADO = 'recolectado';

    public const EN_PROCESO = 'en_proceso';

    public const LISTO = 'listo';

    /** La lavandería marcó "En proceso de entrega" (Módulo B #3). El usuario lo sigue viendo en Listo (ADR-0021). */
    public const EN_ENTREGA = 'en_entrega';

    public const ENTREGADO = 'entregado';

    /** La lavandería rechazó el pedido con su motivo (Módulo B #2). Fuera de los cinco: la banda de excepción (C-201). */
    public const RECHAZADO = 'rechazado';

    protected $table = 'estados_pedido';

    protected $primaryKey = 'id_estados_pedido';

    protected $fillable = ['codigo', 'nombre_usuario', 'orden', 'visible_usuario'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'visible_usuario' => 'boolean',
        ];
    }

    /**
     * Los estados que ve el usuario, en el orden del contrato: los cinco del seguimiento, de Confirmado a Entregado
     * (Módulo A #12; ADR-0021, decisión 1). Deja fuera los internos, como por_pagar.
     *
     * @param  Builder<EstadoPedido>  $consulta
     */
    public function scopeDelSeguimiento(Builder $consulta): void
    {
        $consulta->where($consulta->qualifyColumn('visible_usuario'), true)->orderBy($consulta->qualifyColumn('orden'));
    }

    /**
     * El paso del seguimiento en que el usuario ve este estado: el mismo, si es uno de los cinco; el de su mismo orden,
     * si es un estado operativo que se ve como uno de ellos (aceptado se ve como Confirmado). Ninguno si el pedido está
     * fuera del flujo, como por_pagar.
     *
     * @return HasOne<EstadoPedido, $this>
     */
    public function paso(): HasOne
    {
        return $this->hasOne(self::class, 'orden', 'orden')->where('visible_usuario', true);
    }

    /**
     * Los pedidos que están ahora en este estado.
     *
     * @return HasMany<Pedido, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'id_estados_pedido');
    }
}
