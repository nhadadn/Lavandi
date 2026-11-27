<?php

namespace Tests\Feature\Avisos;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Avisos\Soporte\CambiosDeEstadoDePrueba;
use Tests\Feature\Avisos\Soporte\FcmSimulado;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * En cada cambio de estado del pedido, la API manda un mensaje de Firebase Cloud Messaging a los teléfonos de la cuenta
 * dueña del pedido, con el pedido y su estado nuevo, para que la app abierta en el seguimiento consulte de inmediato
 * (ADR-0007 y ADR-0008; NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3).
 *
 * Es un solo mensaje por cambio: si el estado es un momento, el mensaje es además el aviso visible (avisos-E09 a E12);
 * si no, solo lleva los datos y el teléfono no muestra nada (design.md, "Preguntas abiertas").
 *
 * Los botones de la lavandería son los de estados-del-pedido, tarea 2.1, sobre el pedido que la lavandería aceptó: aquí
 * los toca CambiosDeEstadoDePrueba con la operación real. "En proceso de entrega" (en_entrega) ya está sembrado y le
 * toca el mismo mensaje, solo con los datos.
 *
 * Escenario: avisos-E14 · Spec: openspec/changes/avisos/specs/avisos/spec.md · Tarea 3.2
 */
class CambioDeEstadoTest extends TestCase
{
    use AgendaDePrueba;
    use CambiosDeEstadoDePrueba;
    use EstadosDePrueba;
    use FcmSimulado;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Los botones de la lavandería que ya tienen estado sembrado, en su orden (Módulo B #3). */
    private const BOTONES_DE_LA_LAVANDERIA = [
        EstadoPedido::RECOLECTADO,
        EstadoPedido::EN_PROCESO,
        EstadoPedido::LISTO,
        EstadoPedido::EN_ENTREGA,
    ];

    private Usuario $cuenta;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->simularFcm();
        $this->cuenta = $this->cuentaDeUsuario();
        $this->pedido = $this->pedidoAceptadoDe($this->cuenta);
    }

    #[TestDox('avisos-E14: con cada botón de la lavandería, la api manda al teléfono del usuario un mensaje con el pedido y su estado nuevo')]
    public function test_avisos_e14_cada_boton_de_la_lavanderia_manda_el_mensaje_con_el_pedido_y_su_estado(): void
    {
        $telefono = $this->telefonoDe($this->cuenta);

        foreach (self::BOTONES_DE_LA_LAVANDERIA as $estado) {
            $this->cambiarComoLaLavanderia($this->pedido, $estado);
        }

        $this->assertSame(
            array_map(fn (string $estado): array => [
                'tipo' => 'cambio_de_estado',
                'pedido' => $this->pedido->id_publico,
                'estado' => $estado,
            ], self::BOTONES_DE_LA_LAVANDERIA),
            array_column($this->mensajesAlToken($telefono->token_fcm), 'data'),
        );
    }

    #[TestDox('avisos-E14: un cambio que no es momento manda solo los datos, sin aviso visible, y despierta a la app en iPhone')]
    public function test_avisos_e14_un_cambio_que_no_es_momento_manda_solo_los_datos(): void
    {
        $this->cambiarComoLaLavanderia($this->pedido, EstadoPedido::RECOLECTADO);
        $telefono = $this->telefonoDe($this->cuenta);

        $this->cambiarComoLaLavanderia($this->pedido, EstadoPedido::EN_PROCESO);

        [$mensaje] = $this->mensajesAlToken($telefono->token_fcm);
        $this->assertSame(EstadoPedido::EN_PROCESO, $mensaje['data']['estado']);
        $this->assertSame(['priority' => 'high'], $mensaje['android']);
        $this->assertSame(['content-available' => 1], $mensaje['apns']['payload']['aps']);
        $this->assertSame('background', $mensaje['apns']['headers']['apns-push-type']);
        $this->assertArrayNotHasKey('notification', $mensaje);
    }

    #[TestDox('avisos-E14: un cambio que es momento manda un solo mensaje: el aviso visible con el pedido y su estado')]
    public function test_avisos_e14_un_cambio_que_es_momento_manda_un_solo_mensaje(): void
    {
        $telefono = $this->telefonoDe($this->cuenta);

        $this->cambiarComoLaLavanderia($this->pedido, EstadoPedido::RECOLECTADO);

        $mensajes = $this->mensajesAlToken($telefono->token_fcm);
        $this->assertCount(1, $mensajes);
        $this->assertSame(EstadoPedido::RECOLECTADO, $mensajes[0]['data']['estado']);
        $this->assertArrayHasKey('notification', $mensajes[0]['android']);
    }

    #[TestDox('avisos-E14: el mensaje del cambio va solo a los teléfonos de la cuenta dueña del pedido')]
    public function test_avisos_e14_el_mensaje_va_solo_a_los_telefonos_de_la_cuenta_duena(): void
    {
        $this->cambiarComoLaLavanderia($this->pedido, EstadoPedido::RECOLECTADO);
        $propio = $this->telefonoDe($this->cuenta);
        $ajeno = $this->telefonoDe($this->cuentaDeUsuario());
        $deLaLavanderia = $this->telefonoDe($this->cuentaDeLaLavanderia($this->pedido));

        $this->cambiarComoLaLavanderia($this->pedido, EstadoPedido::EN_PROCESO);

        $this->assertCount(1, $this->mensajesAlToken($propio->token_fcm));
        $this->assertSame([], $this->mensajesAlToken($ajeno->token_fcm));
        $this->assertSame([], $this->mensajesAlToken($deLaLavanderia->token_fcm));
    }
}
