<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La identidad de una cuenta en Google o en Apple: el proveedor y su sujeto, el claim sub del token. La misma
 * identidad vuelve a entrar a la misma cuenta por su (proveedor, sujeto), que es único (registro-E16; ADR-0005).
 */
class CuentaExterna extends Modelo
{
    protected $table = 'cuentas_externas';

    protected $primaryKey = 'id_cuentas_externas';

    protected $fillable = ['proveedor', 'sujeto', 'correo'];

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios');
    }
}
