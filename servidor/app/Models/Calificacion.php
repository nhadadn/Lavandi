<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDeLaLavanderia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una calificación de 1 a 5 estrellas, con comentario opcional, de exactamente un calificado: la lavandería o el
 * repartidor (perfil-lavanderia-E16 y E17; ADR-0016). El perfil de la lavandería cuenta solo las suyas.
 *
 * Nació como base en perfil-de-lavanderia; repartidor le agregó la llave hacia repartidores e historial-y-calificacion,
 * la llave compuesta (id_pedidos, id_plazas) hacia pedidos (historial-E13). id_pedidos sigue opcional: las calificaciones
 * de prueba del perfil no tienen pedido. La calificación del servicio del usuario va con su pedido y con la lavandería
 * del pedido (historial-E12; App\Calificaciones\CalificacionDelServicio). Quién calificó, id_usuarios, nunca sale en el
 * perfil (perfil-lavanderia-E08; NSV-2026-001-v8.pdf:10:Cláusula X).
 */
class Calificacion extends Modelo
{
    use HeredaLaPlazaDeLaLavanderia;

    protected $table = 'calificaciones';

    protected $primaryKey = 'id_calificaciones';

    protected $fillable = ['id_pedidos', 'id_plazas', 'id_usuarios', 'id_lavanderias', 'estrellas', 'comentario'];

    protected $hidden = ['id_usuarios'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estrellas' => 'integer',
        ];
    }

    /**
     * Quien calificó.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios');
    }

    /**
     * El pedido calificado. Vacío en las calificaciones de prueba del perfil de la lavandería.
     *
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedidos');
    }
}
