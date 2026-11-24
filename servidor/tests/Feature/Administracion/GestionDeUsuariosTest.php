<?php

namespace Tests\Feature\Administracion;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Usuarios\HistorialDelUsuario;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\Usuarios\Pages\ViewUsuario;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Historial\Soporte\HistorialDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La gestión de usuarios del panel: la lista de las cuentas de usuario y la ficha de cada una con su historial de
 * pedidos, de solo lectura (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3, "Consulta de usuarios activos,
 * historial y bloqueo"; ADR-0003).
 *
 * El historial trae los pedidos confirmados de la cuenta, cada uno con su folio, su fecha, su lavandería, su estado y su
 * monto (administracion-E01). La fecha es la de la recolección en la hora de la plaza, como en el historial del usuario;
 * el estado, el operativo; el monto, el final si ya se pesó y si no el estimado, que hoy nacen vacíos (DD-012, DD-010).
 * Dar de alta, editar o borrar usuarios desde el panel está fuera del contrato (proposal.md). Quién ve qué lo dice el
 * componente de autorización (ADR-0019).
 *
 * Escenario: administracion-E01 · Spec: openspec/changes/administracion/specs/administracion/spec.md · Tarea 1.1
 */
class GestionDeUsuariosTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use HistorialDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Los roles que no son de administración; repartidor todavía no tiene constante en Rol. */
    private const OTROS_ROLES = [Rol::USUARIO, Rol::LAVANDERIA, 'repartidor'];

    private Usuario $ana;

    private Usuario $beto;

    /** @var array{0: Pedido, 1: Pedido, 2: Pedido} Los de Ana: entregado, en proceso y confirmado, del 1, 2 y 3 de diciembre. */
    private array $deAna;

    private Pedido $deBeto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->ana = $this->cuentaDeUsuario();
        $this->beto = $this->cuentaDeUsuario();

        $entregado = $this->entregar($this->pedidoConfirmadoEl($this->ana, '2026-12-01'));
        $enProceso = $this->pedidoConfirmadoEl($this->ana, '2026-12-02');
        $this->aceptarComoLaLavanderia($enProceso);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::RECOLECTADO);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::EN_PROCESO);
        $confirmado = $this->pedidoConfirmadoEl($this->ana, '2026-12-03', lavanderia: $this->otraLavanderia());
        $this->deAna = [$entregado, $enProceso->fresh(), $confirmado];

        $this->deBeto = $this->pedidoConfirmadoEl($this->beto, '2026-12-03', '10:00');

        // El monto final llega al pesar (DD-012) y hoy ningún pedido lo trae: aquí se escribe a mano en uno, para ver
        // cómo sale en la ficha.
        $entregado->forceFill(['monto_final' => '1180.50'])->save();
    }

    #[TestDox('administracion-E01: la administración abre la ficha de un usuario con tres pedidos de fechas distintas y ve los tres, cada uno con su folio, su fecha, su lavandería, su estado y su monto, del más reciente al más antiguo; no ve el pedido del otro usuario')]
    public function test_administracion_e01_la_ficha_trae_los_tres_pedidos_del_usuario_y_no_el_del_otro(): void
    {
        [$entregado, $enProceso, $confirmado] = $this->deAna;
        $this->comoAdministracion();

        Livewire::test(ViewUsuario::class, ['record' => $this->ana->getRouteKey()])
            ->assertOk()
            ->assertSee($this->ana->correo)
            ->assertSeeInOrder([
                $confirmado->folio, '03/12/2026', $confirmado->lavanderia->nombre, 'Confirmado', 'Sin monto',
                $enProceso->folio, '02/12/2026', $this->lavanderia->nombre, 'En proceso', 'Sin monto',
                $entregado->folio, '01/12/2026', $this->lavanderia->nombre, 'Entregado', '$1,180.50',
            ])
            ->assertDontSee($this->deBeto->folio);
    }

    #[TestDox('administracion-E01: el historial de la ficha sale del componente de autorización: los tres pedidos confirmados del usuario, sin el que espera su pago ni el del otro usuario')]
    public function test_administracion_e01_el_historial_trae_solo_los_pedidos_confirmados_del_usuario(): void
    {
        [$entregado, $enProceso, $confirmado] = $this->deAna;
        $porPagar = $this->pedidoPorPagarEl($this->ana, '2026-12-03', '12:00');
        $administracion = $this->comoAdministracion();

        $filas = app(HistorialDelUsuario::class)->de($administracion, $this->ana);

        $this->assertSame([
            ['folio' => $confirmado->folio, 'fecha' => '03/12/2026', 'lavanderia' => $confirmado->lavanderia->nombre, 'estado' => 'Confirmado', 'monto' => null],
            ['folio' => $enProceso->folio, 'fecha' => '02/12/2026', 'lavanderia' => $this->lavanderia->nombre, 'estado' => 'En proceso', 'monto' => null],
            ['folio' => $entregado->folio, 'fecha' => '01/12/2026', 'lavanderia' => $this->lavanderia->nombre, 'estado' => 'Entregado', 'monto' => '$1,180.50'],
        ], $filas);

        $this->assertNull($porPagar->folio);
        $this->assertSame([$this->deBeto->folio], array_column(app(HistorialDelUsuario::class)->de($administracion, $this->beto), 'folio'));
    }

    #[TestDox('administracion-E01: la fecha del historial es la de la recolección en la hora de la plaza, aunque en UTC ya sea el día siguiente')]
    public function test_administracion_e01_la_fecha_es_la_de_la_recoleccion_en_la_hora_de_la_plaza(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        // 19:00 del 4 de diciembre en la hora de la plaza (UTC-6) es la 01:00 del 5 en UTC.
        $pedido = $this->pedidoConfirmadoEl($cuenta, '2026-12-04', '19:00', $this->lavanderiaDeNoche());
        $this->assertSame('2026-12-05', $pedido->ventana_inicio->utc()->toDateString());

        $filas = app(HistorialDelUsuario::class)->de($this->comoAdministracion(), $cuenta);

        $this->assertSame('04/12/2026', $filas[0]['fecha']);
    }

    #[TestDox('administracion-E01: la gestión de usuarios lista las cuentas de usuario con su correo y su estado, y no las de las lavanderías ni las de la administración')]
    public function test_administracion_e01_la_lista_trae_las_cuentas_de_usuario(): void
    {
        $administracion = $this->comoAdministracion();

        Livewire::test(ListUsuarios::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->ana, $this->beto])
            ->assertCanNotSeeTableRecords([$this->lavanderia->administrador, $administracion])
            ->assertTableColumnStateSet('correo', $this->ana->correo, $this->ana)
            ->assertTableColumnFormattedStateSet('estado', 'Activo', $this->ana)
            ->searchTable($this->beto->correo)
            ->assertCanSeeTableRecords([$this->beto])
            ->assertCanNotSeeTableRecords([$this->ana]);
    }

    #[TestDox('administracion-E01: la ficha es de solo lectura: el panel no da de alta, ni edita, ni borra usuarios')]
    public function test_administracion_e01_la_ficha_es_de_solo_lectura(): void
    {
        $this->comoAdministracion();

        $this->assertSame(['index', 'view'], array_keys(UsuarioResource::getPages()));
        $this->assertFalse(UsuarioResource::canCreate());
        $this->assertFalse(UsuarioResource::canEdit($this->ana));
        $this->assertFalse(UsuarioResource::canDelete($this->ana));
        $this->assertFalse(UsuarioResource::canDeleteAny());

        $this->get('/admin/usuarios')->assertOk();
        $this->get("/admin/usuarios/{$this->ana->id_publico}")->assertOk();
        $this->get("/admin/usuarios/{$this->ana->id_publico}/edit")->assertNotFound();
        $this->get('/admin/usuarios/create')->assertNotFound();

        Livewire::test(ViewUsuario::class, ['record' => $this->ana->getRouteKey()])
            ->assertActionDoesNotExist('edit')
            ->assertActionDoesNotExist('delete');
        Livewire::test(ListUsuarios::class)
            ->assertActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete');
    }

    #[TestDox('administracion-E01: una cuenta que no es de administración no abre la gestión de usuarios ni la ficha, y el componente no le da ningún usuario ni pedido; a la administración le da las cuentas de usuario y sus pedidos confirmados')]
    public function test_administracion_e01_solo_la_administracion_ve_los_usuarios_y_su_historial(): void
    {
        $autorizacion = app(Autorizacion::class);

        // Antes de crear las cuentas de los otros roles: entre ellas va otra de usuario.
        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create();
        $this->assertEqualsCanonicalizing(
            [$this->ana->getKey(), $this->beto->getKey()],
            $autorizacion->usuariosDelPanel($administracion)->pluck('id_usuarios')->all(),
        );
        $this->assertSame(4, $autorizacion->pedidosDelPanel($administracion)->count());

        foreach (self::OTROS_ROLES as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();
            $this->actingAs($cuenta, 'web');

            $this->get('/admin/usuarios')->assertForbidden();
            $respuesta = $this->get("/admin/usuarios/{$this->ana->id_publico}")->assertForbidden();
            $this->assertStringNotContainsString($this->deAna[0]->folio, $respuesta->getContent());

            $this->assertSame(0, $autorizacion->usuariosDelPanel($cuenta)->count(), $rol);
            $this->assertSame(0, $autorizacion->pedidosDelPanel($cuenta)->count(), $rol);
            $this->assertSame([], app(HistorialDelUsuario::class)->de($cuenta, $this->ana), $rol);
        }
    }
}
