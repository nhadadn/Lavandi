<?php

namespace App\Models;

use Database\Factories\LogoPrensaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Un logo de prensa de la portada: el de un medio o una mención, con su imagen, su interruptor de activo y su orden
 * (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 10: "Logos de medios o menciones, activables conforme se
 * obtengan"; ADR-0011). El nombre es el del medio, y es también el texto alternativo de la imagen. Se edita desde el
 * panel, solo por la administración (App\Filament\Resources\LogosPrensa\LogoPrensaResource; landing-E19).
 *
 * La imagen es la ruta del archivo en el disco privado de fotos del entorno (config('fotos.disco')), en la carpeta
 * prensa; la portada la recibe por la ruta pública portada.prensa.imagen (adenda del 2026-11-24 de ADR-0006).
 *
 * Sin plaza: la portada es una para todo el sitio (vault/arquitectura/modelo-de-datos.md, "Landing").
 */
class LogoPrensa extends Modelo
{
    /** @use HasFactory<LogoPrensaFactory> */
    use HasFactory;

    /** La carpeta del disco de fotos donde se guardan las imágenes de los logos. */
    public const CARPETA = 'prensa';

    protected $table = 'logos_prensa';

    protected $primaryKey = 'id_logos_prensa';

    protected $fillable = ['nombre', 'imagen', 'activo', 'orden'];

    /** Un logo nuevo nace inactivo y al principio del orden, como en la base. */
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
     * Los activos, en el orden fijado en el panel; a igual orden, el más antiguo primero. Solo los que tienen la imagen
     * suelta en la carpeta prensa, que es lo único que entrega la ruta pública (ImagenDePrensaController): uno con la
     * imagen en otra parte saldría en la portada como imagen rota. El panel siempre la sube ahí.
     *
     * @param  Builder<LogoPrensa>  $consulta
     * @return Builder<LogoPrensa>
     */
    public function scopeActivosEnOrden(Builder $consulta): Builder
    {
        return $consulta
            ->where('activo', true)
            ->where('imagen', 'like', self::CARPETA.'/%')
            ->where('imagen', 'not like', self::CARPETA.'/%/%')
            ->orderBy('orden')
            ->orderBy('id_logos_prensa');
    }

    /**
     * El nombre del archivo de la imagen, sin la carpeta: el que lleva la ruta pública (ADR-0006, adenda del
     * 2026-11-24). Nunca la llave interna (ADR-0016, regla 5).
     */
    public function archivo(): string
    {
        return basename($this->imagen);
    }
}
