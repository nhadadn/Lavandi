<?php

namespace App\Models\Concerns;

use App\Models\Lavanderia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una hija de lavanderias: un servicio, un horario, una foto o una calificación. Al crearse sin plaza, toma la de su
 * lavandería, así que $lavanderia->horarios()->create([...]) basta.
 *
 * Una plaza distinta de la de su lavandería no se corrige aquí: la rechaza la base con la llave compuesta
 * (id_lavanderias, id_plazas) (modelo-E06; perfil-lavanderia-E14).
 */
trait HeredaLaPlazaDeLaLavanderia
{
    protected static function bootHeredaLaPlazaDeLaLavanderia(): void
    {
        static::creating(function (Model $fila): void {
            if ($fila->getAttribute('id_plazas') === null && $fila->getAttribute('id_lavanderias') !== null) {
                $fila->setAttribute('id_plazas', Lavanderia::withTrashed()
                    ->whereKey($fila->getAttribute('id_lavanderias'))
                    ->value('id_plazas'));
            }
        });
    }

    /**
     * @return BelongsTo<Lavanderia, $this>
     */
    public function lavanderia(): BelongsTo
    {
        return $this->belongsTo(Lavanderia::class, 'id_lavanderias');
    }
}
