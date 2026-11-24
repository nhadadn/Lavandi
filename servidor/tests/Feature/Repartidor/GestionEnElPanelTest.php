<?php

namespace Tests\Feature\Repartidor;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Repartidores\Pages\EditRepartidor;
use App\Filament\Resources\Repartidores\Pages\ListRepartidores;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use App\Repartidores\EstadoDelRepartidor;
use App\Repartidores\TransicionNoPermitida;
use Database\Factories\RepartidorFactory;
use Database\Factories\UsuarioFactory;
use Illuminate\Contracts\Filesystem\Filesystem as Disco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\DiscoLocalDeFotos;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * La gestión de repartidores en el panel de administración: dar de alta en una plaza al que se registró, suspender, dar
 * de baja y editar, y solo la administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2, "Alta, baja,
 * suspensión y edición de socios repartidores"; :18:Módulo C #1, "El alta, validación y autorización del repartidor
 * corresponden a EL CLIENTE"; ADR-0003).
 *
 * El alta autoriza un registro que ya existe (design.md, lectura 1) y fija su plaza (lectura 2). La validación del
 * repartidor ocurre fuera de la plataforma. La baja no borra: deja la fila con su estado y en borrado suave, y sus
 * entregas se quedan (E07).
 *
 * Escenarios: repartidor-E05 a repartidor-E09 · Spec: openspec/changes/repartidor/specs/repartidor/spec.md · Tarea 1.2
 */
class GestionEnElPanelTest extends TestCase
{
    use AgendaDePrueba;
    use DiscoLocalDeFotos;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;

