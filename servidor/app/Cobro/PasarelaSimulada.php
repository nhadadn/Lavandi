<?php

namespace App\Cobro;

use App\Models\EventoPasarela;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * La pasarela simulada de local y de las pruebas: implementa la interfaz sin llamar a ningún proveedor
 * (openspec/changes/pago/design.md, decisiones 2 y 6; vault/formas-de-trabajo/ciclo-openspec-tdd.md). Solo existe en
 * local y en las pruebas (App\Cobro\Pasarelas).
 *
 * Se comporta como una pasarela de verdad en lo que el servidor necesita:
 * - cada petición va autenticada con la llave de la configuración, y sin llave no hay petición (pago-E17);
 * - cada aviso va firmado con esa llave, HMAC-SHA256 de su cuerpo en la cabecera X-Firma-Simulada, y uno sin esa firma
 *   no es auténtico (pago-E07);
 * - la orden lleva el monto del cobro y una referencia nueva, y su checkout es la página de checkout de desarrollo del
 *   propio servidor, /desarrollo/pasarela/checkout/{orden}, con los botones de aprobar y rechazar;
 * - lleva sus propios libros: cada aviso de aprobado que manda es un pago que acreditó, con el monto de su orden, y la
 *   conciliación los lee con pagosDelPeriodo(), como leería los de la API de una pasarela real (segunda adenda del
 *   2026-11-22 de ADR-0010, decisión 6). Las pruebas pueden acreditar un pago distinto o quitar uno, para simular una
 *   diferencia (administracion-E21).
 *
 * Las órdenes y los pagos de sus libros viven en la caché de la aplicación, no en la base: no son de la plataforma, sino
 * de la pasarela, y así duran entre una petición y otra en local, como los de una pasarela real. En las pruebas, la
 * caché es de memoria y nace vacía en cada una. Guarda en memoria las peticiones que recibe, para que las pruebas vean
 * qué le pidió el servidor y con qué llave. Es una sola instancia por aplicación: la misma que usa el servidor en una
 * prueba.
 */
#[Singleton]
final class PasarelaSimulada implements Pasarela
{
    /** El valor de pagos.pasarela y de eventos_pasarela.pasarela. */
    public const CODIGO = Pago::PASARELA_SIMULADA;

    /** La cabecera con la firma de cada aviso simulado. */
    public const CABECERA_DE_FIRMA = 'X-Firma-Simulada';

    /** Las órdenes que creó, con su monto, en la caché. */
    private const ORDENES = 'pasarela-simulada:ordenes';

    /** Los pagos que acreditó, por orden, en la caché. */
    private const PAGOS = 'pasarela-simulada:pagos';

    /**
     * Las peticiones que recibió, en orden.
     *
     * @var list<array{operacion: string, autorizacion: string, orden?: string, monto?: string, pago?: string, desde?: string, hasta?: string}>
     */
    private array $peticiones = [];

    /**
     * Las órdenes cuyo checkout ya venció.
     *
     * @var array<string, true>
     */
    private array $vencidas = [];

    public function codigo(): string
    {
        return self::CODIGO;
    }

    public function crearOrden(Pago $cobro): OrdenDePasarela
    {
        $autorizacion = $this->autorizacion();
        $orden = 'orden-simulada-'.Str::lower((string) Str::ulid());

        $this->peticiones[] = [
            'operacion' => 'crear_orden',
            'autorizacion' => $autorizacion,
            'orden' => $orden,
            'monto' => (string) $cobro->monto,
            'pago' => $cobro->id_publico,
        ];

        Cache::forever(self::ORDENES, [...$this->ordenes(), $orden => (string) $cobro->monto]);

        return new OrdenDePasarela($orden, $this->urlDelCheckout($orden));
    }

