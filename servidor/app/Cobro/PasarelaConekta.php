<?php

namespace App\Cobro;

use App\Models\EventoPasarela;
use App\Models\Pago;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * La pasarela de Conekta, la del contrato (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #10; ADR-0010),
 * detrás de la interfaz neutral del componente cobro (openspec/changes/pago/design.md, decisión 2). Habla con su API
 * REST v2 por el cliente HTTP de Laravel, autenticada con la llave privada del entorno (pago-E17; ADR-0012).
 *
 * - La orden lleva el monto del cobro en centavos, un solo concepto y su checkout redirigido (HostedPayment), solo con
 *   tarjeta: el efectivo espera DD-056. La tarjeta se escribe en la página de Conekta, nunca en la plataforma (pago-E02;
 *   NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3).
 * - Lo que se sabe de su API es grado E, SIN VERIFICAR: sale de su documentación pública, consultada el 2026-11-22
 *   (developers.conekta.com/reference/createorder, /reference/getorderbyid, /reference/autenticación). La tarea 3.3 de
 *   openspec/changes/pago/tasks.md lo comprueba en su sandbox; lo que queda por confirmar está en design.md, "Open
 *   Questions".
 *
 * Existe en todos los entornos: si cobra en el sandbox o en producción lo dice la llave, no el código.
 */
final class PasarelaConekta implements Pasarela
{
    /** El valor de pagos.pasarela y de eventos_pasarela.pasarela. */
    public const CODIGO = Pago::PASARELA_CONEKTA;

    /** La API de Conekta, la misma para el sandbox y para producción. */
    public const URL_DE_LA_API = 'https://api.conekta.io';

    /** La versión de la API que se pide en la cabecera Accept, la vigente de su documentación el 2026-11-22. */
    public const VERSION_DE_LA_API = '2.3.0';

    /**
     * Cuánto dura el checkout desde que se crea su orden: una hora. Si vence, pedir el checkout otra vez crea otra orden
     * para el mismo cobro (pago-E01). Conekta acepta de 5 minutos a 365 días (grado E).
     */
    public const VIGENCIA_DEL_CHECKOUT_EN_MINUTOS = 60;

    /** El concepto de la orden y la razón del pago que muestra el checkout. No lleva la marca (CLAUDE.md §5). */
    private const CONCEPTO = 'Pedido de lavandería';

    /** Cuántas órdenes se piden por página para conciliar: el máximo que acepta Conekta (grado E, /reference/getorders). */
    private const ORDENES_POR_PAGINA = 250;

    /** Los estados de la orden en que su checkout ya no se puede pagar. */
    private const ORDEN_SIN_CHECKOUT = ['expired', 'canceled', 'voided'];

    /** Cuánto se espera a Conekta, en segundos. */
    private const ESPERA_PARA_CONECTAR = 5;

    private const ESPERA_DE_LA_RESPUESTA = 20;

    public function __construct(private readonly DireccionDeRegreso $regreso) {}

    public function codigo(): string
    {
        return self::CODIGO;
    }

    /**
     * POST /orders: la orden por el monto del cobro, con su checkout redirigido. Devuelve el id de la orden y la URL del
     * checkout.
     */
    public function crearOrden(Pago $cobro): OrdenDePasarela
    {
        $respuesta = $this->enviar('crear la orden', fn (PendingRequest $api): Response => $api->post('/orders', $this->orden($cobro)));

        $id = $respuesta->json('id');
        $url = $respuesta->json('checkout.url');

        if (! is_string($id) || $id === '' || ! is_string($url) || $url === '') {
            throw new PasarelaNoDisponible('Conekta no devolvió la orden con la URL de su checkout.');
        }

        return new OrdenDePasarela($id, $url);
    }

    /**
     * GET /orders/{id}: el checkout sigue vigente mientras no haya vencido y su orden no esté vencida, cancelada ni
     * anulada. Una orden pagada, o con un intento declinado, conserva su checkout: crear otra dejaría dos órdenes que se
     * podrían pagar por el mismo cobro. Una orden que Conekta no tiene no tiene checkout.
     */
    public function checkoutVigente(string $idOrden): ?string
    {
        $respuesta = $this->enviar(
            'consultar la orden',
            fn (PendingRequest $api): Response => $api->get('/orders/'.rawurlencode($idOrden)),
            seAceptaQueNoExista: true,
        );

        if ($respuesta->notFound()) {
            return null;
        }

        $url = $respuesta->json('checkout.url');
        $venceEn = $respuesta->json('checkout.expires_at');

        if (! is_string($url) || $url === '' || in_array($respuesta->json('payment_status'), self::ORDEN_SIN_CHECKOUT, true)) {
            return null;
        }

        if (is_int($venceEn) && $venceEn <= CarbonImmutable::now()->getTimestamp()) {
            return null;
        }

        return $url;
    }

    /**
     * Ningún aviso de Conekta es auténtico todavía: cómo firma sus avisos se confirma en su sandbox, con la tarea 3.1
     * (pago-E09; ADR-0010, decisión 2; DD-102, punto 2). Mientras tanto el aviso no se guarda ni cambia nada, y no recibe
     * 200: Conekta lo reintenta durante 24 horas (grado E, /docs/reintentos-de-notificación).
     *
     * @throws PasarelaNoDisponible siempre, hasta la tarea 3.1
     */
    public function esAutentico(string $cuerpo, array $cabeceras): bool
    {
        throw new PasarelaNoDisponible('La autenticidad de los avisos de Conekta espera la tarea 3.1 (pago-E09; DD-102, punto 2).');
    }

    /**
     * El aviso de Conekta es un evento, { "id": …, "type": …, "data": { "object": … } } (grado E, /docs/order y
     * /docs/charge). Se traducen los de la orden, los que su guía del checkout pide escuchar (/docs/componente-de-pago):
     * order.paid es aprobado, order.declined es rechazado y order.expired es vencido. Cualquier otro, también los de sus
     * cargos (charge.paid, charge.declined), es otro: se guarda y no cambia el cobro, porque el checkout permite volver a
     * intentar y la orden es la que dice si se pagó. La orden es la del objeto si es una orden, o la de su order_id.
     */
    public function traducir(string $cuerpo): AvisoDePasarela
    {
        $contenido = json_decode($cuerpo, true);

        if (! is_array($contenido) || ! is_string($contenido['id'] ?? null) || $contenido['id'] === '' || ! is_string($contenido['type'] ?? null)) {
            throw new AvisoNoReconocido('El aviso de Conekta no es JSON o no trae su id y su type.');
        }

        $tipo = match ($contenido['type']) {
            'order.paid' => EventoPasarela::APROBADO,
            'order.declined' => EventoPasarela::RECHAZADO,
            'order.expired' => EventoPasarela::VENCIDO,
            default => EventoPasarela::OTRO,
        };

        return new AvisoDePasarela($contenido['id'], $tipo, $this->ordenDelAviso($contenido), $contenido);
    }

    /**
     * GET /orders?payment_status=paid, por cursor: Conekta no filtra por la fecha del pago, así que se piden sus órdenes
     * pagadas de la más nueva hacia atrás, de 250 en 250, y el corte por fecha lo hace el servidor (segunda adenda del
     * 2026-11-22 de ADR-0010, decisión 6). El cursor de la página siguiente es el id de la última orden de la página
     * (grado E, /reference/next).
     *
     * Se para cuando Conekta no tiene más, o cuando la última orden de la página se creó antes del inicio del periodo
     * menos la vigencia de su checkout: una orden con tarjeta se paga en su checkout, así que una creada antes ya no
     * puede tener su pago en el periodo. El movimiento de cada orden es la suma de sus cargos pagados, con su comisión
     * si la trae y la fecha del último pago.
     */
    public function pagosDelPeriodo(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $limite = $desde->subMinutes(self::VIGENCIA_DEL_CHECKOUT_EN_MINUTOS)->getTimestamp();
        $movimientos = [];
        $cursor = null;

        do {
            $consulta = ['payment_status' => 'paid', 'limit' => self::ORDENES_POR_PAGINA, ...($cursor === null ? [] : ['next' => $cursor])];
            $pagina = $this->enviar('leer las órdenes pagadas', fn (PendingRequest $api): Response => $api->get('/orders', $consulta));
            $ordenes = array_values(array_filter((array) $pagina->json('data'), is_array(...)));

            foreach ($ordenes as $orden) {
                $movimiento = $this->movimientoDe($orden);

                if ($movimiento !== null && $movimiento->pagadoEn->betweenIncluded($desde, $hasta)) {
                    $movimientos[] = $movimiento;
                }
            }

            $ultima = $ordenes === [] ? null : $ordenes[array_key_last($ordenes)];
            $siguiente = is_string($ultima['id'] ?? null) ? $ultima['id'] : null;
            $creadaEn = $ultima['created_at'] ?? null;

            if ($pagina->json('has_more') === true && $siguiente !== null && $siguiente === $cursor) {
                throw new PasarelaNoDisponible('Conekta devolvió dos veces la misma página de órdenes pagadas.');
            }

            $cursor = $siguiente;
        } while ($pagina->json('has_more') === true && $cursor !== null && ! (is_int($creadaEn) && $creadaEn < $limite));

        usort($movimientos, fn (MovimientoDePasarela $uno, MovimientoDePasarela $otro): int => $otro->pagadoEn <=> $uno->pagadoEn);

        return $movimientos;
    }

    /**
     * El pago de una orden de la lista: sus cargos pagados, con su monto, su comisión si la traen y la fecha del último.
     * Una orden sin un cargo pagado con su fecha no tiene movimiento: si la plataforma la tiene pagada, la conciliación lo
     * muestra como un pago sin movimiento (administracion-E21).
     *
     * @param  array<array-key, mixed>  $orden
     */
    private function movimientoDe(array $orden): ?MovimientoDePasarela
    {
        $cargos = $orden['charges']['data'] ?? [];
        $centavos = 0;
        $comision = null;
        $pagadoEn = null;

        foreach (is_array($cargos) ? $cargos : [] as $cargo) {
            if (! is_array($cargo) || ($cargo['status'] ?? null) !== 'paid' || ! is_int($cargo['paid_at'] ?? null) || ! is_int($cargo['amount'] ?? null)) {
                continue;
            }

            $centavos += $cargo['amount'];
            $comision = is_int($cargo['fee'] ?? null) ? ($comision ?? 0) + $cargo['fee'] : $comision;
            $pagadoEn = max($pagadoEn ?? $cargo['paid_at'], $cargo['paid_at']);
        }

        if ($pagadoEn === null || ! is_string($orden['id'] ?? null)) {
            return null;
        }

        return new MovimientoDePasarela(
            $orden['id'],
            self::pesos($centavos),
            $comision === null ? null : self::pesos($comision),
            CarbonImmutable::createFromTimestamp($pagadoEn, 'UTC'),
        );
    }

    /**
     * La orden a la que se refiere el aviso: la del objeto si es una orden, la de su order_id si es un cargo, o ninguna.
     *
     * @param  array<array-key, mixed>  $contenido
     */
    private function ordenDelAviso(array $contenido): ?string
    {
        $objeto = $contenido['data']['object'] ?? null;

        if (! is_array($objeto)) {
            return null;
        }

        $orden = ($objeto['object'] ?? null) === 'order' ? ($objeto['id'] ?? null) : ($objeto['order_id'] ?? null);

        return is_string($orden) && $orden !== '' ? $orden : null;
    }

    /**
     * El cuerpo de la orden: el monto en centavos, el cliente, el concepto y el checkout redirigido.
     *
     * @return array<string, mixed>
     */
    private function orden(Pago $cobro): array
    {
        $checkout = [
            'type' => 'HostedPayment',
            'name' => self::CONCEPTO,
            'allowed_payment_methods' => ['card'],
            'monthly_installments_enabled' => false,
            'expires_at' => CarbonImmutable::now()->addMinutes(self::VIGENCIA_DEL_CHECKOUT_EN_MINUTOS)->getTimestamp(),
            'success_url' => $this->regreso->exito($cobro),
            'failure_url' => $this->regreso->error($cobro),
        ];

        return [
            'currency' => 'MXN',
            'customer_info' => $this->cliente($cobro->pedido->usuario),
            'line_items' => [['name' => self::CONCEPTO, 'unit_price' => self::centavos((string) $cobro->monto), 'quantity' => 1]],
            'metadata' => ['pago' => $cobro->id_publico],
            'checkout' => array_filter($checkout, fn (mixed $valor): bool => $valor !== null),
        ];
    }

    /**
     * Los datos que Conekta pide de quien paga: su nombre, o su correo si todavía no lo tiene (como lo muestra el panel),
     * su correo y su teléfono, si lo tiene. Ningún dato de tarjeta.
     *
     * @return array<string, string>
     */
    private function cliente(Usuario $cuenta): array
    {
        $cliente = ['name' => filled($cuenta->nombre) ? (string) $cuenta->nombre : $cuenta->correo, 'email' => $cuenta->correo];

        if (filled($cuenta->telefono)) {
            $cliente['phone'] = (string) $cuenta->telefono;
        }

        return $cliente;
    }

    /**
     * Le manda la petición a Conekta y devuelve su respuesta si salió bien. Cualquier otra cosa es que la pasarela no está
     * disponible, con su motivo para el registro: el código, el tipo de error y el log de Conekta, nunca la llave.
     *
     * @param  callable(PendingRequest): Response  $peticion
     *
     * @throws PasarelaNoDisponible
     */
    private function enviar(string $que, callable $peticion, bool $seAceptaQueNoExista = false): Response
    {
        try {
            $respuesta = $peticion($this->api());
        } catch (ConnectionException) {
            throw new PasarelaNoDisponible("Conekta no respondió al {$que}.");
        }

        if ($respuesta->successful() || ($seAceptaQueNoExista && $respuesta->notFound())) {
            return $respuesta;
        }

        $tipo = $respuesta->json('type');
        $log = $respuesta->json('log_id');

        throw new PasarelaNoDisponible(sprintf(
            'Conekta respondió %d al %s (%s, log %s).',
            $respuesta->status(),
            $que,
            is_string($tipo) ? $tipo : 'sin tipo',
            is_string($log) ? $log : 'sin log',
        ));
    }

    /**
     * El cliente de su API, con la llave privada del entorno, la versión y el idioma.
     *
     * @throws PasarelaNoDisponible sin llave
     */
    private function api(): PendingRequest
    {
        return Http::baseUrl(self::URL_DE_LA_API)
            ->withToken($this->llave())
            ->accept('application/vnd.conekta-v'.self::VERSION_DE_LA_API.'+json')
            ->withHeaders(['Accept-Language' => 'es'])
            ->asJson()
            ->connectTimeout(self::ESPERA_PARA_CONECTAR)
            ->timeout(self::ESPERA_DE_LA_RESPUESTA);
    }

    /**
     * @throws PasarelaNoDisponible sin llave
     */
    private function llave(): string
    {
        $llave = (string) config('pasarela.conekta.llave');

        if ($llave === '') {
            throw new PasarelaNoDisponible('Falta la llave privada de Conekta (PASARELA_CONEKTA_LLAVE).');
        }

        return $llave;
    }

    /**
     * Los centavos de un monto en pesos con dos decimales, "180.00", sin pasar por un flotante.
     *
     * @throws PasarelaNoDisponible si el monto no tiene esa forma
     */
    private static function centavos(string $monto): int
    {
        if (preg_match('/^(\d+)\.(\d{2})$/', $monto, $partes) !== 1) {
            throw new PasarelaNoDisponible("El monto del cobro no tiene la forma de pesos con dos decimales: «{$monto}».");
        }

        return (int) $partes[1] * 100 + (int) $partes[2];
    }

    /**
     * Los pesos con dos decimales de un monto en centavos, "180.00", sin pasar por un flotante.
     */
    private static function pesos(int $centavos): string
    {
        return sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
    }
}
