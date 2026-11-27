<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDelPedido;
use App\Models\Concerns\TieneIdPublico;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un pago del pedido: su cobro y, si DD-012 lo pide, un ajuste (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A
 * #10; openspec/changes/pago/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md, "Dinero").
 *
 * Toma la plaza de su pedido, y la base rechaza otra (modelo-E06). La API lo identifica por su id_publico, un ULID. No
 * guarda ningún dato de tarjeta: lo único que lo liga con la pasarela es id_pasarela_orden, junto con pasarela
 * (pago-E05; NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3). No se borra nunca: el trigger impedir_cambio() lo impide.
 *
 * Solo el aviso auténtico de la pasarela lo da por pagado (pago-E07; ADR-0010, decisión 1), en App\Cobro\RecepcionDeAvisos.
 */
class Pago extends Modelo
{
    use HeredaLaPlazaDelPedido;
    use TieneIdPublico;

    /** El cobro del pedido: uno por pedido. */
    public const COBRO = 'cobro';

    /** La diferencia al conocer el monto final (pago-E15, DD-012). */
    public const AJUSTE = 'ajuste';

    public const TARJETA = 'tarjeta';

    /** El efectivo en tienda (pago-E12, DD-056). */
    public const EFECTIVO = 'efectivo';

    /** Espera el pago: nace así, y así queda mientras la pasarela no lo confirma. */
    public const PENDIENTE = 'pendiente';

    /** La pasarela confirmó el pago con su aviso (pago-E03). */
    public const PAGADO = 'pagado';

    /** La ficha de efectivo venció sin pagarse (pago-E13, DD-056). */
    public const VENCIDO = 'vencido';

    /** El último intento se rechazó; el mismo cobro se puede volver a intentar (pago-E04). */
    public const FALLIDO = 'fallido';

    /** Se canceló sin pagarse (DD-016). */
    public const CANCELADO = 'cancelado';

    /**
     * El valor de pasarela de la pasarela simulada, la de local y las pruebas (App\Cobro\PasarelaSimulada). El de la
     * pasarela del contrato es conekta (ADR-0010).
     */
    public const PASARELA_SIMULADA = 'simulada';

    /** El valor de pasarela de Conekta, la del contrato (App\Cobro\PasarelaConekta; ADR-0010). */
    public const PASARELA_CONEKTA = 'conekta';

    protected $table = 'pagos';

    protected $primaryKey = 'id_pagos';

    protected $fillable = [
        'id_pedidos',
        'id_plazas',
        'tipo',
        'metodo',
        'pasarela',
        'monto',
        'estado',
        'id_pasarela_orden',
        'referencia_efectivo',
        'vence_en',
        'pagado_en',
    ];

    /** Nace pendiente, como en la base. */
    protected $attributes = ['estado' => self::PENDIENTE];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'vence_en' => 'immutable_datetime',
            'pagado_en' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<Reembolso, $this>
     */
    public function reembolsos(): HasMany
    {
        return $this->hasMany(Reembolso::class, 'id_pagos');
    }
}