    /**
     * El checkout de la orden sigue vigente mientras una prueba no lo venza con vencer().
     */
    public function checkoutVigente(string $idOrden): ?string
    {
        $this->peticiones[] = [
            'operacion' => 'consultar_orden',
            'autorizacion' => $this->autorizacion(),
            'orden' => $idOrden,
        ];

        return isset($this->vencidas[$idOrden]) ? null : $this->urlDelCheckout($idOrden);
    }

    /**
     * Un aviso simulado es auténtico si trae en su cabecera la firma de su cuerpo exacto con la llave de la
     * configuración. Se compara en tiempo constante.
     */
    public function esAutentico(string $cuerpo, array $cabeceras): bool
    {
        $firma = $cabeceras[strtolower(self::CABECERA_DE_FIRMA)] ?? '';

        return $firma !== '' && hash_equals($this->firma($cuerpo), $firma);
    }

    /**
     * El aviso simulado es JSON, { "id": …, "tipo": "pago.aprobado", "orden": …, "creado_en": … }: pago.aprobado,
     * pago.rechazado y pago.vencido se traducen a su evento; cualquier otro tipo es otro.
     */
    public function traducir(string $cuerpo): AvisoDePasarela
    {
        $contenido = json_decode($cuerpo, true);

        if (! is_array($contenido) || ! is_string($contenido['id'] ?? null) || $contenido['id'] === '') {
            throw new AvisoNoReconocido('El aviso simulado no es JSON o no trae su identificador.');
        }

        $tipo = match ($contenido['tipo'] ?? null) {
            'pago.aprobado' => EventoPasarela::APROBADO,
            'pago.rechazado' => EventoPasarela::RECHAZADO,
            'pago.vencido' => EventoPasarela::VENCIDO,
            default => EventoPasarela::OTRO,
        };

        $orden = $contenido['orden'] ?? null;

        return new AvisoDePasarela($contenido['id'], $tipo, is_string($orden) ? $orden : null, $contenido);
    }

    /**
     * Los pagos de sus libros acreditados entre esos dos instantes, los dos incluidos, del más nuevo al más antiguo.
     */
    public function pagosDelPeriodo(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $this->peticiones[] = [
            'operacion' => 'pagos_del_periodo',
            'autorizacion' => $this->autorizacion(),
            'desde' => $desde->utc()->toIso8601ZuluString(),
            'hasta' => $hasta->utc()->toIso8601ZuluString(),
        ];

        $movimientos = [];

        foreach ($this->pagos() as $orden => $pago) {
            $pagadoEn = CarbonImmutable::parse($pago['pagado_en']);

            if ($pagadoEn->betweenIncluded($desde, $hasta)) {
                $movimientos[] = new MovimientoDePasarela((string) $orden, $pago['monto'], $pago['comision'], $pagadoEn);
            }
        }

        usort($movimientos, fn (MovimientoDePasarela $uno, MovimientoDePasarela $otro): int => $otro->pagadoEn <=> $uno->pagadoEn);

        return $movimientos;
    }