    private Disco $disco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disco = $this->usarDiscoLocalDeFotos();
        $this->prepararPedido();
    }

    #[TestDox('repartidor-E05: la lista del panel muestra cada repartidor con su nombre, su correo, su teléfono, su tipo de vehículo, su estado y su plaza, también los pendientes y los de baja')]
    public function test_repartidor_e05_la_lista_muestra_cada_repartidor_con_su_estado_y_su_plaza(): void
    {
        $pendiente = Repartidor::factory()->pendiente()
            ->deLaCuenta(RepartidorFactory::cuentaDeRepartidor(['nombre' => 'Repartidor Pendiente', 'correo' => 'pendiente@ejemplo.com']))
            ->create(['tipo_vehiculo' => 'Bicicleta']);
        $activo = Repartidor::factory()->en($this->plaza)->create();
        $suspendido = Repartidor::factory()->en($this->plaza)->suspendido()->create();
        $deBaja = Repartidor::factory()->en($this->plaza)->deBaja()->create();
        $this->comoAdministracion();

        Livewire::test(ListRepartidores::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$pendiente, $activo, $suspendido, $deBaja])
            ->assertTableColumnStateSet('cuenta.nombre', 'Repartidor Pendiente', $pendiente)
            ->assertTableColumnStateSet('cuenta.correo', 'pendiente@ejemplo.com', $pendiente)
            ->assertTableColumnStateSet('cuenta.telefono', '6180000000', $pendiente)
            ->assertTableColumnStateSet('tipo_vehiculo', 'Bicicleta', $pendiente)
            ->assertTableColumnFormattedStateSet('estado', 'Pendiente', $pendiente)
            ->assertTableColumnFormattedStateSet('estado', 'Activo', $activo)
            ->assertTableColumnFormattedStateSet('estado', 'Suspendido', $suspendido)
            ->assertTableColumnFormattedStateSet('estado', 'Dado de baja', $deBaja)
            ->assertTableColumnStateSet('plaza.nombre', $this->plaza->nombre, $activo);

        $this->get('/admin/repartidores')->assertOk()->assertSee('Repartidor Pendiente');
    }

    #[TestDox('repartidor-E05: la administración da de alta en una plaza al repartidor pendiente; queda activo en esa plaza, con la hora del alta, y ya puede pedir los pedidos que se le asignen')]
    public function test_repartidor_e05_la_administracion_da_de_alta_en_una_plaza(): void
    {
        $this->travelTo('2026-12-03 15:45:00');
        $pendiente = Repartidor::factory()->pendiente()->create();
        $this->comoRepartidor($pendiente)->getJson('/api/repartidor/pedidos')->assertForbidden();
        $this->comoAdministracion();

        Livewire::test(ListRepartidores::class)
            ->assertTableActionVisible('darDeAlta', $pendiente)
            ->assertTableActionHidden('suspender', $pendiente)
            ->callTableAction('darDeAlta', $pendiente, data: ['plaza' => $this->plaza->getKey()])
            ->assertHasNoTableActionErrors();

        $pendiente->refresh();
        $this->assertSame(Repartidor::ACTIVO, $pendiente->estado);
        $this->assertSame($this->plaza->getKey(), $pendiente->id_plazas);
        $this->assertSame('2026-12-03T15:45:00Z', $pendiente->aprobado_en->utc()->toIso8601ZuluString());

        $this->comoRepartidor($pendiente)->getJson('/api/repartidor/perfil')
            ->assertOk()
            ->assertJsonPath('repartidor.estado', Repartidor::ACTIVO)
            ->assertJsonPath('repartidor.puede_operar', true)
            ->assertJsonPath('repartidor.plaza.nombre', $this->plaza->nombre);
        $this->comoRepartidor($pendiente)->getJson('/api/repartidor/pedidos')->assertOk()->assertJsonStructure(['pedidos']);
    }

    #[TestDox('repartidor-E05: el alta pide la plaza; sin ella no se da de alta, y solo se da de alta a un pendiente')]
    public function test_repartidor_e05_el_alta_pide_la_plaza_y_solo_a_un_pendiente(): void
    {
        $pendiente = Repartidor::factory()->pendiente()->create();
        $activo = Repartidor::factory()->en($this->plaza)->create();
        $this->comoAdministracion();

        Livewire::test(ListRepartidores::class)
            ->callTableAction('darDeAlta', $pendiente, data: ['plaza' => null])
            ->assertHasTableActionErrors(['plaza' => 'required']);
        Livewire::test(ListRepartidores::class)->assertTableActionHidden('darDeAlta', $activo);

        $this->assertSame(Repartidor::PENDIENTE, $pendiente->fresh()->estado);
        $this->assertNull($pendiente->fresh()->id_plazas);

        // La regla vive en el estado del repartidor, no solo en el botón.
        $this->expectException(TransicionNoPermitida::class);
        app(EstadoDelRepartidor::class)->darDeAlta($activo, $this->plazaDePrueba('Plaza de prueba 2'));
    }

    #[TestDox('repartidor-E06: al suspenderlo en el panel, la API le rechaza ver y mover pedidos, con su estado; al quitarle la suspensión recupera ese acceso')]
    public function test_repartidor_e06_la_suspension_le_quita_y_le_devuelve_los_pedidos(): void
    {
        $repartidor = Repartidor::factory()->en($this->plaza)->create();
        $pedido = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->comoRepartidor($repartidor)->getJson('/api/repartidor/pedidos')->assertOk();
        $administracion = $this->comoAdministracion();

        Livewire::test(ListRepartidores::class)
            ->assertTableActionVisible('suspender', $repartidor)
            ->assertTableActionHidden('levantarSuspension', $repartidor)
            ->callTableAction('suspender', $repartidor)
            ->assertHasNoTableActionErrors();

        $this->assertSame(Repartidor::SUSPENDIDO, $repartidor->fresh()->estado);

        $suspendido = [
            'message' => 'Tu cuenta de repartidor está suspendida.',
            'estado' => Repartidor::SUSPENDIDO,
        ];
        $this->comoRepartidor($repartidor)->getJson('/api/repartidor/pedidos')->assertForbidden()->assertExactJson($suspendido);
        $this->comoRepartidor($repartidor)->getJson("/api/repartidor/pedidos/{$pedido->id_publico}")->assertForbidden()->assertExactJson($suspendido);
        $this->comoRepartidor($repartidor)->postJson("/api/repartidor/pedidos/{$pedido->id_publico}/estado", ['estado' => EstadoPedido::RECOLECTADO])
            ->assertForbidden()
            ->assertExactJson($suspendido);
        // Sigue viendo su perfil, que le dice que está suspendido.
        $this->comoRepartidor($repartidor)->getJson('/api/repartidor/perfil')
            ->assertOk()
            ->assertJsonPath('repartidor.estado', Repartidor::SUSPENDIDO)
            ->assertJsonPath('repartidor.puede_operar', false);

        // Las peticiones de la app olvidan la sesión web: la administración vuelve a entrar al panel.
        $this->actingAs($administracion, 'web');
        Livewire::test(ListRepartidores::class)
            ->assertTableActionHidden('suspender', $repartidor)
            ->assertTableActionVisible('levantarSuspension', $repartidor)
            ->callTableAction('levantarSuspension', $repartidor)
            ->assertHasNoTableActionErrors();

        $this->assertSame(Repartidor::ACTIVO, $repartidor->fresh()->estado);
        $this->assertSame($this->plaza->getKey(), $repartidor->fresh()->id_plazas);
        $this->comoRepartidor($repartidor)->getJson('/api/repartidor/pedidos')->assertOk();
        $this->comoRepartidor($repartidor)->getJson('/api/repartidor/perfil')->assertJsonPath('repartidor.puede_operar', true);
    }

    #[TestDox('repartidor-E06: la suspensión también se hace desde la página del repartidor, y solo se suspende a uno activo')]
    public function test_repartidor_e06_la_suspension_desde_la_pagina_y_solo_a_un_activo(): void
    {
        $repartidor = Repartidor::factory()->en($this->plaza)->create();
        $pendiente = Repartidor::factory()->pendiente()->create();
        $this->comoAdministracion();

        Livewire::test(EditRepartidor::class, ['record' => $repartidor->getRouteKey()])
            ->assertActionVisible('suspender')
            ->callAction('suspender')
            ->assertActionHidden('suspender')
            ->assertActionVisible('levantarSuspension');

        $this->assertSame(Repartidor::SUSPENDIDO, $repartidor->fresh()->estado);

        Livewire::test(ListRepartidores::class)->assertTableActionHidden('suspender', $pendiente);

        $this->expectException(TransicionNoPermitida::class);
        app(EstadoDelRepartidor::class)->suspender($pendiente);
    }

    #[TestDox('repartidor-E07: al darlo de baja, el repartidor ya no inicia sesión y su sesión abierta deja de servir; sus entregas completadas se conservan, con el pedido ligado a él')]
    public function test_repartidor_e07_la_baja_cierra_la_entrada_y_conserva_las_entregas(): void
    {
        $cuenta = RepartidorFactory::cuentaDeRepartidor(['correo' => 'baja@ejemplo.com']);
        $repartidor = Repartidor::factory()->en($this->plaza)->deLaCuenta($cuenta)->create();
        $entregas = [$this->entregaCompletadaDe($repartidor), $this->entregaCompletadaDe($repartidor)];
        $token = $cuenta->createToken('app')->plainTextToken;
        $this->comoAdministracion();

        Livewire::test(ListRepartidores::class)
            ->assertTableActionVisible('darDeBaja', $repartidor)
            ->callTableAction('darDeBaja', $repartidor)
            ->assertHasNoTableActionErrors()
            ->assertTableActionHidden('darDeBaja', $repartidor)
            ->assertTableActionHidden('suspender', $repartidor);

        // La fila se queda, con su estado y en borrado suave.
        $deBaja = Repartidor::withTrashed()->whereKey($repartidor->getKey())->sole();
        $this->assertSame(Repartidor::BAJA, $deBaja->estado);
        $this->assertNotNull($deBaja->deleted_at);

        // Ya no inicia sesión, ni con su sesión abierta ni con su contraseña.
        $this->assertSame(0, $cuenta->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/repartidor/perfil')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/sesion', ['correo' => 'baja@ejemplo.com', 'password' => UsuarioFactory::CONTRASENA])
            ->assertUnprocessable()
            ->assertJsonMissingPath('token');
        $this->assertFalse(app(Autorizacion::class)->puedeIniciarSesion($cuenta->fresh()));

        // Sus entregas completadas se conservan: el pedido sigue entregado y ligado a él, para el historial y para el
        // indicador de entregas por repartidor (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5).
        foreach ($entregas as $entrega) {
            $entrega->refresh();
            $this->assertSame(EstadoPedido::ENTREGADO, $entrega->estado->codigo);
            $this->assertTrue($deBaja->is($entrega->repartidor));
        }
        $this->assertSame(2, Pedido::query()->entregados()->where('id_repartidores', $repartidor->getKey())->count());
        // Su cuenta tampoco se borra: sus datos se quedan.
        $this->assertNotNull(Usuario::query()->whereKey($cuenta->getKey())->first());
    }

    #[TestDox('repartidor-E07: se da de baja a un activo o a un suspendido; ninguna acción deshace la baja, y un pendiente sin plaza no se da de baja')]
    public function test_repartidor_e07_a_quien_se_da_de_baja(): void
    {
        $suspendido = Repartidor::factory()->en($this->plaza)->suspendido()->create();
        $pendiente = Repartidor::factory()->pendiente()->create();
        $this->comoAdministracion();

        Livewire::test(ListRepartidores::class)
            ->assertTableActionVisible('darDeBaja', $suspendido)
            ->assertTableActionHidden('darDeBaja', $pendiente)
            ->callTableAction('darDeBaja', $suspendido)
            ->assertTableActionHidden('levantarSuspension', $suspendido)
            ->assertTableActionHidden('darDeAlta', $suspendido);

        $this->assertSame(Repartidor::BAJA, Repartidor::withTrashed()->whereKey($suspendido->getKey())->value('estado'));

        $this->expectException(TransicionNoPermitida::class);
        app(EstadoDelRepartidor::class)->darDeBaja($pendiente);
    }

    #[TestDox('repartidor-E08: la administración cambia el nombre, el teléfono, el correo, la foto y el tipo de vehículo del repartidor; el cambio queda guardado y el repartidor lo ve en su módulo')]
    public function test_repartidor_e08_la_administracion_edita_al_repartidor(): void
    {
        // La subida temporal de la foto en Livewire falla con el reloj adelantado al jueves de prueba de prepararPedido();
        // esta prueba no usa pedidos, así que corre con el reloj real.
        $this->travelBack();
        $cuenta = RepartidorFactory::cuentaDeRepartidor(['correo' => 'antes@ejemplo.com', 'nombre' => 'Nombre de antes']);
        $repartidor = Repartidor::factory()->en($this->plaza)->deLaCuenta($cuenta)->create(['tipo_vehiculo' => 'Bicicleta']);
        $this->comoAdministracion();

        $this->get("/admin/repartidores/{$repartidor->id_publico}/edit")->assertOk()->assertSee('Nombre de antes');

        Livewire::test(EditRepartidor::class, ['record' => $repartidor->getRouteKey()])
            ->assertSchemaStateSet([
                'nombre' => 'Nombre de antes',
                'correo' => 'antes@ejemplo.com',
                'telefono' => '6180000000',
                'tipo_vehiculo' => 'Bicicleta',
            ])
            ->fillForm([
                'nombre' => 'Nombre de después',
                'telefono' => '871 765 4321',
                'correo' => 'Despues@Ejemplo.com',
                'tipo_vehiculo' => 'Motocicleta',
                'foto' => $this->fotoDePrueba('desde-el-panel.jpg'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $cuenta->refresh();
        $this->assertSame('Nombre de después', $cuenta->nombre);
        $this->assertSame('871 765 4321', $cuenta->telefono);
        $this->assertSame('despues@ejemplo.com', $cuenta->correo);
        $this->assertSame('Motocicleta', $repartidor->fresh()->tipo_vehiculo);
        $this->assertStringStartsWith("usuarios/{$cuenta->id_publico}/", $cuenta->foto_ruta);
        $this->assertSame([$cuenta->foto_ruta], $this->disco->allFiles());
        // La contraseña no cambia: entra con la de siempre y su correo nuevo.
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $cuenta->password));

        $this->comoRepartidor($repartidor)->getJson('/api/repartidor/perfil')
            ->assertOk()
            ->assertJsonPath('repartidor.nombre', 'Nombre de después')
            ->assertJsonPath('repartidor.telefono', '871 765 4321')
            ->assertJsonPath('repartidor.correo', 'despues@ejemplo.com')
            ->assertJsonPath('repartidor.tipo_vehiculo', 'Motocicleta')
            ->assertJsonPath('repartidor.foto.url', fn (string $url): bool => str_contains($url, 'signature='));
    }

    #[TestDox('repartidor-E08: la edición usa las reglas del registro: un dato vacío, un teléfono que no vale o el correo de otra cuenta se rechazan junto a su campo, y nada cambia; el correo propio sí se conserva')]
    public function test_repartidor_e08_la_edicion_usa_las_reglas_del_registro(): void
    {
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'ocupado@ejemplo.com']);
        $cuenta = RepartidorFactory::cuentaDeRepartidor(['correo' => 'propio@ejemplo.com', 'nombre' => 'Nombre de prueba']);
        $repartidor = Repartidor::factory()->en($this->plaza)->deLaCuenta($cuenta)->create();
        $this->comoAdministracion();

        Livewire::test(EditRepartidor::class, ['record' => $repartidor->getRouteKey()])
            ->fillForm(['nombre' => '', 'telefono' => 'no es teléfono', 'correo' => 'OCUPADO@ejemplo.com', 'tipo_vehiculo' => ''])
            ->call('save')
            ->assertHasFormErrors(['nombre', 'telefono', 'correo', 'tipo_vehiculo']);

        $cuenta->refresh();
        $this->assertSame('Nombre de prueba', $cuenta->nombre);
        $this->assertSame('propio@ejemplo.com', $cuenta->correo);

        // Guardar con su propio correo, aunque cambie de mayúsculas, no choca consigo mismo.
        Livewire::test(EditRepartidor::class, ['record' => $repartidor->getRouteKey()])
            ->fillForm(['correo' => 'Propio@Ejemplo.com', 'nombre' => 'Nombre nuevo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nombre nuevo', $cuenta->fresh()->nombre);
        $this->assertSame([], $this->disco->allFiles());
    }

    #[TestDox('repartidor-E09: un usuario, una lavandería y un repartidor, cada uno con sesión, no entran a la gestión de repartidores, no tocan sus acciones ni su edición, y el repartidor no cambia')]
    public function test_repartidor_e09_solo_la_administracion_gestiona_repartidores(): void
    {
        $pendiente = Repartidor::factory()->pendiente()->create();
        $activo = Repartidor::factory()->en($this->plaza)->create();
        $otras = [
            Usuario::factory()->conRol(Rol::USUARIO)->create(),
            Usuario::factory()->conRol(Rol::LAVANDERIA)->create(),
            $activo->cuenta,
        ];
        $autorizacion = app(Autorizacion::class);

        foreach ($otras as $otra) {
            $this->actingAs($otra, 'web');

            $this->get('/admin/repartidores')->assertForbidden();
            $this->get("/admin/repartidores/{$pendiente->id_publico}/edit")->assertForbidden();
            $this->get("/admin/repartidores/{$activo->id_publico}/edit")->assertForbidden();
            Livewire::test(ListRepartidores::class)->assertForbidden();
            $this->assertSame(0, $autorizacion->repartidoresDelPanel($otra)->count());

            // Desde la app tampoco: ninguna ruta cambia a un repartidor; el perfil solo se consulta.
            $this->comoRepartidor($otra)->patchJson('/api/repartidor/perfil', ['nombre' => 'Otro nombre'])->assertStatus(405);
        }

        $this->assertSame(Repartidor::PENDIENTE, $pendiente->fresh()->estado);
        $this->assertNull($pendiente->fresh()->id_plazas);
        $this->assertSame(Repartidor::ACTIVO, $activo->fresh()->estado);
        $this->assertSame($activo->cuenta->nombre, $activo->cuenta->fresh()->nombre);

        // La administración sí los ve, de todas las plazas, también los de baja.
        Repartidor::factory()->en($this->plazaDePrueba('Plaza de prueba 2'))->deBaja()->create();
        $this->assertSame(3, $autorizacion->repartidoresDelPanel(Usuario::factory()->conRol(Rol::ADMINISTRACION)->create())->count());
    }

    /**
     * Una entrega completada del repartidor: un pedido que se le ofrece y él acepta, que la lavandería lleva hasta En
     * proceso de entrega y que él marca Entregado, todo con las operaciones reales.
     */
    private function entregaCompletadaDe(Repartidor $repartidor): Pedido
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $this->repartidorQueLoAcepta($pedido, $repartidor);

        return $this->llevarHastaEntregado($pedido);
    }
}
