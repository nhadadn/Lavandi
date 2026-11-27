<?php

namespace App\Models;

/**
 * Un pago que acreditó la pasarela, como lo da su API, para conciliarlo con los de la plataforma
 * (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6; segunda adenda del 2026-11-22 de ADR-0010, decisiones 6
 * y 7; openspec/changes/administracion/design.md, "Tablas y migración").
 *
 * Una fila por orden de la pasarela, que se lee al conciliar un periodo (App\Finanzas\Conciliacion). No tiene plaza: la
 * de un movimiento es la del pedido con el que cuadra, por (pasarela, id_pasarela_orden) con pagos, y uno sin pedido es
 * una diferencia (lista sin_plaza de config/modelo.php). No guarda ningún dato de tarjeta.
 */
class MovimientoPasarela extends Modelo
{
    protected $table = 'movimientos_pasarela';

    protected $primaryKey = 'id_movimientos_pasarela';

    protected $fillable = ['pasarela', 'id_pasarela_orden', 'monto', 'comision', 'pagado_en'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'comision' => 'decimal:2',
            'pagado_en' => 'immutable_datetime',
        ];
    }
}
