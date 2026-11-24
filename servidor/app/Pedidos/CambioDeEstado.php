<?php

namespace App\Pedidos;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\PedidoEvento;
use App\Models\Repartidor;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * El único componente que mueve el estado del pedido por mano de un socio (ADR-0021, decisión 4): la tabla de lo que la
 * lavandería puede hacer desde cada estado, la del repartidor (adenda propuesta en openspec/changes/repartidor/design.md),
 * y la operación que lo guarda.
 *
 * Cada cambio agrega su evento a pedido_eventos, con la hora y la cuenta que lo hizo, y cambia pedidos.id_estados_pedido
 * en la misma transacción (ADR-0016, segunda excepción). Ya guardado, publica EstadoDelPedidoCambiado, que se entrega al
 * confirmarse la transacción (avisos). Un cambio fuera de la tabla se rechaza con CambioDeEstadoNoPermitido y el pedido
 * no cambia.
 *
 * No decide quién ve el pedido: lo recibe ya leído por App\Autorizacion\Autorizacion. La confirmación por el pago es de
 * App\Pedidos\ConfirmacionDePedido.
 */
final class CambioDeEstado
{
    /**
     * Los botones de la lavandería, en el orden del contrato: "Recolectado → En proceso → Listo → En proceso de entrega"
     * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #3). Cada uno es el estado al que lleva el pedido.
     */
    public const BOTONES = [
        EstadoPedido::RECOLECTADO,
        EstadoPedido::EN_PROCESO,
        EstadoPedido::LISTO,
        EstadoPedido::EN_ENTREGA,
    ];

    /**
     * Los botones del repartidor, en el orden del contrato: "Recolectado → En tránsito → Entregado"
     * (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #3). Cada uno es el estado al que lleva el pedido: En
     * tránsito es en_entrega, el mismo paso operativo que "En proceso de entrega" de la lavandería (ADR-0021).
     */
    public const BOTONES_DEL_REPARTIDOR = [
        EstadoPedido::RECOLECTADO,
        EstadoPedido::EN_ENTREGA,
        EstadoPedido::ENTREGADO,
    ];

    private const YA_RESPONDIDO = 'El pedido ya fue respondido.';

    private const SIN_ACEPTAR = 'Primero acepta el pedido.';

    private const NO_ES_EL_SIGUIENTE = 'Ese no es el paso siguiente del pedido.';

    /**
     * Lo que la lavandería puede hacer desde cada estado: el estado al que llega (ADR-0021, decisión 2, y su adenda
     * propuesta en openspec/changes/pedidos-de-la-lavanderia/design.md). Desde un estado que no está aquí, como
     * por_pagar, rechazado, en_entrega o entregado, la lavandería no puede hacer nada.
     *
     * @var array<string, list<string>>
     */
    private const DE_LA_LAVANDERIA = [
        // Aceptar o rechazar con motivo (Módulo B #2).
        EstadoPedido::CONFIRMADO => [EstadoPedido::ACEPTADO, EstadoPedido::RECHAZADO],
        // Los botones, uno tras otro (Módulo B #3). Sin aceptar no hay botones (estados-E09).
        EstadoPedido::ACEPTADO => [EstadoPedido::RECOLECTADO],
        EstadoPedido::RECOLECTADO => [EstadoPedido::EN_PROCESO],
        EstadoPedido::EN_PROCESO => [EstadoPedido::LISTO],
        EstadoPedido::LISTO => [EstadoPedido::EN_ENTREGA],
        // Desde en_entrega, Entregado espera DD-022 (estados-E13).
    ];

