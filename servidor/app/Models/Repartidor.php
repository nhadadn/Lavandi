<?php

namespace App\Models;

use App\Models\Concerns\TieneIdPublico;
use Database\Factories\RepartidorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un socio repartidor (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C; openspec/changes/repartidor/design.md,
 * "Tablas y migración"; vault/arquitectura/modelo-de-datos.md).
 *
 * Su nombre, su teléfono, su correo y su foto viven en su cuenta (usuarios); aquí, su plaza, su tipo de vehículo y su
 * estado. Se registra desde la app y queda pendiente, sin plaza, hasta que la administración lo da de alta en una plaza
 * (repartidor-E01 y E05). Solo un repartidor activo ve y mueve pedidos (repartidor-E03 y E06); quién opera lo decide
 * App\Autorizacion\Autorizacion. La baja es borrado suave, con estado baja: sus entregas se conservan (repartidor-E07).
 *
 * La API lo identifica por su id_publico, un ULID (modelo-E15). No tiene documentos: la verificación documental está
 * excluida (Módulo C).
 */
class Repartidor extends Modelo
{
    /** Registrado desde la app, todavía sin alta ni plaza (repartidor-E01). */
    public const PENDIENTE = 'pendiente';

    /** Dado de alta en una plaza: ve y mueve los pedidos que se le asignan (repartidor-E05). */
    public const ACTIVO = 'activo';

    /** Suspendido desde el panel: no ve ni mueve pedidos hasta que se le quite (repartidor-E06). */
    public const SUSPENDIDO = 'suspendido';

    /** Dado de baja desde el panel: no inicia sesión, y sus entregas se conservan (repartidor-E07). */
    public const BAJA = 'baja';

    /** @use HasFactory<RepartidorFactory> */
    use HasFactory;

    use SoftDeletes;
    use TieneIdPublico;

    protected $table = 'repartidores';

    protected $primaryKey = 'id_repartidores';

    protected $fillable = ['id_plazas', 'id_usuarios', 'tipo_vehiculo', 'estado', 'aprobado_en'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aprobado_en' => 'immutable_datetime',
        ];
    }

    /**
     * La plaza en que se dio de alta; ninguna mientras está pendiente.
     *
     * @return BelongsTo<Plaza, $this>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'id_plazas');
    }

    /**
     * La cuenta del repartidor, con su nombre, su teléfono, su correo y su foto.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios');
    }

    /**
     * Los pedidos que se le ofrecieron, cada uno con su respuesta.
     *
     * @return HasMany<PedidoAsignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(PedidoAsignacion::class, 'id_repartidores');
    }

    /**
     * Los pedidos que aceptó y son suyos (pedidos.id_repartidores).
     *
     * @return HasMany<Pedido, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'id_repartidores');
    }
}
