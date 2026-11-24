<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un tipo de servicio, del catálogo tipos_servicio. La migración siembra los tres del contrato: lavado estándar, lavado
 * con planchado y lavado en seco (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #6;
 * openspec/changes/perfil-de-lavanderia/design.md, decisión 3).
 *
 * Ninguna pantalla ni comando agrega tipos: uno nuevo, como el calzado, es Orden de Cambio (DD-046).
 */
class TipoServicio extends Modelo
{
    public const ESTANDAR = 'estandar';

    public const PLANCHADO = 'planchado';

    public const SECO = 'seco';

    protected $table = 'tipos_servicio';

    protected $primaryKey = 'id_tipos_servicio';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LavanderiaServicio, $this>
     */
    public function servicios(): HasMany
    {
        return $this->hasMany(LavanderiaServicio::class, 'id_tipos_servicio');
    }
}
