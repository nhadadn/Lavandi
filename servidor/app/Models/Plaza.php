<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

/**
 * Una plaza: un área operativa formada por zonas de servicio, con su zona horaria y su estado, sin atarla a un estado
 * ni a un municipio (NSV-2026-001-v8.pdf:4:Cláusula I; vault/arquitectura/modelo-multi-plaza.md).
 *
 * La zona horaria es obligatoria y es un nombre de la base de husos IANA, como America/Mexico_City; una abreviatura o
 * un desfase no sirven (plazas-E02). Los instantes se guardan absolutos, en timestamptz, y se presentan en la hora de
 * la plaza (plazas-E01).
 *
 * Una plaza que no está activa no da servicio al público (openspec/changes/plazas-y-zonas/design.md, decisión 4).
 */
class Plaza extends Modelo
{
    protected $table = 'plazas';

    protected $primaryKey = 'id_plazas';

    protected $fillable = ['nombre', 'zona_horaria', 'activa'];

    /**
     * Sin zona horaria válida no hay plaza, ni al crearla ni al cambiarla (plazas-E02). La base no puede revisarlo con
     * un CHECK, porque no conoce la base de husos de PHP.
     */
    protected static function booted(): void
    {
        static::saving(function (Plaza $plaza): void {
            Validator::make(
                ['zona_horaria' => $plaza->zona_horaria],
                ['zona_horaria' => ['required', 'string', 'timezone:all']],
                [
                    'zona_horaria.required' => 'La plaza necesita su zona horaria.',
                    'zona_horaria.string' => 'La zona horaria no es válida.',
                    'zona_horaria.timezone' => 'La zona horaria no está en la base de husos IANA.',
                ],
            )->validate();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    /**
     * El instante presentado en la hora de la plaza. Es el mismo instante: solo cambia cómo se lee (plazas-E01).
     */
    public function horaLocal(DateTimeInterface $instante): CarbonImmutable
    {
        return CarbonImmutable::instance($instante)->setTimezone($this->zona_horaria);
    }

    /**
     * @return HasMany<Zona, $this>
     */
    public function zonas(): HasMany
    {
        return $this->hasMany(Zona::class, 'id_plazas');
    }

    /**
     * @return HasMany<ParametroPlaza, $this>
     */
    public function parametros(): HasMany
    {
        return $this->hasMany(ParametroPlaza::class, 'id_plazas');
    }
}