    /**
     * Lo que el repartidor puede hacer desde cada estado, en un pedido que aceptó (adenda propuesta a ADR-0021, en
     * openspec/changes/repartidor/design.md; repartidor-E19 y E20):
     *
     * - Recolectado, desde aceptado: solo lo que la lavandería ya aceptó (Módulo B #2), como sus propios botones;
     * - En tránsito, desde listo: entre los dos, la lavandería marca En proceso y Listo (Módulo B #3);
     * - Entregado, desde en_entrega, también si fue la lavandería quien marcó En proceso de entrega.
     *
     * Desde un estado que no está aquí, el repartidor no puede hacer nada. Cómo ve el usuario estos cambios espera DD-022
     * y DD-011 (repartidor-E22).
     *
     * @var array<string, list<string>>
     */
    private const DEL_REPARTIDOR = [
        EstadoPedido::ACEPTADO => [EstadoPedido::RECOLECTADO],
        EstadoPedido::LISTO => [EstadoPedido::EN_ENTREGA],
        EstadoPedido::EN_ENTREGA => [EstadoPedido::ENTREGADO],
    ];

    /**
     * El botón que la lavandería puede tocar en un pedido en ese estado: el paso siguiente, o ninguno (estados-E06).
     */
    public static function botonSiguiente(string $estado): ?string
    {
        $siguientes = array_values(array_intersect(self::DE_LA_LAVANDERIA[$estado] ?? [], self::BOTONES));

        return $siguientes[0] ?? null;
    }

    /**
     * El botón que el repartidor puede tocar en un pedido en ese estado, si ya lo aceptó: el paso siguiente, o ninguno.
     */
    public static function botonSiguienteDelRepartidor(string $estado): ?string
    {
        return self::DEL_REPARTIDOR[$estado][0] ?? null;
    }

    /**
     * El repartidor toca un botón en el pedido que aceptó: el pedido pasa al estado de ese botón si es el paso siguiente
     * (repartidor-E19). Un salto, un regreso, el mismo botón otra vez, o un botón en un pedido que no aceptó, no cambian
     * nada (repartidor-E20). El evento queda con la cuenta del repartidor y su hora.
     *
     * Que el pedido sea suyo lo decide antes App\Autorizacion\Autorizacion::pedidosDelRepartidor(): uno de otro
     * repartidor no llega aquí (repartidor-E21). Aquí se revisa además, con el pedido bloqueado, que lo haya aceptado.
     *
     * @param  string  $estado  Uno de BOTONES_DEL_REPARTIDOR.
     *
     * @throws CambioDeEstadoNoPermitido
     */
    public function marcarComoRepartidor(Pedido $pedido, Repartidor $repartidor, string $estado): Pedido
    {
        if (! in_array($estado, self::BOTONES_DEL_REPARTIDOR, true)) {
            throw new CambioDeEstadoNoPermitido($pedido, self::NO_ES_EL_SIGUIENTE, $repartidor);
        }

        return $this->cambiar(
            $pedido,
            $repartidor->cuenta()->sole(),
            $estado,
            self::NO_ES_EL_SIGUIENTE,
            tabla: self::DEL_REPARTIDOR,
            repartidor: $repartidor,
            antes: function (Pedido $bloqueado) use ($repartidor): void {
                if (! $this->loAcepto($bloqueado, $repartidor)) {
                    throw new CambioDeEstadoNoPermitido($bloqueado, self::SIN_ACEPTAR, $repartidor);
                }
            },
        );
    }

    /**
     * La lavandería acepta el pedido que espera su respuesta (pedidos-lavanderia-E07). Uno ya respondido no cambia
     * (pedidos-lavanderia-E08).
     *
     * @throws CambioDeEstadoNoPermitido
     */
    public function aceptar(Pedido $pedido, Usuario $lavanderia): Pedido
    {
        return $this->cambiar($pedido, $lavanderia, EstadoPedido::ACEPTADO, self::YA_RESPONDIDO);
    }

