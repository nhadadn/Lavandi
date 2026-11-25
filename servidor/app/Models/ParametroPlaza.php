<?php

namespace App\Models;

use App\Agenda\ParametrosDeAgenda;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

/**
 * Un parámetro comercial de una plaza, con su vigencia: las reglas que el contrato deja abiertas y cambian con el
 * tiempo (vault/arquitectura/modelo-de-datos.md; ADR-0016, alternativa C).
 *
 * La tabla nace vacía. Cada clave la agrega y la valida el cambio que la usa (agenda-de-recoleccion,
 * resumen-y-confirmacion), cuando su fila de la deuda se responda (openspec/changes/plazas-y-zonas/design.md). Las que
 * ya existen:
 *
 *     agenda_anticipacion_minima   {"minutos": 120}   App\Agenda\ParametrosDeAgenda (agenda-E12)
 *     agenda_dias_hacia_adelante   {"dias": 3}        App\Agenda\ParametrosDeAgenda (agenda-E12)
 *
 * Una clave que no está en esa lista todavía no tiene validación propia y se guarda como llega.
 *
 * La vigencia termina después de empezar, o no termina: lo revisa la base con su CHECK (plazas-E15). Que dos vigencias
 * de la misma clave no se traslapen sigue sin escenario: mientras tanto, si se enciman, rige la que empezó después.
 */
class ParametroPlaza extends Modelo
{
    protected $table = 'parametros_plaza';

    protected $primaryKey = 'id_parametros_plaza';

    protected $fillable = ['clave', 'valor', 'vigente_desde', 'vigente_hasta'];

    /**
     * El valor de una clave conocida se valida al crearlo y al cambiarlo, como la zona horaria de la plaza
     * (plazas-E02): la base no puede revisar un jsonb contra la regla de cada clave.
     */
    protected static function booted(): void
    {
        static::saving(function (ParametroPlaza $parametro): void {
            $validacion = ParametrosDeAgenda::validacion((string) $parametro->clave);

            if ($validacion !== null) {
                Validator::make(['valor' => $parametro->valor], $validacion['reglas'], $validacion['mensajes'])->validate();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'array',
            'vigente_desde' => 'immutable_datetime',
            'vigente_hasta' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Plaza, $this>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'id_plazas');
    }
}
