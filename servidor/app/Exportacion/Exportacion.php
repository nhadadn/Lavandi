<?php

namespace App\Exportacion;

use App\Models\Usuario;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Una descarga de un reporte, pedida en el panel o en la app: la fila de exports de la exportación de Filament, con su
 * archivo en CSV y en XLSX (openspec/changes/exportacion/design.md; ADR-0003; ADR-0022, decisión 15).
 *
 * Extiende el modelo de Filament para ligar la descarga a la cuenta que la pidió por usuarios.id_usuarios: aquí no
 * existe la tabla users ni el modelo App\Models\User que Filament busca. AppServiceProvider lo pone en lugar del de
 * Filament en el contenedor, así que la acción, los trabajos de la cola y la ruta de descarga usan este.
 *
 * - Quién baja el archivo lo decide el componente único de autorización (App\Autorizacion\Autorizacion::
 *   puedeDescargarLaExportacion(); exportacion-E11), al que pregunta PoliticaDeExportacion desde la ruta de Filament.
 * - La descarga vive los días de exportacion.dias_de_vida y después se borra con sus archivos: el comando model:prune,
 *   programado una vez al día en routes/console.php (design.md, pregunta abierta 2).
 * - La API de la administración la nombra por su id_publico, un ULID que se asigna al crear la fila, nunca por su id
 *   (ADR-0016, regla 5; ADR-0022, decisión 15). La llave y la ruta de descarga de Filament siguen con id: por eso no usa
 *   TieneIdPublico, que cambia getRouteKeyName(). Las filas de antes de la migración del id_publico se quedan sin él, y
 *   la API no las lista.
 * - Da también el estado de la descarga, de sus archivos (estado()), y las que siguen vivas (vivas()).
 */
class Exportacion extends Export
{
    use HasUlids;

    protected $table = 'exports';

    /**
     * Las columnas que reciben un ULID al crear la fila: solo el identificador público, no la llave primaria, que sigue
     * siendo el id autoincremental de Filament.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['id_publico'];
    }

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
     * La descarga con ese identificador público, o ninguna: tampoco si el valor no es un ULID, que no llega a la base.
     * HasUlids guarda el ULID en minúsculas. De quién es no lo dice esto: lo decide después el componente de
     * autorización (puedeDescargarLaExportacion()).
     */
    public static function porIdPublico(string $idPublico): ?self
    {
        if (! Str::isUlid($idPublico)) {
            return null;
        }

        return static::query()->where('id_publico', Str::lower($idPublico))->first();
    }

    /**
     * Las descargas que pidió esa cuenta.
     *
     * @param  Builder<static>  $consulta
     */
    public function scopePedidasPor(Builder $consulta, Usuario $cuenta): void
    {
        $consulta->where($consulta->qualifyColumn('id_usuarios'), $cuenta->getKey());
    }

    /**
     * Las descargas que la API puede nombrar: las que tienen id_publico. Las de antes de su migración no lo tienen.
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeConIdPublico(Builder $consulta): void
    {
        $consulta->whereNotNull($consulta->qualifyColumn('id_publico'));
    }

    /**
     * En qué va la descarga (ADR-0022, decisión 15): generándose mientras la cola no la marque terminada; lista si
     * además están en su disco los dos archivos que escribe EscribirArchivoDelReporte; si falta alguno, no se pudo. Lista,
     * se baja en los dos formatos: el descargador de Filament arma el XLSX de esos CSV si todavía no existe.
     */
    public function estado(): EstadoDeLaDescarga
    {
        if ($this->completed_at === null) {
            return EstadoDeLaDescarga::Generandose;
        }

        $disco = $this->getFileDisk();

        foreach ([EscribirArchivoDelReporte::ARCHIVO_DEL_ENCABEZADO, EscribirArchivoDelReporte::ARCHIVO_DE_LAS_FILAS] as $archivo) {
            if (! $disco->exists($this->getFileDirectory().DIRECTORY_SEPARATOR.$archivo)) {
                return EstadoDeLaDescarga::NoSePudo;
            }
        }

        return EstadoDeLaDescarga::Lista;
    }

    /**
     * Las descargas que siguen vivas: las que todavía no cumplen sus días de vida, las que el borrado diario no quita.
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeVivas(Builder $consulta): void
    {
        $consulta->where($consulta->qualifyColumn('created_at'), '>', self::finDeLaVida());
    }

    /**
     * Las descargas que ya pasaron sus días de vida.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', self::finDeLaVida());
    }

    /**
     * Antes de borrar la fila, sus archivos.
     */
    protected function pruning(): void
    {
        $this->deleteFileDirectory();
    }

    /**
     * Cuántos días vive una descarga: los de exportacion.dias_de_vida, al menos uno (exportacion-E12). La API los da con
     * la lista de Descargas, para que la app los diga.
     */
    public static function diasDeVida(): int
    {
        return max(1, (int) config('exportacion.dias_de_vida'));
    }

    /**
     * El instante hasta el que una descarga ya cumplió sus días de vida: una pedida en él o antes se borra.
     */
    private static function finDeLaVida(): CarbonInterface
    {
        return now()->subDays(self::diasDeVida());
    }
}
