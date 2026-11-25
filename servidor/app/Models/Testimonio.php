<?php

namespace App\Models;

use Database\Factories\TestimonioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Un testimonio de la portada: la opinión de un usuario beta, con su autor, su interruptor de activo y su orden
 * (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 8; ADR-0011). Se edita desde el panel, solo por la
 * administración (App\Filament\Resources\Testimonios\TestimonioResource; landing-E19).
 *
 * Sin plaza: la portada es una para todo el sitio (vault/arquitectura/modelo-de-datos.md, "Landing").
 */
class Testimonio extends Modelo
{
    /** @use HasFactory<TestimonioFactory> */
    use HasFactory;

    protected $table = 'testimonios';

    protected $primaryKey = 'id_testimonios';

    protected $fillable = ['autor', 'texto', 'activo', 'orden'];

    /** Un testimonio nuevo nace inactivo y al principio del orden, como en la base. */
    protected $attributes = [
        'activo' => false,
        'orden' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * Los activos, en el orden fijado en el panel; a igual orden, el más antiguo primero.
     *
     * @param  Builder<Testimonio>  $consulta
     * @return Builder<Testimonio>
     */
    public function scopeActivosEnOrden(Builder $consulta): Builder
    {
        return $consulta->where('activo', true)->orderBy('orden')->orderBy('id_testimonios');
    }
}
