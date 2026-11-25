<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso al celular de la administración, para promociones o para avisar de un mantenimiento: su título, su texto,
 * quién lo envió y a cuántos teléfonos salió (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7;
 * openspec/changes/administracion/design.md, "Tablas y migración").
 *
 * Sin plaza, el aviso es para todas las plazas; sin segmento, para todas las cuentas. Los criterios del segmento son
 * DD-113 (administracion-E24). El envío por FCM es la tarea 5.1 de administracion (administracion-E23).
 */
class AvisoMasivo extends Modelo
{
    protected $table = 'avisos_masivos';

    protected $primaryKey = 'id_avisos_masivos';

    protected $fillable = ['id_plazas', 'segmento', 'titulo', 'mensaje', 'id_usuarios', 'enviado_en', 'destinatarios'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segmento' => 'array',
            'enviado_en' => 'immutable_datetime',
            'destinatarios' => 'integer',
        ];
    }

    /**
     * La plaza del aviso; null si es para todas.
     *
     * @return BelongsTo<Plaza, $this>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'id_plazas');
    }

    /**
     * La cuenta de la administración que lo envió.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios')->withTrashed();
    }
}
