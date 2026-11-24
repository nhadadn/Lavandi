<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * La clase base de los modelos del negocio (ADR-0016, regla 5; openspec/changes/modelo-de-datos/design.md,
 * decisión 5).
 *
 * La llave primaria se llama id_<tabla> y la foránea lleva su mismo nombre, así que getForeignKey() devuelve la
 * llave primaria: hasMany y belongsToMany encuentran la foránea sin nombrarla.
 *
 * Cada modelo declara su $table y su $primaryKey, porque el pluralizador de Laravel es inglés y Eloquent espera id
 * (la prueba modelo-E16 lo verifica). Cada belongsTo nombra su llave, porque Eloquent la deduce como
 * <relación>_<llave>. Las tablas de enlace (rol_usuario, actividad_usuarios) no tienen modelo (decisión 7).
 *
 *     class Direccion extends Modelo
 *     {
 *         use TieneIdPublico;
 *
 *         protected $table = 'direcciones';
 *
 *         protected $primaryKey = 'id_direcciones';
 *
 *         public function usuario(): BelongsTo
 *         {
 *             return $this->belongsTo(Usuario::class, 'id_usuarios');
 *         }
 *     }
 */
abstract class Modelo extends Model
{
    /**
     * La llave foránea que apunta a este modelo: el mismo nombre que su llave primaria (C-190).
     */
    public function getForeignKey(): string
    {
        return $this->getKeyName();
    }
}
