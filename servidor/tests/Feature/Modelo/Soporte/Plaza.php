<?php

namespace Tests\Feature\Modelo\Soporte;

use App\Models\Modelo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un modelo de prueba padre: hasMany encuentra la llave foránea sin nombrarla (design.md, decisión 5).
 */
class Plaza extends Modelo
{
    protected $table = 'plazas';

    protected $primaryKey = 'id_plazas';

    protected $fillable = ['nombre'];

    public function zonas(): HasMany
    {
        return $this->hasMany(Zona::class);
    }
}
