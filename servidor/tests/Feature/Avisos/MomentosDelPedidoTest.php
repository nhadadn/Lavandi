<?php

namespace Tests\Feature\Avisos;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\Dispositivo;
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
 * Los cuatro momentos de aviso que nombra el contrato: "Confirmación, recolección, listo para entrega y entregado"
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #17). Cada aviso va solo a los teléfonos de la cuenta dueña
 * del pedido, con el texto de su momento en config/avisos.php, la única lista de momentos (ADR-0008).
 *
 * La confirmación la publica la operación de confirmar, cuando la pasarela confirma el pago (C-188): aquí, la pasarela
 * simulada acredita el pago y el cobro confirma el pedido. Los demás cambios los publica la lavandería con sus botones (estados-del-pedido,
 * tarea 2.1), sobre el pedido que aceptó (CambiosDeEstadoDePrueba), y Entregado, el repartidor, cuyo botón llega con
 * repartidor (EstadosDePrueba::entregarComoElRepartidor()). FCM está simulado (FcmSimulado).
 *
 * El quinto momento espera DD-011 (avisos-E13, tarea 5.1).
 *
 * Escenarios: avisos-E09, avisos-E10, avisos-E11 y avisos-E12 · Spec: openspec/changes/avisos/specs/avisos/spec.md ·
 * Tarea 3.1
 */
