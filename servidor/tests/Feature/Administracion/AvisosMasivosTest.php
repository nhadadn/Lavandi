<?php

namespace Tests\Feature\Administracion;

use App\Autorizacion\Autorizacion;
use App\Avisos\MandarAvisoMasivo;
use App\Avisos\MandarAvisoMasivoAlTelefono;
use App\Filament\Resources\AvisosMasivos\Pages\CreateAvisoMasivo;
use App\Filament\Resources\AvisosMasivos\Pages\ListAvisosMasivos;
use App\Models\AvisoMasivo;
use App\Models\Dispositivo;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Avisos\Soporte\FcmSimulado;
use Tests\TestCase;

/**
 * Los avisos masivos del panel: la administración escribe un aviso con título y texto y lo manda al celular de todas las
 * cuentas de la app, por el servicio gratuito de Google, FCM (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7;
 * ADR-0008 y su adenda, FCM HTTP v1 directo).
 *
 * - El aviso se guarda en avisos_masivos, sin plaza y sin segmento: es global (design.md, "Tablas y migración").
 * - El envío va en cola: el panel no espera a Google. Un trabajo cuenta los teléfonos y pone un envío en cola por
 *   cada uno, con los reintentos de los avisos del pedido (design.md, "Risks / Trade-offs").
 * - Solo salen envíos a los teléfonos registrados en dispositivos: una cuenta sin teléfono no genera ninguno. Qué
 *   teléfonos reciben el aviso lo decide el componente único de autorización (ADR-0019).
 *
 * FCM está simulado (FcmSimulado): ninguna prueba sale a la red ni usa llaves de Firebase. El aviso a un segmento
 * espera DD-113 (administracion-E24).
 *
 * Escenario: administracion-E23 · Spec: openspec/changes/administracion/specs/administracion/spec.md · Tarea 5.1
 */
class AvisosMasivosTest extends TestCase
{
    use FcmSimulado;
    use PanelDePrueba;
    use RefreshDatabase;

