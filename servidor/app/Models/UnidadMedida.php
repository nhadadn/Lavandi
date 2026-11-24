<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una unidad de medida del precio, del catálogo unidades_medida. La migración siembra el kilo, porque el contrato dice
 * "precios por kilo" (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5). La pieza entra como fila solo si
 * DD-010 la pide para el lavado en seco.
 */
class UnidadMedida extends Modelo
{
    public const KILO = 'kg';

    protected $table = 'unidades_medida';

    protected $primaryKey = 'id_unidades_medida';

    protected $fillable = ['codigo', 'nombre'];

    /**
     * @return HasMany<LavanderiaServicio, $this>
     */
    public function servicios(): HasMany
    {
        return $this->hasMany(LavanderiaServicio::class, 'id_unidades_medida');
    }
}