    /**
     * El aviso que mandaría la pasarela simulada para la orden, con su cuerpo y su cabecera de firma, para las pruebas y
     * las herramientas de desarrollo. Sin identificador, el aviso es nuevo. Un aviso de aprobado de una orden suya es un
     * pago que acreditó: si sus libros todavía no lo tienen, queda en ellos con el monto de la orden, a la hora de ahora.
     *
     * @param  string  $tipo  aprobado, rechazado, vencido o cualquier otro, que se traduce a otro
     * @param  array<string, mixed>  $datos  lo que se agrega al cuerpo, como lo que mandaría una pasarela real
     * @return array{cuerpo: string, cabeceras: array<string, string>}
     *
     * @throws PasarelaNoDisponible sin llave para firmarlo
     */
    public function avisoSimulado(string $idOrden, string $tipo, ?string $idEvento = null, array $datos = []): array
    {
        $cuerpo = (string) json_encode([
            'id' => $idEvento ?? 'evento-simulado-'.Str::lower((string) Str::ulid()),
            'tipo' => 'pago.'.$tipo,
            'orden' => $idOrden,
            'creado_en' => now()->utc()->toIso8601ZuluString(),
            ...$datos,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $aviso = ['cuerpo' => $cuerpo, 'cabeceras' => [self::CABECERA_DE_FIRMA => $this->firma($cuerpo)]];
        $monto = $this->ordenes()[$idOrden] ?? null;

        if ($tipo === EventoPasarela::APROBADO && $monto !== null && ! array_key_exists($idOrden, $this->pagos())) {
            $this->acreditar($idOrden, $monto);
        }

        return $aviso;
    }

    /**
     * La pasarela acredita en sus libros el pago de la orden, por ese monto, a esa hora o a la de ahora, con su comisión
     * si la trae. Si la orden ya tenía un pago, lo reemplaza: uno por orden. Lo usan los avisos de aprobado y las pruebas
     * que simulan un pago que la plataforma no tiene o un monto distinto (administracion-E21).
     */
    public function acreditar(string $idOrden, string $monto, ?CarbonImmutable $pagadoEn = null, ?string $comision = null): void
    {
        Cache::forever(self::PAGOS, [...$this->pagos(), $idOrden => [
            'monto' => $monto,
            'comision' => $comision,
            'pagado_en' => ($pagadoEn ?? CarbonImmutable::now())->utc()->toIso8601ZuluString(),
        ]]);
    }

    /**
     * La pasarela ya no tiene el pago de la orden en sus libros, como un pago que nunca le llegó. Solo para las pruebas
     * (administracion-E21).
     */
    public function quitarPago(string $idOrden): void
    {
        $pagos = $this->pagos();
        unset($pagos[$idOrden]);

        Cache::forever(self::PAGOS, $pagos);
    }

    /**
     * Vence el checkout de la orden, como cuando una pasarela real lo da por caducado. Solo para las pruebas.
     */
    public function vencer(string $idOrden): void
    {
        $this->vencidas[$idOrden] = true;
    }

    /**
     * Las peticiones que recibió, para las pruebas.
     *
     * @return list<array{operacion: string, autorizacion: string, orden?: string, monto?: string, pago?: string, desde?: string, hasta?: string}>
     */
    public function peticiones(): array
    {
        return $this->peticiones;
    }

    /**
     * Las órdenes que creó, con su monto.
     *
     * @return array<string, string>
     */
    private function ordenes(): array
    {
        return Cache::get(self::ORDENES, []);
    }

    /**
     * Los pagos de sus libros, por orden.
     *
     * @return array<string, array{monto: string, comision: string|null, pagado_en: string}>
     */
    private function pagos(): array
    {
        return Cache::get(self::PAGOS, []);
    }

    /**
     * La cabecera con que se autentica cada petición: la llave de la configuración del entorno, como la privada de una
     * pasarela real.
     *
     * @throws PasarelaNoDisponible sin llave
     */
    private function autorizacion(): string
    {
        return 'Bearer '.$this->llave();
    }

    /**
     * @throws PasarelaNoDisponible sin llave
     */
    private function llave(): string
    {
        $llave = (string) config('pasarela.simulada.llave');

        if ($llave === '') {
            throw new PasarelaNoDisponible('Falta la llave de la pasarela simulada (PASARELA_SIMULADA_LLAVE).');
        }

        return $llave;
    }

    /**
     * La firma del aviso: HMAC-SHA256 del cuerpo con la llave de la configuración, como firman sus avisos las pasarelas
     * reales.
     *
     * @throws PasarelaNoDisponible sin llave
     */
    private function firma(string $cuerpo): string
    {
        return hash_hmac('sha256', $cuerpo, $this->llave());
    }

    private function urlDelCheckout(string $orden): string
    {
        return route('desarrollo.pasarela.checkout', $orden);
    }
}
