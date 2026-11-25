<?php

namespace Tests\Feature\Avisos;

use App\Avisos\Fcm\CanalDeFcm;
use App\Avisos\Fcm\FcmNoDisponible;
use App\Avisos\MandarAvisoAlTelefono;
use App\Avisos\MensajeDelPedido;
use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
 * El canal de Firebase Cloud Messaging: la API manda cada aviso por FCM directo, el servicio gratuito de Google que
 * nombra el contrato (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3; ADR-0008), con la API HTTP v1 y la cuenta de
 * servicio del proyecto de Firebase del entorno (ADR-0012; openspec/changes/avisos/design.md).
 *
 * FCM está simulado (FcmSimulado): ninguna prueba sale a la red ni necesita llaves de Firebase (DD-072).
 *
 * Escenarios: avisos-E01, avisos-E02 y avisos-E16 · Spec: openspec/changes/avisos/specs/avisos/spec.md · Tarea 1.1
 */
class CanalDeAvisosTest extends TestCase
{
    use AgendaDePrueba;
    use CambiosDeEstadoDePrueba;
    use EstadosDePrueba;
    use FcmSimulado;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Los cuatro momentos que nombra el contrato (Módulo A #17), por el estado que los dispara. */
    private const LOS_CUATRO_MOMENTOS = [
        EstadoPedido::CONFIRMADO,
        EstadoPedido::RECOLECTADO,
        EstadoPedido::LISTO,
        EstadoPedido::ENTREGADO,
    ];

    private Usuario $cuenta;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->cuenta = $this->cuentaDeUsuario();
        // Aceptado por su lavandería, para que sus botones lo muevan.
        $this->pedido = $this->pedidoAceptadoDe($this->cuenta);
    }

    #[TestDox('avisos-E01: un aviso sale por Firebase Cloud Messaging, dirigido al token de ese teléfono, con el servicio simulado')]
    public function test_avisos_e01_un_aviso_sale_por_fcm_dirigido_al_token_del_telefono(): void
    {
        $this->simularFcm();
        $telefono = $this->telefonoDe($this->cuenta);

        app(CanalDeFcm::class)->mandar(
            $telefono->token_fcm,
            MensajeDelPedido::armar($this->pedido, EstadoPedido::RECOLECTADO),
        );

        // Un envío a la API HTTP v1 de FCM, al proyecto de la cuenta de servicio, con el token de acceso de OAuth.
        Http::assertSent(fn ($peticion): bool => $peticion->url() === self::DIRECCION_DE_ENVIO
            && $peticion->method() === 'POST'
            && $peticion->hasHeader('Authorization', 'Bearer '.self::TOKEN_DE_ACCESO));
        $this->assertCount(1, $this->mensajesAFcm());
        $this->assertSame($telefono->token_fcm, $this->mensajesAFcm()[0]['token']);
    }

    #[TestDox('avisos-E01: el token de acceso sale de la cuenta de servicio, firmado con su llave, para el alcance de FCM')]
    public function test_avisos_e01_el_token_de_acceso_sale_de_la_cuenta_de_servicio(): void
    {
        $this->simularFcm();
        $telefono = $this->telefonoDe($this->cuenta);

        app(CanalDeFcm::class)->mandar($telefono->token_fcm, MensajeDelPedido::armar($this->pedido, EstadoPedido::RECOLECTADO));

        [$peticion] = $this->peticionesDeTokenDeAcceso();
        $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $peticion['grant_type']);

        // El reloj de la prueba está fijo en el jueves de prueba (PedidosDePrueba): la aserción se revisa con él.
        JWT::$timestamp = now()->getTimestamp();

        try {
            $reclamos = JWT::decode($peticion['assertion'], new Key($this->llavePublicaDePrueba(), 'RS256'));
        } finally {
            JWT::$timestamp = null;
        }

