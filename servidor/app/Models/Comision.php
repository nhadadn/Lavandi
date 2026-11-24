<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDeLaLavanderia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un porcentaje de comisión con su vigencia: el global, sin plaza ni lavandería, uno solo para todo el sistema, o el
 * propio de una lavandería, con su plaza (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #5;
 * openspec/changes/administracion/design.md, "Tablas y migración").
 *
 * Un cambio de porcentaje es una fila nueva y la anterior queda como historial, con su fin de vigencia: no se edita una
 * vigencia que ya rigió. Quién fija, cambia y quita un porcentaje, y cuál le aplica a cada lavandería, lo dice
 * App\Comisiones\PorcentajesDeComision. La propia toma la plaza de su lavandería al crearse.
 *
 * El porcentaje va de 0 a 100, con dos decimales; lo revisa la base con su CHECK (administracion-E13). Ninguno viene de
 * fábrica: los captura EL CLIENTE (administracion-E11).
 */
class Comision extends Modelo
{
    use HeredaLaPlazaDeLaLavanderia;

    protected $table = 'comisiones';

    protected $primaryKey = 'id_comisiones';

    protected $fillable = ['id_plazas', 'id_lavanderias', 'porcentaje', 'vigente_desde', 'vigente_hasta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'vigente_desde' => 'immutable_datetime',
            'vigente_hasta' => 'immutable_datetime',
        ];
    }

    /**
     * La plaza de la comisión propia, la de su lavandería. Vacía en la global.
     *
     * @return BelongsTo<Plaza, $this>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'id_plazas');
    }

    /**
     * La lavandería de la comisión propia, aunque después se borre en suave: la comisión es historial. Vacía en la
     * global.
     *
     * @return BelongsTo<Lavanderia, $this>
     */
    public function lavanderia(): BelongsTo
    {
        return $this->belongsTo(Lavanderia::class, 'id_lavanderias')->withTrashed();
    }
}
