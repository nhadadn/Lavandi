<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un asentamiento del catálogo colonias, con su código postal y su tipo: Colonia, Fraccionamiento, Barrio…
 * (openspec/changes/direcciones/design.md, "La dirección asistida", decisión 8).
 *
 * Solo llena la lista de colonias de un código postal (direcciones-E19). La dirección guarda la colonia como texto y el
 * servidor no la compara con este catálogo (direcciones-E20), así que ninguna tabla apunta aquí y la carga puede
 * reemplazar las colonias de un municipio.
 */
class Colonia extends Modelo
{
    protected $table = 'colonias';

    protected $primaryKey = 'id_colonias';

    protected $fillable = ['id_municipios', 'codigo_postal', 'nombre', 'tipo'];

    /**
     * @return BelongsTo<Municipio, $this>
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'id_municipios');
    }
}
