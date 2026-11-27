<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDelPedido;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La copia de la recogida o de la entrega de un pedido, hecha al crearlo: si la dirección guardada se edita o se
 * borra, el pedido conserva la suya (ADR-0016, primera excepción). id_direcciones dice de cuál salió, con la llave
 * compuesta con la plaza: la base rechaza una dirección de otra plaza o sin plaza (direcciones-E11 y E12).
 *
 * La ubicación se asigna con su latitud y su longitud, o con una expresión de PostGIS ya armada, como la que copia el
 * punto de la dirección guardada sin pasar por PHP. Leída de la base es el EWKB que devuelve PostgreSQL, así que no
 * sale en JSON; las coordenadas se leen con conCoordenadas().
 */
class PedidoDireccion extends Modelo
{
    use HeredaLaPlazaDelPedido;

    public const RECOLECCION = 'recoleccion';

    public const ENTREGA = 'entrega';

    protected $table = 'pedido_direcciones';

    protected $primaryKey = 'id_pedido_direcciones';

    protected $fillable = [
        'id_plazas',
        'tipo',
        'id_direcciones',
        'alias',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'municipio',
        'referencias',
        'ubicacion',
    ];

    protected $hidden = ['ubicacion'];

    /**
     * La dirección guardada de la que salió la copia, aunque después se haya borrado.
     *
     * @return BelongsTo<Direccion, $this>
     */
    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class, 'id_direcciones')->withTrashed();
    }

    /**
     * Agrega a cada copia su latitud y su longitud, leídas de la ubicación con PostGIS, para la API.
     *
     * @param  Builder<PedidoDireccion>  $consulta
     */
    public function scopeConCoordenadas(Builder $consulta): void
    {
        $consulta
            ->select($consulta->qualifyColumn('*'))
            ->selectRaw("ST_Y({$consulta->qualifyColumn('ubicacion')}::geometry) as latitud")
            ->selectRaw("ST_X({$consulta->qualifyColumn('ubicacion')}::geometry) as longitud");
    }

    /**
     * La ubicación se asigna como ['latitud' => ..., 'longitud' => ...] o como una expresión de PostGIS ya armada.
     *
     * @return Attribute<mixed, Expression>
     */
    protected function ubicacion(): Attribute
    {
        return Attribute::make(
            set: fn (array|Expression $ubicacion): Expression => $ubicacion instanceof Expression
                ? $ubicacion
                : Direccion::punto((float) $ubicacion['latitud'], (float) $ubicacion['longitud']),
        );
    }
}
