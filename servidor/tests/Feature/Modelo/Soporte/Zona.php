<?php

namespace Tests\Feature\Modelo\Soporte;

use App\Models\Modelo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un modelo de prueba hijo: belongsTo nombra su llave, porque Eloquent la deduciría como plaza_id_plazas
 * (design.md, decisión 5).
 */
class Zona extends Modelo
{
    protected $table = 'zonas';

    protected $primaryKey = 'id_zonas';

    protected $fillable = ['nombre'];

    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'id_plazas');
    }
}