        $this->assertSame(self::CORREO_DE_LA_CUENTA_DE_SERVICIO, $reclamos->iss);
        $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', $reclamos->scope);
        $this->assertSame(self::DIRECCION_DE_TOKENS, $reclamos->aud);
        $this->assertGreaterThan($reclamos->iat, $reclamos->exp);
    }

    #[TestDox('avisos-E01: el token de acceso se pide una vez y sirve para los envíos siguientes')]
    public function test_avisos_e01_el_token_de_acceso_se_pide_una_vez(): void
    {
        $this->simularFcm();
        $mensaje = MensajeDelPedido::armar($this->pedido, EstadoPedido::RECOLECTADO);

        app(CanalDeFcm::class)->mandar($this->telefonoDe($this->cuenta)->token_fcm, $mensaje);
        app(CanalDeFcm::class)->mandar($this->telefonoDe($this->cuenta)->token_fcm, $mensaje);

        $this->assertCount(1, $this->peticionesDeTokenDeAcceso());
        $this->assertCount(2, $this->mensajesAFcm());
    }

    #[TestDox('avisos-E01: sin la cuenta de servicio configurada no sale nada a la red y el canal lo dice')]
    public function test_avisos_e01_sin_la_cuenta_de_servicio_no_sale_nada(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        config(['avisos.fcm.credenciales' => null]);

        try {
            app(CanalDeFcm::class)->mandar('token-de-prueba', MensajeDelPedido::armar($this->pedido, EstadoPedido::RECOLECTADO));
            $this->fail('Sin cuenta de servicio, el canal debió decir que FCM no está disponible.');
        } catch (FcmNoDisponible $falla) {
            $this->assertFalse($falla->reintentable);
        }

        Http::assertNothingSent();
    }

    #[TestDox('avisos-E02: con FCM sin responder, la lavandería marca Recolectado, el cambio se guarda igual y el fallo queda en la bitácora')]
    public function test_avisos_e02_con_fcm_sin_responder_el_cambio_se_guarda_y_el_fallo_queda_en_la_bitacora(): void
    {
        $this->simularFcmSinResponder();
        $this->telefonoDe($this->cuenta);
        Log::spy();

        $evento = $this->cambiarComoLaLavanderia($this->pedido, EstadoPedido::RECOLECTADO);

        $this->assertSame(EstadoPedido::RECOLECTADO, $this->pedido->fresh()->estado->codigo);
        $this->assertTrue($evento->exists);
        $this->assertSame(
            [EstadoPedido::POR_PAGAR, EstadoPedido::CONFIRMADO, EstadoPedido::ACEPTADO, EstadoPedido::RECOLECTADO],
            $this->pedido->eventos()->with('estado')->orderBy('id_pedido_eventos')->get()->pluck('estado.codigo')->all(),
        );
        $this->assertFalloEnLaBitacora(EstadoPedido::RECOLECTADO, 'no se pudo llegar a FCM');
    }

    #[TestDox('avisos-E02: si FCM responde con un error del servicio, el cambio se guarda igual y el fallo queda en la bitácora')]
    public function test_avisos_e02_si_fcm_responde_con_un_error_el_cambio_se_guarda(): void
    {
        $this->simularFcm(Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503));
        $this->telefonoDe($this->cuenta);
        Log::spy();

        $this->cambiarComoLaLavanderia($this->pedido, EstadoPedido::RECOLECTADO);

        $this->assertSame(EstadoPedido::RECOLECTADO, $this->pedido->fresh()->estado->codigo);
        $this->assertFalloEnLaBitacora(EstadoPedido::RECOLECTADO, 'FCM respondió 503 UNAVAILABLE');
    }

    #[TestDox('avisos-E02: sin la cuenta de servicio de Firebase (DD-072), el cambio se guarda, nada sale a la red y el fallo queda en la bitácora')]
    public function test_avisos_e02_sin_la_cuenta_de_servicio_el_cambio_se_guarda(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        config(['avisos.fcm.credenciales' => null]);
        $this->telefonoDe($this->cuenta);
        Log::spy();

        $this->cambiarComoLaLavanderia($this->pedido, EstadoPedido::RECOLECTADO);

        $this->assertSame(EstadoPedido::RECOLECTADO, $this->pedido->fresh()->estado->codigo);
        $this->assertFalloEnLaBitacora(EstadoPedido::RECOLECTADO, 'no hay cuenta de servicio configurada');
        Http::assertNothingSent();
    }

    #[TestDox('avisos-E02: con FCM sin responder, la confirmación por el pago se guarda igual y el fallo queda en la bitácora')]
    public function test_avisos_e02_con_fcm_sin_responder_la_confirmacion_se_guarda(): void
    {
        $this->simularFcmSinResponder();
        $otraCuenta = $this->cuentaDeUsuario();
        $this->telefonoDe($otraCuenta);
        $id = $this->aceptarResumen($otraCuenta, $this->resumen($this->direccionDe($otraCuenta)))->json('pedido.id_publico');
        Log::spy();

        $pedido = app(CobroSimulado::class)->pagar(Pedido::query()->where('id_publico', $id)->sole())->fresh();

        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->estado->codigo);
        $this->assertNotNull($pedido->folio);
        $this->assertFalloEnLaBitacora(EstadoPedido::CONFIRMADO, 'no se pudo llegar a FCM');
    }

    #[TestDox('avisos-E02: un envío que falló porque FCM no respondió vuelve a la cola con su espera; uno sin remedio, no')]
    public function test_avisos_e02_el_envio_que_fallo_vuelve_a_la_cola_con_su_espera(): void
    {
        $telefono = $this->telefonoDe($this->cuenta);
        $esperas = config('avisos.esperas');

        $this->simularFcmSinResponder();
        $envio = (new MandarAvisoAlTelefono($this->pedido, EstadoPedido::RECOLECTADO, $telefono->getKey()))
            ->withFakeQueueInteractions();
        $envio->job->attempts = 2;
        app()->call([$envio, 'handle']);
        $envio->assertReleased(delay: $esperas[1]);
        $envio->assertNotFailed();

        // Cinco intentos en total: los que da la lista de esperas, más el primero.
        $this->assertSame(count($esperas) + 1, $envio->tries());

        config(['avisos.fcm.credenciales' => null]);
        $sinRemedio = (new MandarAvisoAlTelefono($this->pedido, EstadoPedido::RECOLECTADO, $telefono->getKey()))
            ->withFakeQueueInteractions();
        app()->call([$sinRemedio, 'handle']);
        $sinRemedio->assertNotReleased();
        $sinRemedio->assertNotFailed();
    }

    #[TestDox('avisos-E16: el aviso de cada uno de los cuatro momentos trae la parte de Android y la de iPhone, cada una con su título y su texto')]
    public function test_avisos_e16_el_aviso_de_cada_momento_trae_la_parte_de_android_y_la_de_iphone(): void
    {
        foreach (self::LOS_CUATRO_MOMENTOS as $estado) {
            $mensaje = MensajeDelPedido::armar($this->pedido, $estado);
            $titulo = config("avisos.momentos.{$estado}.titulo");
            $texto = str_replace(':folio', $this->pedido->folio, config("avisos.momentos.{$estado}.texto"));

            $this->assertNotEmpty($titulo, $estado);
            $this->assertNotEmpty($texto, $estado);
            $this->assertSame(
                ['title' => $titulo, 'body' => $texto],
                $mensaje['android']['notification'],
                "La parte de Android del momento {$estado}.",
            );
            $this->assertSame(
                ['title' => $titulo, 'body' => $texto],
                $mensaje['apns']['payload']['aps']['alert'],
                "La parte de iPhone del momento {$estado}.",
            );
            $this->assertSame('alert', $mensaje['apns']['headers']['apns-push-type'], $estado);
        }
    }

    #[TestDox('avisos-E16: el texto sale de la configuración, con el folio del pedido, y no del código')]
    public function test_avisos_e16_el_texto_sale_de_la_configuracion_con_el_folio(): void
    {
        config([
            'avisos.momentos.recolectado.titulo' => 'Título de prueba',
            'avisos.momentos.recolectado.texto' => 'Texto de prueba del pedido :folio',
        ]);

        $mensaje = MensajeDelPedido::armar($this->pedido, EstadoPedido::RECOLECTADO);

        $this->assertSame('Título de prueba', $mensaje['android']['notification']['title']);
        $this->assertSame("Texto de prueba del pedido {$this->pedido->folio}", $mensaje['android']['notification']['body']);
        $this->assertSame('Título de prueba', $mensaje['apns']['payload']['aps']['alert']['title']);
    }

    #[TestDox('avisos-E16: el mensaje lleva el pedido por su identificador público y el estado, como texto, sin la llave interna')]
    public function test_avisos_e16_el_mensaje_lleva_el_pedido_y_el_estado(): void
    {
        $mensaje = MensajeDelPedido::armar($this->pedido, EstadoPedido::RECOLECTADO);

        $this->assertSame([
            'tipo' => 'cambio_de_estado',
            'pedido' => $this->pedido->id_publico,
            'estado' => EstadoPedido::RECOLECTADO,
        ], $mensaje['data']);
    }

    /**
     * La bitácora del servidor recibió una advertencia del aviso que no salió, con el pedido, el estado y el motivo, y
     * sin el token del teléfono.
     */
    private function assertFalloEnLaBitacora(string $estado, string $motivo): void
    {
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $mensaje, array $contexto): bool => str_contains($mensaje, 'aviso')
                && ($contexto['estado'] ?? null) === $estado
                && is_string($contexto['pedido'] ?? null)
                && str_contains((string) ($contexto['motivo'] ?? ''), $motivo)
                && ! str_contains(json_encode($contexto, JSON_THROW_ON_ERROR), 'token-fcm-'))
            ->once();
    }
}
