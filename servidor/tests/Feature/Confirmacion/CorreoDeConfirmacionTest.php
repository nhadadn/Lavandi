<?php

namespace Tests\Feature\Confirmacion;

use App\Avisos\CorreoDeConfirmacion;
use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Al confirmarse el pedido, el servidor pone en cola un correo a la dirección de la cuenta con el folio y el resumen
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #11; :30:ANEXO B, Sección B.5). El correo cuelga del evento
 * de pedido confirmado, que se publica ya guardada la confirmación: si el envío falla, el pedido sigue confirmado y el
 * correo queda en la cola para reintentarse (design.md, decisión 7).
 *
 * En local y en las pruebas el correo se captura sin enviarse: el transporte de log en local y el de arreglo en las
 * pruebas (ADR-0014). El proveedor fuera de local es DD-071, y el dominio del remitente, DD-023. El nombre del producto
 * sale de la configuración (APP_NAME), nunca de la plantilla.
 *
 * Escenarios: confirmacion-E10 y confirmacion-E11 · Spec:
 * openspec/changes/resumen-y-confirmacion/specs/confirmacion/spec.md · Tarea 1.5
 */
class CorreoDeConfirmacionTest extends TestCase
{
    use AgendaDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->cuenta = $this->cuentaDeUsuario();
    }

    #[TestDox('confirmacion-E10: al confirmarse el pedido se pone en cola un correo a la dirección de la cuenta')]
    public function test_confirmacion_e10_al_confirmarse_el_pedido_se_pone_en_cola_un_correo_a_la_cuenta(): void
    {
        Mail::fake();
        $pedido = $this->pedidoPorPagar();

        // Aceptar el resumen no manda nada: el correo es de la confirmación.
        Mail::assertNothingOutgoing();

        app(CobroSimulado::class)->pagar($pedido);

        Mail::assertQueued(CorreoDeConfirmacion::class, 1);
        Mail::assertQueued(
            CorreoDeConfirmacion::class,
            fn (CorreoDeConfirmacion $correo): bool => $correo->hasTo($this->cuenta->correo) && $correo->pedido->is($pedido),
        );
        Mail::assertNothingSent();
    }

    #[TestDox('confirmacion-E10: el correo lleva el folio, el servicio, la fecha con su ventana y las direcciones')]
    public function test_confirmacion_e10_el_correo_lleva_el_folio_el_servicio_la_fecha_con_su_ventana_y_las_direcciones(): void
    {
        $casa = $this->direccionDe($this->cuenta, ['alias' => 'Casa']);
        $trabajo = $this->direccionDe($this->cuenta, [
            'alias' => 'Trabajo',
            'calle' => 'Avenida de prueba',
            'numero_exterior' => '9',
            'numero_interior' => null,
            'colonia' => 'Colonia del trabajo',
            'ubicacion' => ['latitud' => 10.25, 'longitud' => -119.75],
        ]);
        $pedido = app(CobroSimulado::class)->pagar($this->pedidoPorPagar($this->resumen($casa, $trabajo)));
        $correo = new CorreoDeConfirmacion($pedido->fresh());

        foreach ([
            $pedido->folio,
            $this->lavanderia->nombre,
            'Lavado estándar',
            'jueves 3 de diciembre de 2026',
            '11:00 a 12:00',
            'Casa: Calle de prueba 123, int. 4, Colonia de prueba, C.P. 00000, Municipio de prueba',
            'Trabajo: Avenida de prueba 9, Colonia del trabajo, C.P. 00000, Municipio de prueba',
        ] as $dato) {
            $correo->assertSeeInHtml($dato, false);
            $correo->assertSeeInText($dato, false);
        }

        $correo->assertHasSubject(config('app.name').": tu pedido {$pedido->folio} está confirmado");
    }

    #[TestDox('confirmacion-E10: el nombre del producto sale de la configuración, nunca escrito en la plantilla')]
    public function test_confirmacion_e10_el_nombre_del_producto_sale_de_la_configuracion(): void
    {
        $pedido = app(CobroSimulado::class)->pagar($this->pedidoPorPagar())->fresh();

        foreach (['Producto de prueba A', 'Producto de prueba B'] as $producto) {
            config(['app.name' => $producto]);
            $correo = new CorreoDeConfirmacion($pedido);

            $correo->assertSeeInHtml($producto, false);
            $correo->assertSeeInText($producto, false);
            $correo->assertHasSubject("{$producto}: tu pedido {$pedido->folio} está confirmado");
        }

        $correo->assertDontSeeInHtml('Producto de prueba A', false);

        // Las plantillas no traen ningún nombre de producto: lo reciben.
        foreach (['confirmacion', 'confirmacion-texto'] as $plantilla) {
            $this->assertStringContainsString('$producto', (string) file_get_contents(resource_path("views/correo/{$plantilla}.blade.php")));
        }
    }

    #[TestDox('confirmacion-E10: en las pruebas el correo se captura sin enviarse, con el transporte de arreglo, a la dirección de la cuenta')]
    public function test_confirmacion_e10_el_correo_se_captura_sin_enviarse(): void
    {
        // La cola síncrona de las pruebas lo procesa al momento; el transporte de arreglo lo guarda y no lo manda.
        $this->assertSame('array', config('mail.default'));
        $pedido = app(CobroSimulado::class)->pagar($this->pedidoPorPagar());

        $capturados = $this->correosCapturados();
        $this->assertCount(1, $capturados);
        $this->assertSame([$this->cuenta->correo], array_map(fn ($direccion) => $direccion->getAddress(), $capturados[0]->getTo()));
        $this->assertStringContainsString($pedido->folio, (string) $capturados[0]->getTextBody());
    }

    #[TestDox('confirmacion-E10: si la confirmación no se guarda, no sale ningún correo')]
    public function test_confirmacion_e10_si_la_confirmacion_no_se_guarda_no_sale_ningun_correo(): void
    {
        Mail::fake();
        $pedido = $this->pedidoPorPagar();
        Event::listen('eloquent.creating: '.PedidoEvento::class, fn () => throw new RuntimeException('Falla de prueba.'));

        try {
            app(CobroSimulado::class)->pagar($pedido);
            $this->fail('El evento debió fallar.');
        } catch (RuntimeException) {
            // La confirmación se deshizo.
        }

        $this->assertNull($pedido->fresh()->folio);
        Mail::assertNothingOutgoing();
    }

    #[TestDox('confirmacion-E11: si el servicio de correo falla al enviar, el pedido queda Confirmado, el usuario ve su folio y el correo queda para reintentarse')]
    public function test_confirmacion_e11_si_el_correo_falla_el_pedido_sigue_confirmado_y_el_correo_se_reintenta(): void
    {
        // Una cola de verdad, en la base, y un servicio de correo que falla al enviar. El error del correo no se
        // escribe en el registro de la prueba.
        config(['queue.default' => 'database', 'logging.default' => 'null']);
        $servicio = $this->servicioDeCorreoQueFalla();

        $pedido = app(CobroSimulado::class)->pagar($this->pedidoPorPagar());

        // El correo espera en la cola; el pedido ya está confirmado.
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertConfirmadoConSuFolio($pedido);

        // El trabajador intenta mandarlo y el servicio falla: el pedido sigue confirmado y el correo vuelve a la cola.
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('jobs')->value('attempts'));
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertConfirmadoConSuFolio($pedido);

        $this->assertSame([], $servicio->enviados);

        // El servicio vuelve; pasado su tiempo de espera, el reintento lo manda.
        $servicio->falla = false;
        $this->travel(2)->minutes();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertCount(1, $servicio->enviados);
        $correo = $servicio->enviados[0]->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $correo);
        $this->assertSame([$this->cuenta->correo], array_map(fn ($direccion) => $direccion->getAddress(), $correo->getTo()));
        $this->assertStringContainsString($pedido->fresh()->folio, (string) $correo->getTextBody());
    }

    /**
     * Un pedido en el estado previo al pago, creado como lo crea la app al aceptar el resumen.
     *
     * @param  array<string, mixed>|null  $resumen
     */
    private function pedidoPorPagar(?array $resumen = null): Pedido
    {
        $resumen ??= $this->resumen($this->direccionDe($this->cuenta, ['alias' => 'Casa']));

        $id = $this->aceptarResumen($this->cuenta, $resumen)->assertCreated()->json('pedido.id_publico');

        return Pedido::query()->where('id_publico', $id)->sole();
    }

    /**
     * El pedido está Confirmado con su folio, y la cuenta lo ve así en la API.
     */
    private function assertConfirmadoConSuFolio(Pedido $pedido): void
    {
        $folio = $pedido->fresh()->folio;
        $this->assertNotNull($folio);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->cuenta->createToken('app')->plainTextToken)
            ->getJson("/api/pedidos/{$pedido->id_publico}")
            ->assertOk()
            ->assertJsonPath('pedido.folio', $folio)
            ->assertJsonPath('pedido.estado.codigo', 'confirmado');
    }

    /**
     * Un servicio de correo que falla al enviar, como el proveedor caído o que rechaza el envío, mientras $falla sea
     * true; después guarda lo que envía en $enviados, sin mandarlo. Queda como el transporte de omisión.
     */
    private function servicioDeCorreoQueFalla(): object
    {
        $servicio = new class extends AbstractTransport
        {
            public bool $falla = true;

            /** @var list<SentMessage> */
            public array $enviados = [];

            protected function doSend(SentMessage $message): void
            {
                if ($this->falla) {
                    throw new TransportException('El servicio de correo de prueba no responde.');
                }

                $this->enviados[] = $message;
            }

            public function __toString(): string
            {
                return 'que_falla://';
            }
        };

        Mail::extend('que_falla', fn () => $servicio);
        config([
            'mail.mailers.que_falla' => ['transport' => 'que_falla'],
            'mail.default' => 'que_falla',
        ]);

        return $servicio;
    }

    /**
     * Los correos que guardó el transporte de arreglo.
     *
     * @return list<Email>
     */
    private function correosCapturados(): array
    {
        $transporte = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transporte);

        return $transporte->messages()
            ->map(fn (SentMessage $mensaje): Email => $mensaje->getOriginalMessage())
            ->values()
            ->all();
    }
}