class MomentosDelPedidoTest extends TestCase
{
    use AgendaDePrueba;
    use CambiosDeEstadoDePrueba;
    use EstadosDePrueba;
    use FcmSimulado;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->simularFcm();
        $this->cuenta = $this->cuentaDeUsuario();
    }

    #[TestDox('avisos-E09: al confirmarse el pedido, la api le manda al usuario el aviso de confirmación')]
    public function test_avisos_e09_al_confirmarse_el_pedido_llega_el_aviso_de_confirmacion(): void
    {
        $telefono = $this->telefonoDe($this->cuenta);
        $pedido = $this->pedidoPorPagarDe($this->cuenta);

        // Aceptar el resumen no avisa: el pedido todavía no está pagado (C-188).
        $this->assertSame([], $this->mensajesAFcm());

        $pedido = app(CobroSimulado::class)->pagar($pedido)->fresh();

        $this->assertAvisoDelMomento($telefono, $pedido, EstadoPedido::CONFIRMADO);
    }

    #[TestDox('avisos-E09: un aviso repetido del cobro no confirma dos veces ni avisa dos veces')]
    public function test_avisos_e09_un_aviso_repetido_del_cobro_no_avisa_dos_veces(): void
    {
        $telefono = $this->telefonoDe($this->cuenta);
        $pedido = $this->pedidoPorPagarDe($this->cuenta);

        app(CobroSimulado::class)->pagar($pedido);
        app(CobroSimulado::class)->pagar($pedido);

        $this->assertCount(1, $this->mensajesAlToken($telefono->token_fcm));
    }

    #[TestDox('avisos-E10: un pedido en Confirmado pasa a Recolectado y la api le manda al usuario el aviso de recolección')]
    public function test_avisos_e10_al_pasar_a_recolectado_llega_el_aviso_de_recoleccion(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $telefono = $this->telefonoDe($this->cuenta);

        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO);

        $this->assertAvisoDelMomento($telefono, $pedido, EstadoPedido::RECOLECTADO);
    }

    #[TestDox('avisos-E11: un pedido en En proceso pasa a Listo y la api le manda al usuario el aviso de listo para entrega')]
    public function test_avisos_e11_al_pasar_a_listo_llega_el_aviso_de_listo_para_entrega(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $telefono = $this->telefonoDe($this->cuenta);
        $this->llevarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO);

        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::LISTO);

        $this->assertAvisoDelMomento($telefono, $pedido, EstadoPedido::LISTO);
    }

    #[TestDox('avisos-E12: el pedido pasa a Entregado y la api le manda al usuario el aviso de entregado, y a ningún teléfono de otra cuenta')]
    public function test_avisos_e12_al_pasar_a_entregado_llega_el_aviso_y_a_ningun_telefono_de_otra_cuenta(): void
    {
        $otraCuenta = $this->cuentaDeUsuario();
        $telefonoAjeno = $this->telefonoDe($otraCuenta);
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $telefono = $this->telefonoDe($this->cuenta);
        $this->llevarComoLaLavanderia(
            $pedido,
            EstadoPedido::RECOLECTADO,
            EstadoPedido::EN_PROCESO,
            EstadoPedido::LISTO,
            EstadoPedido::EN_ENTREGA,
        );

        // Entregado es del repartidor (DD-022): su botón llega con repartidor (H8).
        $this->entregarComoElRepartidor($pedido);

        $this->assertAvisoDelMomento($telefono, $pedido, EstadoPedido::ENTREGADO);
        $this->assertSame([], $this->mensajesAlToken($telefonoAjeno->token_fcm));
    }

    #[TestDox('avisos-E09 a avisos-E12: el aviso llega a cada teléfono de la cuenta dueña, en Android y en iPhone, y a ninguno de otra cuenta, ni de la cuenta de la lavandería')]
    public function test_avisos_e09_a_e12_el_aviso_llega_solo_a_los_telefonos_de_la_cuenta_duena(): void
    {
        $android = $this->telefonoDe($this->cuenta, Dispositivo::ANDROID);
        $iphone = $this->telefonoDe($this->cuenta, Dispositivo::IOS);
        $otraCuenta = $this->cuentaDeUsuario();
        $ajenos = [$this->telefonoDe($otraCuenta)];
        $pedidoAjeno = $this->pedidoAceptadoDe($otraCuenta);
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $ajenos[] = $this->telefonoDe($this->cuentaDeLaLavanderia($pedido));

        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO);

        $this->assertAvisoDelMomento($android, $pedido, EstadoPedido::RECOLECTADO);
        $this->assertAvisoDelMomento($iphone, $pedido, EstadoPedido::RECOLECTADO);

        foreach ($ajenos as $ajeno) {
            $this->assertSame([], $this->mensajesDelPedido($ajeno, $pedido));
        }

        // El pedido de la otra cuenta avisa a su teléfono, no a los de esta.
        $this->cambiarComoLaLavanderia($pedidoAjeno, EstadoPedido::RECOLECTADO);
        $this->assertAvisoDelMomento($ajenos[0], $pedidoAjeno, EstadoPedido::RECOLECTADO);
        $this->assertSame([], $this->mensajesDelPedido($android, $pedidoAjeno));
        $this->assertSame([], $this->mensajesDelPedido($iphone, $pedidoAjeno));
    }

    #[TestDox('avisos-E09 a avisos-E12: un pedido de una cuenta sin teléfono registrado no manda nada')]
    public function test_avisos_e09_a_e12_sin_telefono_registrado_no_se_manda_nada(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);

        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO);

        $this->assertSame([], $this->mensajesAFcm());
    }

    #[TestDox('avisos-E09 a avisos-E12: los momentos viven en una sola lista, con los cuatro del contrato en su orden')]
    public function test_avisos_e09_a_e12_los_momentos_viven_en_una_sola_lista(): void
    {
        $this->assertSame(
            [EstadoPedido::CONFIRMADO, EstadoPedido::RECOLECTADO, EstadoPedido::LISTO, EstadoPedido::ENTREGADO],
            array_keys(config('avisos.momentos')),
        );
    }

    #[TestDox('avisos-E09 a avisos-E12: el paso a En proceso no es un momento y no lleva aviso visible')]
    public function test_avisos_e09_a_e12_el_paso_a_en_proceso_no_lleva_aviso_visible(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO);
        $telefono = $this->telefonoDe($this->cuenta);

        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::EN_PROCESO);

        foreach ($this->mensajesAlToken($telefono->token_fcm) as $mensaje) {
            $this->assertArrayNotHasKey('notification', $mensaje['android'] ?? []);
            $this->assertArrayNotHasKey('alert', $mensaje['apns']['payload']['aps'] ?? []);
        }
    }

    #[TestDox('avisos-E09 a avisos-E12: un momento que se agrega a la lista avisa sin tocar los otros cuatro')]
    public function test_avisos_e09_a_e12_un_momento_que_se_agrega_a_la_lista_avisa(): void
    {
        config(['avisos.momentos.en_proceso' => ['titulo' => 'Momento de prueba', 'texto' => 'Texto de prueba :folio']]);
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO);
        $telefono = $this->telefonoDe($this->cuenta);

        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::EN_PROCESO);

        $this->assertAvisoDelMomento($telefono, $pedido, EstadoPedido::EN_PROCESO);
    }

    /**
     * Un pedido de la cuenta, creado al aceptar el resumen, que espera su pago.
     */
    private function pedidoPorPagarDe(Usuario $cuenta): Pedido
    {
        $id = $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta)))
            ->assertCreated()
            ->json('pedido.id_publico');

        return Pedido::query()->where('id_publico', $id)->sole();
    }

    /**
     * Al teléfono llegó un solo aviso de ese pedido en ese estado, con el título y el texto del momento en Android y en
     * iPhone.
     */
    private function assertAvisoDelMomento(Dispositivo $telefono, Pedido $pedido, string $estado): void
    {
        $avisos = array_values(array_filter(
            $this->mensajesDelPedido($telefono, $pedido),
            fn (array $mensaje): bool => $mensaje['data']['estado'] === $estado,
        ));

        $this->assertCount(1, $avisos, "Un aviso del momento {$estado} al teléfono.");

        $titulo = config("avisos.momentos.{$estado}.titulo");
        $texto = str_replace(':folio', (string) $pedido->fresh()->folio, config("avisos.momentos.{$estado}.texto"));

        $this->assertSame(['title' => $titulo, 'body' => $texto], $avisos[0]['android']['notification']);
        $this->assertSame(['title' => $titulo, 'body' => $texto], $avisos[0]['apns']['payload']['aps']['alert']);
    }

    /**
     * Los mensajes de ese pedido que llegaron al teléfono.
     *
     * @return list<array<string, mixed>>
     */
    private function mensajesDelPedido(Dispositivo $telefono, Pedido $pedido): array
    {
        return array_values(array_filter(
            $this->mensajesAlToken($telefono->token_fcm),
            fn (array $mensaje): bool => $mensaje['data']['pedido'] === $pedido->id_publico,
        ));
    }
}