    /**
     * La lavandería rechaza con su motivo el pedido que espera su respuesta (pedidos-lavanderia-E12). El motivo queda en
     * el evento. Uno ya respondido no cambia (pedidos-lavanderia-E08). Qué pasa después con el pedido y el cobro espera
     * DD-103 (pedidos-lavanderia-E14).
     *
     * @throws CambioDeEstadoNoPermitido
     */
    public function rechazar(Pedido $pedido, Usuario $lavanderia, string $motivo): Pedido
    {
        return $this->cambiar($pedido, $lavanderia, EstadoPedido::RECHAZADO, self::YA_RESPONDIDO, $motivo);
    }

    /**
     * La lavandería toca un botón: el pedido pasa al estado de ese botón si es el paso siguiente (estados-E03 y E04). Un
     * salto, un regreso o un botón en un pedido que no aceptó o que rechazó no cambian nada (estados-E05 y E09).
     *
     * @param  string  $estado  Uno de BOTONES.
     *
     * @throws CambioDeEstadoNoPermitido
     */
    public function marcar(Pedido $pedido, Usuario $lavanderia, string $estado): Pedido
    {
        if (! in_array($estado, self::BOTONES, true)) {
            throw new CambioDeEstadoNoPermitido($pedido, self::NO_ES_EL_SIGUIENTE);
        }

        return $this->cambiar($pedido, $lavanderia, $estado, self::NO_ES_EL_SIGUIENTE);
    }

    /**
     * Si el repartidor aceptó el pedido: es el repartidor del pedido y su oferta quedó aceptada (repartidor-E15).
     */
    private function loAcepto(Pedido $pedido, Repartidor $repartidor): bool
    {
        return $pedido->id_repartidores === $repartidor->getKey()
            && $pedido->asignaciones()
                ->where('id_repartidores', $repartidor->getKey())
                ->where('estado', PedidoAsignacion::ACEPTADO)
                ->exists();
    }

    /**
     * @param  string  $siNoSePuede  El mensaje de CambioDeEstadoNoPermitido si el cambio no está en la tabla.
     * @param  array<string, list<string>>  $tabla  Lo que puede hacer quien mueve el pedido, desde cada estado.
     * @param  Repartidor|null  $repartidor  Si lo mueve un repartidor, para responderle con su forma del pedido.
     * @param  (callable(Pedido): void)|null  $antes  Lo que se revisa con el pedido ya bloqueado, antes de la tabla.
     *
     * @throws CambioDeEstadoNoPermitido
     */
    private function cambiar(
        Pedido $pedido,
        Usuario $quien,
        string $nuevo,
        string $siNoSePuede,
        ?string $motivo = null,
        array $tabla = self::DE_LA_LAVANDERIA,
        ?Repartidor $repartidor = null,
        ?callable $antes = null,
    ): Pedido {
        return DB::transaction(function () use ($pedido, $quien, $nuevo, $siNoSePuede, $motivo, $tabla, $repartidor, $antes): Pedido {
            // Bloquea el pedido: dos toques a la vez no lo mueven dos veces.
            $bloqueado = Pedido::query()->whereKey($pedido->getKey())->lockForUpdate()->with('estado')->sole();

            if ($antes !== null) {
                $antes($bloqueado);
            }

            if (! in_array($nuevo, $tabla[$bloqueado->estado->codigo] ?? [], true)) {
                throw new CambioDeEstadoNoPermitido($bloqueado, $siNoSePuede, $repartidor);
            }

            $estado = EstadoPedido::query()->where('codigo', $nuevo)->sole();
            $bloqueado->forceFill(['id_estados_pedido' => $estado->getKey()])->save();

            /** @var PedidoEvento $evento */
            $evento = $bloqueado->eventos()->create([
                'id_estados_pedido' => $estado->getKey(),
                'id_usuarios' => $quien->getKey(),
                'motivo' => $motivo,
                // En UTC, como toda fecha que se guarda (App\Agenda\Ventana).
                'ocurrido_en' => CarbonImmutable::now()->utc(),
            ]);

            EstadoDelPedidoCambiado::dispatch($bloqueado->setRelation('estado', $estado), $evento->setRelation('estado', $estado));

            return $bloqueado;
        });
    }
}
