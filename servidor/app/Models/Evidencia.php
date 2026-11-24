<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDelPedido;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una foto de evidencia del pedido (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Almacenamiento de imágenes, "las
 * evidencias de entrega"; ADR-0006; openspec/changes/repartidor/design.md, "Tablas y migración").
 *
 * La foto vive en el almacenamiento privado de fotos del entorno; aquí, solo su ruta, que nunca sale en JSON
 * (repartidor-E23). id_usuarios es quien la tomó y tomada_en, cuándo. Solo se toma al marcar Entregado, y es opcional;
 * la base solo admite ese tipo. Si es obligatoria, si también se toma al recolectar y si es foto o firma espera DD-110
 * (repartidor-E24): si pide la de recolección, entra con su migración.
 *
 * Toma la plaza de su pedido, y la base rechaza otra (modelo-E06).
 */
class Evidencia extends Modelo
{
    use HeredaLaPlazaDelPedido;

    /** Al marcar Entregado (repartidor-E23). */
    public const ENTREGA = 'entrega';

    protected $table = 'evidencias';

    protected $primaryKey = 'id_evidencias';

    protected $fillable = ['id_pedidos', 'id_plazas', 'tipo', 'ruta', 'id_usuarios', 'tomada_en'];

    /** La foto se ve solo con su URL firmada, nunca por su ruta (ADR-0006). */
    protected $hidden = ['ruta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tomada_en' => 'immutable_datetime',
        ];
    }

    /**
     * Quien la tomó.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios');
    }
}
