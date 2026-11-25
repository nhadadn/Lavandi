<?php

namespace App\Models;

/**
 * Un aviso (webhook) de la pasarela, guardado antes de procesarlo (openspec/changes/pago/design.md, decisión 3;
 * vault/arquitectura/modelo-de-datos.md, "Dinero").
 *
 * El único (pasarela, id_pasarela_evento) hace que el mismo aviso no se guarde ni se procese dos veces (pago-E10). Su
 * tipo es el evento ya traducido por la pasarela, sin proveedor. Su contenido es el aviso como llegó, sin datos de
 * tarjeta. No tiene plaza: es de la pasarela, no de una plaza (lista sin_plaza de config/modelo.php).
 */
class EventoPasarela extends Modelo
{
    /** El pago de la orden se aprobó (pago-E03). */
    public const APROBADO = 'aprobado';

    /** El intento de pago se rechazó (pago-E04). */
    public const RECHAZADO = 'rechazado';

    /** La orden o la ficha venció sin pagarse (pago-E13, bloqueado por DD-056 y DD-012). */
    public const VENCIDO = 'vencido';

    /** Cualquier otro aviso de la pasarela: se guarda y no cambia nada. */
    public const OTRO = 'otro';

    protected $table = 'eventos_pasarela';

    protected $primaryKey = 'id_eventos_pasarela';

    protected $fillable = [
        'pasarela',
        'id_pasarela_evento',
        'tipo',
        'contenido',
        'recibido_en',
        'procesado_en',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contenido' => 'array',
            'recibido_en' => 'immutable_datetime',
            'procesado_en' => 'immutable_datetime',
        ];
    }
}