    /** Los roles que no son de administración. */
    private const OTROS_ROLES = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR];

    private const TITULO = 'Aviso de prueba';

    private const MENSAJE = 'Texto de prueba del aviso, con acentos y eñe.';

    #[TestDox('administracion-E23: con tres cuentas con un teléfono registrado y una sin teléfono, el aviso global sale por FCM a los tres teléfonos y la cuenta sin teléfono no genera ningún envío')]
    public function test_administracion_e23_el_aviso_global_sale_a_los_tres_telefonos_y_no_a_la_cuenta_sin_telefono(): void
    {
        $this->simularFcm();
        $telefonos = $this->tresCuentasConTelefono();
        Usuario::factory()->conRol(Rol::USUARIO)->create();
        $administracion = $this->comoAdministracion();

        $this->mandarAviso();

        $mensajes = $this->mensajesAFcm();
        $this->assertCount(3, $mensajes, 'Un envío por teléfono, ninguno por la cuenta sin teléfono.');
        $this->assertEqualsCanonicalizing(
            array_map(fn (Dispositivo $telefono): string => $telefono->token_fcm, $telefonos),
            array_column($mensajes, 'token'),
        );

        foreach ($mensajes as $mensaje) {
            $this->assertSame(['tipo' => 'aviso_masivo'], $mensaje['data']);
            $this->assertSame(['title' => self::TITULO, 'body' => self::MENSAJE], $mensaje['android']['notification']);
            $this->assertSame(['title' => self::TITULO, 'body' => self::MENSAJE], $mensaje['apns']['payload']['aps']['alert']);
            $this->assertSame('alert', $mensaje['apns']['headers']['apns-push-type']);
        }

        $aviso = AvisoMasivo::sole();
        $this->assertSame(
            [self::TITULO, self::MENSAJE, $administracion->getKey(), null, null, 3],
            [$aviso->titulo, $aviso->mensaje, $aviso->id_usuarios, $aviso->id_plazas, $aviso->segmento, $aviso->destinatarios],
        );
        $this->assertNotNull($aviso->enviado_en);
    }

    #[TestDox('administracion-E23: el aviso sale en cola: el panel no espera a FCM, y el trabajo pone en cola un envío por teléfono y guarda cuántos son')]
    public function test_administracion_e23_el_aviso_sale_en_cola_con_un_envio_por_telefono(): void
    {
        $this->simularFcm();
        Queue::fake();
        $telefonos = $this->tresCuentasConTelefono();
        Usuario::factory()->conRol(Rol::USUARIO)->create();
        $this->comoAdministracion();

        $this->mandarAviso();

        // El panel solo guardó el aviso y lo puso en cola: nada salió a Google todavía.
        Http::assertNothingSent();
        $aviso = AvisoMasivo::sole();
        $this->assertNull($aviso->destinatarios, 'Se cuenta al salir, no al guardar.');
        Queue::assertPushed(MandarAvisoMasivo::class, 1);
        Queue::assertPushed(MandarAvisoMasivo::class, fn (MandarAvisoMasivo $trabajo): bool => $trabajo->aviso->is($aviso));

        // El trabajo, al correr, pone en cola un envío por cada teléfono y guarda a cuántos se mandó.
        app()->call([new MandarAvisoMasivo($aviso), 'handle']);

        Queue::assertPushed(MandarAvisoMasivoAlTelefono::class, 3);
        $this->assertEqualsCanonicalizing(
            array_map(fn (Dispositivo $telefono): int => $telefono->getKey(), $telefonos),
            Queue::pushed(MandarAvisoMasivoAlTelefono::class)->map(fn (MandarAvisoMasivoAlTelefono $envio): int => $envio->telefono)->all(),
        );
        $this->assertSame(3, $aviso->refresh()->destinatarios);
    }

    #[TestDox('administracion-E23: una cuenta bloqueada no recibe el aviso global: no puede usar la aplicación')]
    public function test_administracion_e23_una_cuenta_bloqueada_no_recibe_el_aviso(): void
    {
        $this->simularFcm();
        $activa = $this->telefonoDe(Usuario::factory()->conRol(Rol::USUARIO)->create());
        $bloqueada = $this->telefonoDe(Usuario::factory()->conRol(Rol::USUARIO)->create(['estado' => Usuario::BLOQUEADO]));
        $this->comoAdministracion();

        $this->mandarAviso();

        $this->assertSame([$activa->token_fcm], array_column($this->mensajesAFcm(), 'token'));
        $this->assertSame([], $this->mensajesAlToken($bloqueada->token_fcm));
        $this->assertSame(1, AvisoMasivo::sole()->destinatarios);
    }

    #[TestDox('administracion-E23: si FCM dice que un token ya no sirve, ese teléfono se borra y los demás reciben el aviso')]
    public function test_administracion_e23_un_token_rechazado_se_borra_y_los_demas_reciben_el_aviso(): void
    {
        [$uno, $otro, $rechazado] = $this->tresCuentasConTelefono();
        $this->simularFcm(fn (Request $peticion) => $peticion->data()['message']['token'] === $rechazado->token_fcm
            ? Http::response(['error' => [
                'code' => 404,
                'status' => 'NOT_FOUND',
                'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']],
            ]], 404)
            : Http::response(['name' => 'projects/proyecto-de-prueba/messages/0:1']));
        $this->comoAdministracion();

        $this->mandarAviso();

        $this->assertCount(1, $this->mensajesAlToken($uno->token_fcm));
        $this->assertCount(1, $this->mensajesAlToken($otro->token_fcm));
        $this->assertModelMissing($rechazado);
        $this->assertModelExists($uno);
    }

    #[TestDox('administracion-E23: el panel no manda un aviso sin título o sin texto')]
    public function test_administracion_e23_el_panel_no_manda_un_aviso_incompleto(): void
    {
        Queue::fake();
        $this->comoAdministracion();

        Livewire::test(CreateAvisoMasivo::class)
            ->fillForm(['titulo' => '', 'mensaje' => '   '])
            ->call('create')
            ->assertHasFormErrors(['titulo' => 'required', 'mensaje' => 'required']);

        $this->assertSame(0, AvisoMasivo::count());
        Queue::assertNothingPushed();
    }

    #[TestDox('administracion-E23: la lista de avisos muestra los enviados con su título y a cuántos teléfonos salieron, y es de solo lectura')]
    public function test_administracion_e23_la_lista_muestra_los_avisos_enviados_y_es_de_solo_lectura(): void
    {
        $this->simularFcm();
        $this->tresCuentasConTelefono();
        $this->comoAdministracion();
        $this->get('/admin/avisos-masivos/create')->assertOk();

        $this->mandarAviso();
        $aviso = AvisoMasivo::sole();

        $this->get('/admin/avisos-masivos')->assertOk()->assertSee(self::TITULO);
        Livewire::test(ListAvisosMasivos::class)
            ->assertCanSeeTableRecords([$aviso])
            ->assertTableColumnStateSet('titulo', self::TITULO, $aviso)
            ->assertTableColumnStateSet('destinatarios', 3, $aviso)
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete');
    }

    #[TestDox('administracion-E23: una cuenta que no es de administración no abre los avisos masivos ni manda uno, y el componente no le da ninguno')]
    public function test_administracion_e23_solo_la_administracion_manda_avisos_masivos(): void
    {
        Queue::fake();
        Filament::setCurrentPanel('admin');
        $autorizacion = app(Autorizacion::class);
        $aviso = AvisoMasivo::create([
            'titulo' => self::TITULO,
            'mensaje' => self::MENSAJE,
            'id_usuarios' => Usuario::factory()->conRol(Rol::ADMINISTRACION)->create()->getKey(),
            'enviado_en' => now(),
        ]);

        foreach (self::OTROS_ROLES as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();
            $this->actingAs($cuenta, 'web');

            $this->assertFalse($autorizacion->puedeMandarAvisosMasivos($cuenta), $rol);
            $this->assertSame(0, $autorizacion->avisosMasivosDelPanel($cuenta)->count(), $rol);
            $this->get('/admin/avisos-masivos')->assertForbidden();
            $this->get('/admin/avisos-masivos/create')->assertForbidden();
            Livewire::test(CreateAvisoMasivo::class)->assertForbidden();
            Livewire::test(ListAvisosMasivos::class)->assertForbidden();
        }

        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create();
        $this->assertTrue($autorizacion->puedeMandarAvisosMasivos($administracion));
        $this->assertSame([$aviso->getKey()], $autorizacion->avisosMasivosDelPanel($administracion)->pluck('id_avisos_masivos')->all());

        $bloqueada = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['estado' => Usuario::BLOQUEADO]);
        $this->assertFalse($autorizacion->puedeMandarAvisosMasivos($bloqueada), 'Una cuenta bloqueada no manda avisos.');
        $this->assertSame(1, AvisoMasivo::count());
        Queue::assertNothingPushed();
    }

    #[TestDox('administracion-E23: un aviso con segmento o con plaza no sale a ningún teléfono mientras DD-113 no defina los segmentos')]
    public function test_administracion_e23_un_aviso_con_segmento_no_sale_mientras_no_haya_segmentos(): void
    {
        $this->tresCuentasConTelefono();
        $autorizacion = app(Autorizacion::class);
        $datos = [
            'titulo' => self::TITULO,
            'mensaje' => self::MENSAJE,
            'id_usuarios' => Usuario::factory()->conRol(Rol::ADMINISTRACION)->create()->getKey(),
            'enviado_en' => now(),
        ];

        $global = AvisoMasivo::create($datos);
        $conSegmento = AvisoMasivo::create([...$datos, 'segmento' => ['criterio' => 'de prueba']]);

        $this->assertSame(3, $autorizacion->telefonosQueRecibenElAvisoMasivo($global)->count());
        $this->assertSame(0, $autorizacion->telefonosQueRecibenElAvisoMasivo($conSegmento)->count());
    }

    /**
     * Tres cuentas de la app con un teléfono registrado cada una: un usuario, un socio lavandería y un socio repartidor.
     *
     * @return list<Dispositivo>
     */
    private function tresCuentasConTelefono(): array
    {
        return [
            $this->telefonoDe(Usuario::factory()->conRol(Rol::USUARIO)->create()),
            $this->telefonoDe(Usuario::factory()->conRol(Rol::LAVANDERIA)->create(), Dispositivo::IOS),
            $this->telefonoDe(Repartidor::factory()->create()->cuenta),
        ];
    }

    /**
     * La administración escribe el aviso en el panel y lo manda.
     */
    private function mandarAviso(): void
    {
        Livewire::test(CreateAvisoMasivo::class)
            ->fillForm(['titulo' => self::TITULO, 'mensaje' => self::MENSAJE])
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
