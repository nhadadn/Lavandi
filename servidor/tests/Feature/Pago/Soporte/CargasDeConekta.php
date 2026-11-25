<?php

namespace Tests\Feature\Pago\Soporte;

/**
 * Las cargas de Conekta con que se prueba App\Cobro\PasarelaConekta (tarea 3.2 de openspec/changes/pago/tasks.md): lo
 * que responde su API y lo que mandan sus avisos.
 *
 * GRADO E, SIN VERIFICAR. Ninguna es una captura: salen de la documentación pública de Conekta, consultada el
 * 2026-11-22 (las fuentes van en cada una), recortadas a lo que lee el servidor y con los mismos nombres de campos. Las
 * llaves del sandbox todavía no existen (C-170). La tarea 3.3 las cambia por capturas del sandbox, con la cuenta de
 * prueba de Arthur; lo que no se pudo saber sin él está en openspec/changes/pago/design.md, "Open Questions".
 *
 * Los montos van en centavos y las fechas en segundos desde 1970, como en la documentación. Ninguna lleva una llave: el
 * verificador de secretos revisa también las pruebas.
 */
final class CargasDeConekta
{
    /** La orden de los ejemplos de la documentación (order_response de "Create order"). */
    public const ORDEN = 'ord_2tMtQQpDvfnNjiuFG';

    /** El checkout de los ejemplos de la documentación (order_response_checkout de "Create order"). */
    public const CHECKOUT = '6fca054a-8519-4c43-971e-cea35cc519bb';

    /** La URL del checkout redirigido (HostedPayment) de los ejemplos de la documentación. */
    public const URL_DEL_CHECKOUT = 'https://pay.conekta.io/checkout/6fca054a85194c43971ecea35cc519bb';

    /**
     * La respuesta de POST /orders con su checkout redirigido (HostedPayment), y la de GET /orders/{id}, que trae la misma
     * orden.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/reference/createorder y /reference/getorderbyid (los esquemas
     * order_response y order_response_checkout, y "Respuesta de la orden (recortada)" de /docs/componente-de-pago),
     * consultadas el 2026-11-22. La tarea 3.3 la cambia por una captura del sandbox.
     *
     * @param  string  $estado  payment_status: pending_payment, paid, declined, expired, canceled…
     * @return array<string, mixed>
     */
    public static function orden(
        string $idOrden = self::ORDEN,
        int $centavos = 18000,
        ?int $venceEn = null,
        string $estado = 'pending_payment',
        ?string $url = self::URL_DEL_CHECKOUT,
    ): array {
        $creadaEn = 1676328434;

        return [
            'livemode' => false,
            'amount' => $centavos,
            'currency' => 'MXN',
            'payment_status' => $estado,
            'amount_refunded' => 0,
            'customer_info' => [
                'email' => 'persona@ejemplo.com',
                'phone' => '5522997233',
                'name' => 'Persona de prueba',
                'corporate' => false,
                'object' => 'customer_info',
            ],
            'object' => 'order',
            'id' => $idOrden,
            'metadata' => [],
            'created_at' => $creadaEn,
            'updated_at' => $creadaEn,
            'checkout' => [
                'id' => self::CHECKOUT,
                'object' => 'checkout',
                'type' => 'HostedPayment',
                'name' => 'ord-2tNDzhA4Akmzj11AS',
                'livemode' => false,
                'status' => 'Issued',
                'allowed_payment_methods' => ['card'],
                'expires_at' => $venceEn ?? 1772171999,
                'url' => $url,
            ],
        ];
    }

    /**
     * La respuesta de error de la API.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/reference/createorder (los esquemas error y details_error, con sus
     * ejemplos), consultada el 2026-11-22. La tarea 3.3 la cambia por una captura del sandbox.
     *
     * @return array<string, mixed>
     */
    public static function error(
        string $tipo = 'authentication_error',
        string $codigo = 'conekta.errors.authentication.missing_key',
        string $mensaje = 'Acceso no autorizado.',
    ): array {
        return [
            'details' => [[
                'code' => $codigo,
                'param' => null,
                'message' => $mensaje,
                'debug_message' => 'Please include your access key in your request.',
            ]],
            'log_id' => '507f1f77bcf86cd799439011',
            'type' => $tipo,
            'object' => 'error',
        ];
    }

