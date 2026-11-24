<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un reporte del formulario de Soporte: la descripción de la persona, ligada a su cuenta y, si ella quiere, a uno de sus
 * pedidos (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #16; openspec/changes/perfil-y-soporte/design.md).
 *
 * La descripción se guarda en mensaje y la fecha es created_at (perfil-usuario-E11). La plaza la resuelve
 * App\Soporte\RecepcionDeReporte (decisión 27 de acta-2026-11-20-modelo-y-pasarela); queda vacía si la cuenta no tiene
 * de dónde tomarla, y el reporte se guarda igual (perfil-usuario-E17). No tiene identificador público: la API no lo
 * expone.
 *
 * A quién le llega y cómo se le responde a la persona es DD-108 (perfil-usuario-E14): mientras tanto, solo se guarda.
 */
class ReporteSoporte extends Modelo
{
    /** El único estado mientras no se responda DD-108: el reporte llegó y nadie lo ha atendido. */
    public const RECIBIDO = 'recibido';

    protected $table = 'reportes_soporte';

    protected $primaryKey = 'id_reportes_soporte';

    protected $fillable = ['id_usuarios', 'id_pedidos', 'id_plazas', 'mensaje'];

    /**
     * Nace recibido, como el valor por omisión de la base.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => self::RECIBIDO,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'atendido_en' => 'immutable_datetime',
        ];
    }

    /**
     * La cuenta que mandó el reporte, también si después se borró en suave. Qué se conserva de una cuenta borrada es
     * DD-109 (perfil-usuario-E16).
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios')->withTrashed();
    }

    /**
     * El pedido del reporte; null si la persona no eligió ninguno.
     *
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedidos');
    }

    /**
     * La plaza del reporte; null si la cuenta no tenía de dónde tomarla (perfil-usuario-E17).
     *
     * @return BelongsTo<Plaza, $this>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'id_plazas');
    }
}
