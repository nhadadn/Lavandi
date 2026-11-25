<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un municipio del territorio, del catálogo municipios (openspec/changes/direcciones/design.md, "La dirección
 * asistida", decisión 7). La API lo nombra por su codigo, cinco números: el del estado y los tres del municipio, como
 * los da el archivo de Correos de México.
 *
 * La dirección guarda la llave a él (direcciones.id_municipios) y su estado sale de aquí, sin columna propia
 * (direcciones-E17). Nunca se borra: las direcciones lo apuntan.
 */
class Municipio extends Modelo
{
    protected $table = 'municipios';

    protected $primaryKey = 'id_municipios';

    protected $fillable = ['id_entidades_federativas', 'codigo', 'nombre'];

    /**
     * @return BelongsTo<EntidadFederativa, $this>
     */
    public function entidadFederativa(): BelongsTo
    {
        return $this->belongsTo(EntidadFederativa::class, 'id_entidades_federativas');
    }

    /**
     * @return HasMany<Colonia, $this>
     */
    public function colonias(): HasMany
    {
        return $this->hasMany(Colonia::class, 'id_municipios');
    }
}
