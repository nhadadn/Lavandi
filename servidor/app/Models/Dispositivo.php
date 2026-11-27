<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un teléfono registrado para recibir avisos: el token de Firebase Cloud Messaging que la app manda al iniciar sesión o
 * cuando Firebase lo renueva, ligado a la cuenta (openspec/changes/avisos/design.md, "Tablas y migración"; ADR-0008;
 * ADR-0016).
 *
 * El token es único: un teléfono es de una sola cuenta a la vez (avisos-E21). No lleva plaza, porque la cuenta no
 * pertenece a una plaza, ni identificador público, porque la API no expone la fila. Un token que ya no sirve se borra
 * (avisos-E05, E06).
 *
 * Qué teléfonos reciben los avisos de un pedido lo decide App\Autorizacion\Autorizacion.
 */
class Dispositivo extends Modelo
{
    public const IOS = 'ios';

    public const ANDROID = 'android';

    /** Las plataformas que acepta la base, con su CHECK. */
    public const PLATAFORMAS = [self::IOS, self::ANDROID];

    protected $table = 'dispositivos';

    protected $primaryKey = 'id_dispositivos';

    protected $fillable = ['id_usuarios', 'token_fcm', 'plataforma', 'ultimo_uso_en'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ultimo_uso_en' => 'immutable_datetime',
        ];
    }

    /**
     * La cuenta a la que van los avisos de este teléfono.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuarios');
    }
}
