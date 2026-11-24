<?php

namespace App\Exportacion;

use App\Models\Usuario;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una descarga de un reporte del panel: la fila de exports de la exportación de Filament, con su archivo en CSV y en
 * XLSX (openspec/changes/exportacion/design.md; ADR-0003).
 *
 * Extiende el modelo de Filament para ligar la descarga a la cuenta que la pidió por usuarios.id_usuarios: aquí no
 * existe la tabla users ni el modelo App\Models\User que Filament busca. AppServiceProvider lo pone en lugar del de
 * Filament en el contenedor, así que la acción, los trabajos de la cola y la ruta de descarga usan este.
 *
 * - Quién baja el archivo lo decide el componente único de autorización (App\Autorizacion\Autorizacion::
 *   puedeDescargarLaExportacion(); exportacion-E11), al que pregunta PoliticaDeExportacion desde la ruta de Filament.
 * - La descarga vive los días de exportacion.dias_de_vida y después se borra con sus archivos: el comando model:prune,
 *   programado una vez al día en routes/console.php (design.md, pregunta abierta 2).
 */
class Exportacion extends Export
{
    protected $table = 'exports';

    /**
     * La cuenta de la administración que pidió la descarga.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios', 'id_usuarios');
    }

    /**
     * Las descargas que ya pasaron sus días de vida.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDays(max(1, (int) config('exportacion.dias_de_vida'))));
    }

    /**
     * Antes de borrar la fila, sus archivos.
     */
    protected function pruning(): void
    {
        $this->deleteFileDirectory();
    }
}