    /**
     * El aviso order.paid de un pago con tarjeta: la orden pagada, con su cargo pagado.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/docs/order, "Order Pagado Tarjetas - order.paid", consultada el
     * 2026-11-22; recortado, con la orden y el monto de la prueba. El cargo trae, como en la documentación, el mes y el año
     * de vencimiento de la tarjeta, que no se guardan (pago-E05). La tarea 3.3 lo cambia por una captura del sandbox.
     */
    public static function avisoOrdenPagada(string $idOrden = self::ORDEN, string $idEvento = '58740be5dba34d123c027a70', int $centavos = 18000): string
    {
        return self::aviso('order.paid', $idEvento, [
            ...self::ordenDelAviso($idOrden, $centavos, 'paid'),
            'charges' => self::lista([
                self::cargo($idOrden, $centavos, 'paid', ['paid_at' => 1524845720]),
            ]),
        ]);
    }

    /**
     * El aviso order.declined: la orden del checkout cuyo pago se declinó, con su cargo declinado.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/docs/order, "Orden Declinada - order.declined", consultada el
     * 2026-11-22; recortado, con la orden y el monto de la prueba. La documentación no trae su payment_status. La tarea 3.3
     * lo cambia por una captura del sandbox.
     */
    public static function avisoOrdenDeclinada(string $idOrden = self::ORDEN, string $idEvento = '637306fb5eeaad00015eeb6f', int $centavos = 18000): string
    {
        $orden = self::ordenDelAviso($idOrden, $centavos, null);

        return self::aviso('order.declined', $idEvento, [
            ...$orden,
            'channel' => [
                'segment' => 'Checkout',
                'checkout_request_id' => '3bd5f194-aeba-496c-afc8-8628009b1843',
                'checkout_request_type' => 'Integration',
                'id' => 'channel_2sw3RECXQs4aHrJE1',
            ],
            'charges' => self::lista([
                self::cargo($idOrden, $centavos, 'declined', [
                    'failure_code' => 'invalid_card',
                    'failure_message' => 'El número de la tarjeta es inválido.',
                ]),
            ]),
        ]);
    }

    /**
     * El aviso order.expired: la orden cuya fecha de pago venció sin pagarse.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/docs/order, "Orden Expirada - order.expired", consultada el
     * 2026-11-22; recortado, con la orden y el monto de la prueba. El ejemplo de la documentación es de SPEI, no de un
     * checkout con tarjeta. La tarea 3.3 lo cambia por una captura del sandbox.
     */
    public static function avisoOrdenExpirada(string $idOrden = self::ORDEN, string $idEvento = '6372983ddfd6a70001e5eca4', int $centavos = 18000): string
    {
        return self::aviso('order.expired', $idEvento, self::ordenDelAviso($idOrden, $centavos, 'expired'));
    }

    /**
     * Un aviso de la orden que no cambia su pago, como order.created o order.pending_payment.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/docs/order, "Orden Creada - order.created" y "Orden pago pendiente -
     * order.pending_payment", consultada el 2026-11-22; recortado. La tarea 3.3 lo cambia por una captura del sandbox.
     */
    public static function avisoDeLaOrden(string $tipo, string $idOrden = self::ORDEN, string $idEvento = '58740be5dba34d123c027a71'): string
    {
        return self::aviso($tipo, $idEvento, self::ordenDelAviso($idOrden, 18000, 'pending_payment'));
    }

    /**
     * Un aviso de un cargo de la orden, como charge.paid o charge.declined, con el cargo en data.object.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/docs/charge, los ejemplos de charge.paid y charge.declined, consultada
     * el 2026-11-22; recortado, con la orden y el monto de la prueba. La tarea 3.3 lo cambia por una captura del sandbox.
     */
    public static function avisoDelCargo(string $tipo, string $idOrden = self::ORDEN, string $idEvento = '62cb51180211a6615fe3b70a'): string
    {
        $estado = $tipo === 'charge.paid' ? 'paid' : 'declined';

        return self::aviso($tipo, $idEvento, self::cargo($idOrden, 18000, $estado, $estado === 'paid' ? ['paid_at' => 1657412015] : [
            'failure_code' => 'restricted_card',
            'failure_message' => 'La tarjeta fue rechazada por el antifraude del banco.',
        ]));
    }

    /**
     * Una orden pagada como la trae la lista de GET /orders: con su cargo pagado, su fecha de pago y su comisión.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/reference/getorders (los esquemas order_response y
     * charges_data_response, con paid_at y fee) y /reference/next ("orders json example"), consultadas el 2026-11-22;
     * recortada. La tarea 3.3 la cambia por una captura del sandbox.
     *
     * @return array<string, mixed>
     */
    public static function ordenPagadaDeLaLista(string $idOrden, int $centavos, ?int $comision, int $pagadaEn, int $creadaEn): array
    {
        $cargo = self::cargo($idOrden, $centavos, 'paid', ['paid_at' => $pagadaEn, 'created_at' => $creadaEn]);

        if ($comision === null) {
            unset($cargo['fee']);
        } else {
            $cargo['fee'] = $comision;
        }

        return [
            ...self::ordenDelAviso($idOrden, $centavos, 'paid'),
            'created_at' => $creadaEn,
            'updated_at' => $pagadaEn,
            'charges' => self::lista([$cargo]),
        ];
    }

