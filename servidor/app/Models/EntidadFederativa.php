<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un estado del catálogo del territorio, entidades_federativas (openspec/changes/direcciones/design.md, "La dirección
 * asistida"). Se llama así, y no estado, para no confundirlo con estados_pedido ni con las columnas estado de otras
 * tablas.
 *
 * Lo llena el comando direcciones:cargar-catalogo con el archivo de Correos de México; ninguna pantalla lo edita. Solo
 * entran los estados del territorio de config/territorio.php (NSV-2026-001-v8.pdf:4:Cláusula I).
 */
class EntidadFederativa extends Modelo
{
    protected $table = 'entidades_federativas';

    protected $primaryKey = 'id_entidades_federativas';

    protected $fillable = ['codigo', 'nombre'];

    /**
     * @return HasMany<Municipio, $this>
     */
    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class, 'id_entidades_federativas');
    }
}
