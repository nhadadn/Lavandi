<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDeLaLavanderia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un servicio que ofrece una lavandería, con su precio y su unidad como datos: una fila por lavandería y tipo
 * (openspec/changes/perfil-de-lavanderia/design.md, decisión 2; ADR-0016, reglas 2 y 7).
 *
 * El precio va en pesos, numeric(12,2), y sale como texto con dos decimales, nunca como flotante. Quién lo fija es
 * DD-010.
 */
class LavanderiaServicio extends Modelo
{
    use HeredaLaPlazaDeLaLavanderia;

    protected $table = 'lavanderia_servicios';

    protected $primaryKey = 'id_lavanderia_servicios';

    protected $fillable = ['id_plazas', 'id_tipos_servicio', 'id_unidades_medida', 'precio', 'activo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TipoServicio, $this>
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoServicio::class, 'id_tipos_servicio');
    }

    /**
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidades_medida');
    }
}
