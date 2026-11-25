<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un reembolso de un pago, por la pasarela de ese pago (openspec/changes/pago/design.md, "Tablas y migración";
 * vault/arquitectura/modelo-de-datos.md, "Dinero").
 *
 * La tabla nace vacía: cuándo y cuánto se reembolsa esperan DD-012, DD-016 y DD-103 (pago-E15 y pago-E16). Toma la plaza
 * de su pago, y la base rechaza otra con la llave compuesta (id_pagos, id_plazas) (modelo-E06). No se borra nunca: el
 * trigger impedir_cambio() lo impide.
 */
class Reembolso extends Modelo
{
    public const PENDIENTE = 'pendiente';

    public const REEMBOLSADO = 'reembolsado';

    public const FALLIDO = 'fallido';

    protected $table = 'reembolsos';

    protected $primaryKey = 'id_reembolsos';

    protected $fillable = [
        'id_pagos',
        'id_plazas',
        'monto',
        'motivo',
        'id_pasarela_reembolso',
        'estado',
        'reembolsado_en',
    ];

    protected static function booted(): void
    {
        // Sin plaza, toma la de su pago.
        static::creating(function (Reembolso $reembolso): void {
            if ($reembolso->id_plazas === null && $reembolso->id_pagos !== null) {
                $reembolso->id_plazas = Pago::query()->whereKey($reembolso->id_pagos)->value('id_plazas');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'reembolsado_en' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Pago, $this>
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'id_pagos');
    }
}
