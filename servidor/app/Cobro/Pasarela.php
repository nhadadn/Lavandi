<?php

namespace App\Cobro;

use App\Models\Pago;
use Carbon\CarbonImmutable;

/**
 * La pasarela de pago, detrás de la frontera del componente cobro (vault/arquitectura/c3-api.md, "Habla con la
 * pasarela"; openspec/changes/pago/design.md, decisión 2). La llamada a la pasarela vive solo detrás de esta interfaz:
 * ni la base ni la API nombran al proveedor (C-192; ADR-0016, regla 10).
 *
 * La implementan la de Conekta, la del contrato (App\Cobro\PasarelaConekta; ADR-0010), y la simulada de local y las
 * pruebas (App\Cobro\PasarelaSimulada). Si el cliente firma el cambio de pasarela (DD-079), la elegida es otra
 * implementación, con su ADR. Cuál cobra en cada entorno lo dice App\Cobro\Pasarelas.
 *
 * Ninguna implementación recibe ni devuelve datos de tarjeta: la tarjeta la captura y la resguarda la pasarela
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3).
 */
interface Pasarela
{
    /**
     * El código de la pasarela, el que se guarda en pagos.pasarela y en eventos_pasarela.pasarela.
     */
    public function codigo(): string;

    /**
     * Crea en la pasarela la orden del cobro, por el monto del cobro, con su checkout, autenticada con la llave de la
     * configuración del entorno (pago-E01 y pago-E17).
     *
     * @throws PasarelaNoDisponible si falta la llave o la pasarela no responde
     */
    public function crearOrden(Pago $cobro): OrdenDePasarela;

    /**
     * La URL del checkout de una orden si todavía se puede pagar en él, o null si ya venció o la pasarela ya no lo
     * acepta (pago-E01: un solo checkout vigente por cobro).
     *
     * @throws PasarelaNoDisponible si falta la llave o la pasarela no responde
     */
    public function checkoutVigente(string $idOrden): ?string;

    /**
     * Si el aviso (webhook) viene de verdad de la pasarela: su prueba de autenticidad, como una firma, sobre el cuerpo
     * exacto que llegó (pago-E07; ADR-0010, decisión 2). Solo un aviso auténtico se guarda y cambia algo. La de Conekta
     * se confirma en su sandbox (pago-E09, tarea 3.1).
     *
     * @param  array<string, string>  $cabeceras  por nombre en minúsculas
     *
     * @throws PasarelaNoDisponible si falta la llave con que se comprueba
     */
    public function esAutentico(string $cuerpo, array $cabeceras): bool;

    /**
     * Traduce el cuerpo de un aviso (webhook) de la pasarela a un evento sin proveedor: aprobado, rechazado, vencido u
     * otro, con la orden a la que se refiere (openspec/changes/pago/design.md, decisión 2).
     *
     * @throws AvisoNoReconocido si el cuerpo no es un aviso de esta pasarela
     */
    public function traducir(string $cuerpo): AvisoDePasarela;

    /**
     * Los pagos que la pasarela acreditó entre esos dos instantes, los dos incluidos, como los da su propia API: uno por
     * orden pagada, con el id de la orden, el monto, la comisión si la trae y la fecha del pago (segunda adenda del
     * 2026-11-22 de ADR-0010, decisión 6). Los lee la conciliación del Módulo D cuando la administración concilia un
     * periodo (administracion-E20), autenticada con la llave de la configuración del entorno (pago-E17).
     *
     * Con Conekta, se piden las órdenes pagadas de la más nueva hacia atrás hasta pasar el primer instante, y el corte por
     * fecha lo hace el servidor; se comprueba en su sandbox con la sección 3 de openspec/changes/pago/tasks.md.
     *
     * @return list<MovimientoDePasarela>
     *
     * @throws PasarelaNoDisponible si falta la llave o la pasarela no responde
     */
    public function pagosDelPeriodo(CarbonImmutable $desde, CarbonImmutable $hasta): array;
}