    /**
     * Una página de GET /orders, de la más nueva a la más antigua, con su cursor.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/reference/getorders (get_orders_response: data, has_more, object,
     * next_page_url y previous_page_url) y /reference/next (el cursor next es el último id de la página), consultadas el
     * 2026-11-22. Que la lista va de la más nueva a la más antigua no lo dice la documentación: lo confirma la tarea 3.3,
     * que cambia esta carga por una captura del sandbox.
     *
     * @param  list<array<string, mixed>>  $ordenes
     * @return array<string, mixed>
     */
    public static function paginaDeOrdenes(array $ordenes, bool $hayMas): array
    {
        $ultima = $ordenes === [] ? null : $ordenes[array_key_last($ordenes)]['id'];
        $primera = $ordenes === [] ? null : $ordenes[0]['id'];

        return [
            'next_page_url' => $hayMas ? "https://api.conekta.io/orders?limit=250&next={$ultima}" : null,
            'previous_page_url' => $primera === null ? null : "https://api.conekta.io/orders?limit=250&previous={$primera}",
            'has_more' => $hayMas,
            'object' => 'list',
            'data' => $ordenes,
        ];
    }

    /**
     * El evento como lo manda el webhook: lo que pasó en data.object, y su id y su type arriba.
     *
     * Grado E, SIN VERIFICAR: developers.conekta.com/docs/order y /docs/autenticación-webhooks (notification_payload),
     * consultadas el 2026-11-22.
     *
     * @param  array<string, mixed>  $objeto
     */
    private static function aviso(string $tipo, string $idEvento, array $objeto): string
    {
        return (string) json_encode([
            'data' => ['object' => $objeto, 'previous_attributes' => (object) []],
            'livemode' => false,
            'webhook_status' => 'pending',
            'id' => $idEvento,
            'object' => 'event',
            'type' => $tipo,
            'created_at' => 1599496065,
            'webhook_logs' => [[
                'id' => 'webhl_xtwgFC2EhrSTNKQ',
                'url' => 'http://requestb.in/1467d141',
                'failed_attempts' => 0,
                'last_http_response_status' => -1,
                'object' => 'webhook_log',
                'last_attempted_at' => 0,
            ]],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * La orden de un aviso, sin sus cargos.
     *
     * @return array<string, mixed>
     */
    private static function ordenDelAviso(string $idOrden, int $centavos, ?string $estado): array
    {
        $orden = [
            'livemode' => false,
            'amount' => $centavos,
            'currency' => 'MXN',
            'payment_status' => $estado,
            'amount_refunded' => 0,
            'customer_info' => [
                'email' => 'persona@ejemplo.com',
                'phone' => '5555555555',
                'name' => 'Persona de prueba',
                'object' => 'customer_info',
            ],
            'object' => 'order',
            'id' => $idOrden,
            'metadata' => [],
            'created_at' => 1599496065,
            'updated_at' => 1599496065,
        ];

        if ($estado === null) {
            unset($orden['payment_status']);
        }

        return $orden;
    }

    /**
     * Un cargo con tarjeta de la orden. El método de pago trae lo que trae en la documentación: el nombre, el mes y el año
     * de vencimiento, los últimos cuatro dígitos y la marca.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function cargo(string $idOrden, int $centavos, string $estado, array $datos = []): array
    {
        return [
            'id' => '5ae34c95b795b071223e21a2',
            'livemode' => false,
            'created_at' => 1599496065,
            'currency' => 'MXN',
            'payment_method' => [
                'name' => 'Persona de prueba',
                'exp_month' => '02',
                'exp_year' => '22',
                'auth_code' => '571003',
                'object' => 'card_payment',
                'type' => 'credit',
                'last4' => '5589',
                'brand' => 'mc',
                'issuer' => 'BANAMEX',
                'account_type' => 'CLASICA',
                'country' => 'MX',
                'fraud_score' => 33,
                'fraud_indicators' => [],
            ],
            'object' => 'charge',
            'description' => 'Payment from order',
            'status' => $estado,
            'amount' => $centavos,
            'fee' => 708,
            'customer_id' => '',
            'order_id' => $idOrden,
            ...$datos,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $datos
     * @return array<string, mixed>
     */
    private static function lista(array $datos): array
    {
        return ['object' => 'list', 'has_more' => false, 'total' => count($datos), 'data' => $datos];
    }
}
