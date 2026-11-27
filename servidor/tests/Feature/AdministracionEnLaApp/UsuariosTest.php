<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Usuarios\HistorialDelUsuario as HistorialDelPanel;
use App\Http\Controllers\Api\Administracion\UsuariosController;
use App\Identidad\BloqueoDeCuenta;
use App\Models\Direccion;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use App\Pedidos\HistorialDelUsuario;
use Carbon\CarbonImmutable;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\ContratosDeLaApp;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Autorizacion\Soporte\TablaDeRutas;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Historial\Soporte\HistorialDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La gestión de usuarios en la app de la administración, por /api/administracion/usuarios
 * (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3, "Consulta de usuarios activos, historial y bloqueo";
 * ADR-0022, decisión 9): la lista de las cuentas de usuario, la ficha de cada una con su historial de pedidos, y el
 * bloqueo y su levantamiento.
 *
 * Cada ruta llama a las mismas clases que el panel, y la prueba compara su respuesta con ellas (administracion-app-E11):
 * las cuentas salen de Autorizacion::usuariosDelPanel(); el historial, de App\Pedidos\HistorialDelUsuario, la clase de
 * dominio a la que se movió el historial de la ficha del panel (tarea 7.1); y el bloqueo, de
 * App\Identidad\BloqueoDeCuenta, con Autorizacion::puedeBloquear(). Que el panel no cambió lo prueban, sin tocarlas,
 * GestionDeUsuariosTest y BloqueoDeUsuariosTest (tests/Feature/Administracion/).
 *
 * Una cuenta que no es de usuario, o que no existe, responde 404 (administracion-app-E12); y solo entra la
 * administración con su token de administración (administracion-app-E06 y E07). Las sesiones son reales
 * (Soporte\SesionesDeLaApp).
 *
 * Fuera de aquí: el filtro de usuarios activos (administracion-app-E26; BR-33 y DD-111) y lo que pasa con un pedido
 * activo al bloquear (administracion-app-E27; DD-016). Dar de alta, editar o borrar usuarios no tiene ruta: el Módulo D
 * #3 dice consulta, historial y bloqueo.
 *
 * Los pedidos nacen como en la app, en dos plazas de prueba (HistorialDePrueba); las fechas son de prueba.
 *
 * Escenarios: administracion-app-E06, administracion-app-E07, administracion-app-E11, administracion-app-E12,
 * administracion-app-E24 y administracion-app-E25 · Spec:
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md · Tareas 7.1, 7.2 y 7.3
 */
class UsuariosTest extends TestCase
{
    use AgendaDePrueba;
    use ContratosDeLaApp;
    use EstadosDePrueba;
    use HistorialDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use SesionesDeLaApp;
    use TablaDeRutas;

    private const USUARIOS = '/api/administracion/usuarios';

    /** Lo único que trae el 404 de una cuenta que no es de usuario o que no existe (ADR-0019, decisión 3). */
    private const NO_EXISTE = ['message' => 'No existe el usuario.'];

    private const BLOQUEADA = 'Esta cuenta está bloqueada.';

    private Plaza $plazaDos;

    private Lavanderia $lavanderiaDeLaPlazaDos;

    private Usuario $ana;

    private Usuario $beto;

    /** @var array{0: Pedido, 1: Pedido, 2: Pedido} Los de Ana, del más reciente al más antiguo: confirmado, en proceso y entregado. */
    private array $deAna;

    private Pedido $deBeto;

    protected function setUp(): void
    {
        parent::setUp();

        // La plaza de prepararPedido(), con su zona en (-120, 10); y otra, con su zona en (-118, 10).
        $this->prepararPedido();
        $this->plazaDos = $this->plazaConAgenda('Plaza de prueba 2', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($this->plazaDos, 'Centro de la plaza 2', $this->cuadrado(-118.0, 10.0));
        $this->lavanderiaDeLaPlazaDos = $this->otraLavanderia($this->plazaDos);

        $this->ana = Usuario::factory()->conRol(Rol::USUARIO)->create([
            'correo' => 'ana@ejemplo.com',
            'nombre' => 'Ana Pruebas',
            'telefono' => '6181234567',
        ]);
        $this->beto = Usuario::factory()->conRol(Rol::USUARIO)->create([
            'correo' => 'beto@ejemplo.com',
            'nombre' => 'Beto Sandoval',
        ]);

        // Los tres de Ana, de fechas distintas y en las dos plazas: entregado el 1 de diciembre, en proceso el 2 y
        // confirmado el 3.
        $entregado = $this->entregar($this->pedidoConfirmadoEl($this->ana, '2026-12-01'));
        $enProceso = $this->pedidoConfirmadoEl($this->ana, '2026-12-02');
        $this->aceptarComoLaLavanderia($enProceso);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::RECOLECTADO);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::EN_PROCESO);
        $confirmado = $this->pedidoConfirmadoEl(
            $this->ana,
            '2026-12-03',
            lavanderia: $this->lavanderiaDeLaPlazaDos,
            recogida: $this->direccionEnLaPlazaDos($this->ana),
        );

        // El monto final llega al pesar (DD-012) y hoy ningún pedido lo trae: aquí se escribe a mano en uno, para ver
        // cómo sale en la ficha.
        $entregado->forceFill(['monto_final' => '1180.50'])->save();

        $this->deAna = [$confirmado, $enProceso->fresh(), $entregado->fresh()];
        $this->deBeto = $this->pedidoConfirmadoEl(
            $this->beto,
            '2026-12-03',
            '10:00',
            $this->lavanderiaDeLaPlazaDos,
            $this->direccionEnLaPlazaDos($this->beto),
        );
    }

    #[TestDox('administracion-app-E24 y administracion-app-E11: la lista trae las cuentas con el rol de usuario, cada una con su correo, su nombre y su estado, las mismas de usuariosDelPanel(); no las de las lavanderías, los repartidores ni la administración')]
    public function test_administracion_app_e24_la_lista_trae_las_cuentas_de_usuario(): void
    {
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);
        $this->beto->forceFill(['estado' => Usuario::BLOQUEADO, 'bloqueado_en' => now()])->save();

        $respuesta = $this->llamarConToken($token, 'GET', self::USUARIOS)->assertOk();

        $this->assertSame([
            'usuarios' => [
                ['id_publico' => $this->ana->id_publico, 'correo' => 'ana@ejemplo.com', 'nombre' => 'Ana Pruebas', 'estado' => 'activo'],
                ['id_publico' => $this->beto->id_publico, 'correo' => 'beto@ejemplo.com', 'nombre' => 'Beto Sandoval', 'estado' => 'bloqueado'],
            ],
            'pagina' => ['actual' => 1, 'ultima' => 1, 'por_pagina' => UsuariosController::POR_PAGINA, 'total' => 2],
        ], $respuesta->json());

        // Las mismas cuentas que da al panel el componente de autorización (administracion-app-E11).
        $this->assertEqualsCanonicalizing(
            app(Autorizacion::class)->usuariosDelPanel($administracion)->pluck('id_publico')->all(),
            array_column($respuesta->json('usuarios'), 'id_publico'),
        );

        // Ni las cuentas de los socios ni las de la administración: el sembrado sí las tiene.
        $this->assertGreaterThan(0, Repartidor::query()->count());
        foreach ([$this->lavanderia->administrador, $this->lavanderiaDeLaPlazaDos->administrador, $administracion] as $otra) {
            $this->assertStringNotContainsString($otra->correo, $respuesta->getContent());
        }
        $this->assertStringNotContainsString(Repartidor::query()->firstOrFail()->cuenta->correo, $respuesta->getContent());
    }

    #[TestDox('administracion-app-E24: la lista va en páginas, ordenada por correo, y cada página dice cuál es, cuántas hay, de cuántas cuentas y cuántas van en cada una')]
    public function test_administracion_app_e24_la_lista_va_en_paginas(): void
    {
        // Con Ana y Beto, una cuenta más que las de una página.
        Usuario::factory()->count(UsuariosController::POR_PAGINA - 1)->conRol(Rol::USUARIO)->create();
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);
        $total = UsuariosController::POR_PAGINA + 1;

        $primera = $this->llamarConToken($token, 'GET', self::USUARIOS)->assertOk();
        $segunda = $this->llamarConToken($token, 'GET', self::USUARIOS, ['pagina' => 2])->assertOk();

        $this->assertCount(UsuariosController::POR_PAGINA, $primera->json('usuarios'));
        $this->assertCount(1, $segunda->json('usuarios'));
        $this->assertSame(['actual' => 1, 'ultima' => 2, 'por_pagina' => UsuariosController::POR_PAGINA, 'total' => $total], $primera->json('pagina'));
        $this->assertSame(['actual' => 2, 'ultima' => 2, 'por_pagina' => UsuariosController::POR_PAGINA, 'total' => $total], $segunda->json('pagina'));

        // Entre las dos páginas, cada cuenta de usuario una vez y en el orden de su correo.
        $correos = [...array_column($primera->json('usuarios'), 'correo'), ...array_column($segunda->json('usuarios'), 'correo')];
        $esperados = app(Autorizacion::class)->usuariosDelPanel($administracion)->orderBy('correo')->pluck('correo')->all();
        $this->assertSame($esperados, $correos);

        // Una página que no es un número es la primera; una que ya no tiene cuentas llega vacía.
        $this->assertSame($primera->json(), $this->llamarConToken($token, 'GET', self::USUARIOS, ['pagina' => 'otra'])->json());
        $this->assertSame([], $this->llamarConToken($token, 'GET', self::USUARIOS, ['pagina' => 3])->assertOk()->json('usuarios'));
    }

    #[TestDox('administracion-app-E24: la lista se busca por correo y por nombre, sin distinguir mayúsculas; un % o un _ se buscan como letras, la página dice cuántas cuentas encontró, y un texto que no es UTF-8 válido da la lista completa y no un 500')]
    public function test_administracion_app_e24_la_lista_se_busca_por_correo_y_por_nombre(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $porCorreo = $this->llamarConToken($token, 'GET', self::USUARIOS, ['buscar' => 'ANA@'])->assertOk();
        $this->assertSame([$this->ana->id_publico], array_column($porCorreo->json('usuarios'), 'id_publico'));
        $this->assertSame(1, $porCorreo->json('pagina.total'));

        $porNombre = $this->llamarConToken($token, 'GET', self::USUARIOS, ['buscar' => ' sandoval '])->assertOk();
        $this->assertSame([$this->beto->id_publico], array_column($porNombre->json('usuarios'), 'id_publico'));

        foreach (['%', '_', 'nadie'] as $texto) {
            $respuesta = $this->llamarConToken($token, 'GET', self::USUARIOS, ['buscar' => $texto])->assertOk();

            $this->assertSame([], $respuesta->json('usuarios'), $texto);
            $this->assertSame(0, $respuesta->json('pagina.total'), $texto);
        }

        // Sin texto, o con algo que no es texto (una lista, o bytes que no son UTF-8 válido: ?buscar=%FF), la lista sale
        // completa y no un 500.
        foreach (['', '   ', ['ana'], "\xFF", "ana\xFF"] as $vacio) {
            $this->assertSame(2, $this->llamarConToken($token, 'GET', self::USUARIOS, ['buscar' => $vacio])->assertOk()->json('pagina.total'));
        }
    }

    #[TestDox('administracion-app-E24: la ficha de un usuario con tres pedidos trae su correo, su nombre, su teléfono, su estado y su historial: sus tres pedidos, cada uno con folio, fecha, lavandería, estado y monto, del más reciente al más antiguo, y ninguno del otro usuario')]
    public function test_administracion_app_e24_la_ficha_trae_el_historial_del_usuario(): void
    {
        [$confirmado, $enProceso, $entregado] = $this->deAna;
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $respuesta = $this->llamarConToken($token, 'GET', $this->fichaDe($this->ana))->assertOk();

        $this->assertSame([
            'usuario' => [
                'id_publico' => $this->ana->id_publico,
                'correo' => 'ana@ejemplo.com',
                'nombre' => 'Ana Pruebas',
                'telefono' => '6181234567',
                'estado' => 'activo',
                'historial' => [
                    ['folio' => $confirmado->folio, 'fecha' => '2026-12-03', 'lavanderia' => $this->lavanderiaDeLaPlazaDos->nombre, 'estado' => 'Confirmado', 'monto' => null],
                    ['folio' => $enProceso->folio, 'fecha' => '2026-12-02', 'lavanderia' => $this->lavanderia->nombre, 'estado' => 'En proceso', 'monto' => null],
                    ['folio' => $entregado->folio, 'fecha' => '2026-12-01', 'lavanderia' => $this->lavanderia->nombre, 'estado' => 'Entregado', 'monto' => '1180.50'],
                ],
            ],
        ], $respuesta->json());

        $this->assertStringNotContainsString($this->deBeto->folio, $respuesta->getContent());
        $this->assertSame(
            [$this->deBeto->folio],
            array_column($this->llamarConToken($token, 'GET', $this->fichaDe($this->beto))->assertOk()->json('usuario.historial'), 'folio'),
        );

        // El ULID se acepta también en mayúsculas.
        $this->assertSame($respuesta->json(), $this->llamarConToken($token, 'GET', self::USUARIOS.'/'.Str::upper($this->ana->id_publico))->json());
    }

    #[TestDox('administracion-app-E11: la ficha de la API da lo que dan las clases que usa el panel: la cuenta de usuariosDelPanel() y el historial de la clase de dominio, el mismo de la ficha del panel con la fecha AAAA-MM-DD y el monto sin formato')]
    public function test_administracion_app_e11_la_ficha_da_lo_mismo_que_las_clases_del_panel(): void
    {
        $administracion = $this->cuentaDeAdministracion();
        $respuesta = $this->llamarConToken($this->tokenDeLaApp($administracion), 'GET', $this->fichaDe($this->ana))->assertOk();

        $cuenta = app(Autorizacion::class)->usuariosDelPanel($administracion)->whereKey($this->ana->getKey())->sole();
        $this->assertSame(
            ['correo' => $cuenta->correo, 'nombre' => $cuenta->nombre, 'telefono' => $cuenta->telefono, 'estado' => $cuenta->estado],
            array_intersect_key($respuesta->json('usuario'), array_flip(['correo', 'nombre', 'telefono', 'estado'])),
        );

        // El historial, fila por fila, el de la clase de dominio.
        $historial = $respuesta->json('usuario.historial');
        $this->assertSame(
            array_map(
                fn (array $fila): array => [...$fila, 'fecha' => $fila['fecha']->toDateString()],
                app(HistorialDelUsuario::class)->de($administracion, $this->ana),
            ),
            $historial,
        );

        // Y el de la ficha del panel, que solo lo escribe distinto: la fecha DD/MM/AAAA y el monto en pesos.
        $delPanel = app(HistorialDelPanel::class)->de($administracion, $this->ana);
        foreach (['folio', 'lavanderia', 'estado'] as $columna) {
            $this->assertSame(array_column($delPanel, $columna), array_column($historial, $columna), $columna);
        }
        $this->assertSame(
            array_column($delPanel, 'fecha'),
            array_map(fn (string $fecha): string => CarbonImmutable::createFromFormat('!Y-m-d', $fecha)->format('d/m/Y'), array_column($historial, 'fecha')),
        );
        $this->assertSame([null, null, '$1,180.50'], array_column($delPanel, 'monto'));
        $this->assertSame([null, null, '1180.50'], array_column($historial, 'monto'));
    }

    #[TestDox('administracion-app-E11: la fecha del historial de la ficha es la de la recolección en la hora de la plaza, aunque en UTC ya sea el día siguiente, como en el panel')]
    public function test_administracion_app_e11_la_fecha_del_historial_es_la_de_la_hora_de_la_plaza(): void
    {
        $cuenta = Usuario::factory()->conRol(Rol::USUARIO)->create();
        // 19:00 del 4 de diciembre en la hora de la plaza (UTC-6) es la 01:00 del 5 en UTC.
        $pedido = $this->pedidoConfirmadoEl($cuenta, '2026-12-04', '19:00', $this->lavanderiaDeNoche());
        $this->assertSame('2026-12-05', $pedido->ventana_inicio->utc()->toDateString());
        $administracion = $this->cuentaDeAdministracion();

        $respuesta = $this->llamarConToken($this->tokenDeLaApp($administracion), 'GET', $this->fichaDe($cuenta))->assertOk();

        $this->assertSame('2026-12-04', $respuesta->json('usuario.historial.0.fecha'));
        $this->assertSame('04/12/2026', app(HistorialDelPanel::class)->de($administracion, $cuenta)[0]['fecha']);
    }

    #[TestDox('administracion-app-E11: el historial de la ficha vive en una clase de dominio que no sabe de Filament: los pedidos confirmados de la cuenta, del más reciente al más antiguo, con su fecha en la hora de la plaza, su estado operativo y su monto; a quien no es de administración no le da ninguno')]
    public function test_administracion_app_e11_el_historial_vive_en_una_clase_de_dominio(): void
    {
        [$confirmado, $enProceso, $entregado] = $this->deAna;
        $porPagar = $this->pedidoPorPagarEl($this->ana, '2026-12-03', '12:00');
        $administracion = $this->cuentaDeAdministracion();

        $filas = app(HistorialDelUsuario::class)->de($administracion, $this->ana);

        // El que espera su pago no entró al flujo: no tiene folio y no va (C-188).
        $this->assertNull($porPagar->folio);
        $this->assertSame([$confirmado->folio, $enProceso->folio, $entregado->folio], array_column($filas, 'folio'));
        $this->assertSame(['Confirmado', 'En proceso', 'Entregado'], array_column($filas, 'estado'));
        $this->assertSame([null, null, '1180.50'], array_column($filas, 'monto'));

        foreach ($filas as $fila) {
            $this->assertInstanceOf(CarbonImmutable::class, $fila['fecha']);
        }
        $this->assertSame(
            ['2026-12-03 11:00', '2026-12-02 11:00', '2026-12-01 11:00'],
            array_map(fn (CarbonImmutable $fecha): string => $fecha->format('Y-m-d H:i'), array_column($filas, 'fecha')),
        );
        $this->assertSame(
            [$this->plazaDos->zona_horaria, $this->plaza->zona_horaria, $this->plaza->zona_horaria],
            array_map(fn (CarbonImmutable $fecha): string => $fecha->getTimezone()->getName(), array_column($filas, 'fecha')),
        );

        foreach (self::ROLES_QUE_NO_SON_DE_ADMINISTRACION as $rol) {
            $this->assertSame([], app(HistorialDelUsuario::class)->de($this->cuentaQueOperaComo($rol), $this->ana), $rol);
        }
    }

    #[TestDox('administracion-app-E12: la ficha, el bloqueo y su levantamiento de una cuenta que no es de usuario o que no existe responden 404 con solo su mensaje, y nada cambia: un ULID de nadie, un valor que no es ULID, la cuenta de una lavandería, la de un repartidor, la propia y la de otra administración')]
    public function test_administracion_app_e12_una_cuenta_que_no_es_de_usuario_responde_404(): void
    {
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);
        $deLaLavanderia = $this->lavanderia->administrador;
        $delRepartidor = Repartidor::query()->firstOrFail()->cuenta;
        $otraAdministracion = $this->cuentaDeAdministracion();

        $cuentas = [
            'un ULID que no es de nadie' => (string) Str::ulid(),
            'un valor que no es ULID' => 'no-es-un-ulid',
            'la cuenta de una lavandería' => $deLaLavanderia->id_publico,
            'la cuenta de un repartidor' => $delRepartidor->id_publico,
            'la propia cuenta de administración' => $administracion->id_publico,
            'otra cuenta de administración' => $otraAdministracion->id_publico,
        ];
        $antes = $this->fotoDeLaBase();

        foreach ($cuentas as $cual => $identificador) {
            foreach ([['GET', ''], ['POST', '/bloqueo'], ['DELETE', '/bloqueo']] as [$metodo, $despues]) {
                $respuesta = $this->llamarConToken($token, $metodo, self::USUARIOS.'/'.$identificador.$despues);

                $this->assertSame(404, $respuesta->status(), "{$metodo} · {$cual}: {$respuesta->getContent()}");
                $this->assertSame(self::NO_EXISTE, $respuesta->json(), "{$metodo} · {$cual}");
            }
        }

        $this->assertSame($antes, $this->fotoDeLaBase());
        $this->assertSame(Usuario::ACTIVO, $deLaLavanderia->fresh()->estado);
    }

    #[TestDox('administracion-app-E25: la administración bloquea a un usuario activo con la sesión abierta en la app, con la regla de administracion-E03: su siguiente petición se rechaza y no vuelve a entrar; al levantar el bloqueo, con la de administracion-E04, vuelve a activo y entra otra vez; cada respuesta es su ficha con el estado nuevo')]
    public function test_administracion_app_e25_bloquear_a_un_usuario_y_levantar_el_bloqueo(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $sesion = $this->iniciarSesionDeAna()->assertOk()->json('token');
        $otroTelefono = $this->iniciarSesionDeAna()->assertOk()->json('token');
        $this->llamarConToken($sesion, 'GET', '/api/cuenta')->assertOk();
        $bloqueo = self::USUARIOS."/{$this->ana->id_publico}/bloqueo";

        $this->travelTo(CarbonImmutable::parse('2026-12-04 15:00:00', 'UTC'));
        $bloqueada = $this->llamarConToken($token, 'POST', $bloqueo)->assertOk();

        $this->assertSame('bloqueado', $bloqueada->json('usuario.estado'));
        $this->assertSame($this->llamarConToken($token, 'GET', $this->fichaDe($this->ana))->json(), $bloqueada->json());

        // administracion-E03: la sesión que tenía abierta se rechaza, en todos sus teléfonos, y no vuelve a entrar.
        $this->llamarConToken($sesion, 'GET', '/api/cuenta')->assertUnauthorized();
        $this->llamarConToken($otroTelefono, 'GET', '/api/cuenta')->assertUnauthorized();
        $this->iniciarSesionDeAna()
            ->assertUnprocessable()
            ->assertJsonPath('errors.correo', [self::BLOQUEADA])
            ->assertJsonMissingPath('token');
        $this->assertSame(Usuario::BLOQUEADO, $this->ana->fresh()->estado);
        $this->assertSame('2026-12-04T15:00:00Z', $this->ana->fresh()->bloqueado_en->utc()->toIso8601ZuluString());
        $this->assertSame(0, $this->ana->tokens()->count());

        // Bloquear otra vez la deja como está, con su hora de bloqueo, como BloqueoDeCuenta.
        $this->travel(5)->minutes();
        $this->llamarConToken($token, 'POST', $bloqueo)->assertOk()->assertJsonPath('usuario.estado', 'bloqueado');
        $this->assertSame('2026-12-04T15:00:00Z', $this->ana->fresh()->bloqueado_en->utc()->toIso8601ZuluString());

        // administracion-E04: al levantar el bloqueo vuelve a activo y entra otra vez.
        $levantada = $this->llamarConToken($token, 'DELETE', $bloqueo)->assertOk();

        $this->assertSame('activo', $levantada->json('usuario.estado'));
        $this->assertSame($this->llamarConToken($token, 'GET', $this->fichaDe($this->ana))->json(), $levantada->json());
        $this->assertSame(Usuario::ACTIVO, $this->ana->fresh()->estado);
        $this->assertNull($this->ana->fresh()->bloqueado_en);
        $nueva = $this->iniciarSesionDeAna()->assertOk()->json('token');
        $this->llamarConToken($nueva, 'GET', '/api/cuenta')->assertOk()->assertJsonPath('usuario.id_publico', $this->ana->id_publico);

        // Levantarlo otra vez la deja activa.
        $this->llamarConToken($token, 'DELETE', $bloqueo)->assertOk()->assertJsonPath('usuario.estado', 'activo');
    }

    #[TestDox('administracion-app-E25 y administracion-app-E11: el bloqueo y su levantamiento por la API dejan la cuenta como la deja App\Identidad\BloqueoDeCuenta, la clase que llama el botón del panel')]
    public function test_administracion_app_e25_el_bloqueo_de_la_api_es_el_de_la_clase_del_panel(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $this->tokenDeLaApp($this->ana);
        $this->tokenDeLaApp($this->beto);
        $this->travelTo(CarbonImmutable::parse('2026-12-04 15:00:00', 'UTC'));

        $this->llamarConToken($token, 'POST', self::USUARIOS."/{$this->ana->id_publico}/bloqueo")->assertOk();
        app(BloqueoDeCuenta::class)->bloquear($this->beto);

        $this->assertSame($this->estadoDelBloqueo($this->beto), $this->estadoDelBloqueo($this->ana));
        $this->assertSame([Usuario::BLOQUEADO, '2026-12-04T15:00:00Z', 0], $this->estadoDelBloqueo($this->ana));

        $this->llamarConToken($token, 'DELETE', self::USUARIOS."/{$this->ana->id_publico}/bloqueo")->assertOk();
        app(BloqueoDeCuenta::class)->levantar($this->beto->fresh());

        $this->assertSame($this->estadoDelBloqueo($this->beto), $this->estadoDelBloqueo($this->ana));
        $this->assertSame([Usuario::ACTIVO, null, 0], $this->estadoDelBloqueo($this->ana));
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: la lista, la ficha, el bloqueo y su levantamiento solo los abre la administración con su token: 403 sin datos a las cuentas de usuario, lavandería y repartidor y a un token sin la habilidad, 401 sin token y con el vencido, y nada cambia')]
    public function test_administracion_app_e06_y_e07_cada_ruta_de_usuarios_solo_la_abre_la_administracion(): void
    {
        $ficha = $this->fichaDe($this->ana);

        $this->assertSoloEntraLaAdministracion('GET', self::USUARIOS);
        $this->assertSoloEntraLaAdministracion('GET', $ficha);
        $this->assertSoloEntraLaAdministracion('POST', "{$ficha}/bloqueo");
        $this->assertSoloEntraLaAdministracion('DELETE', "{$ficha}/bloqueo");

        $this->assertSame(Usuario::ACTIVO, $this->ana->fresh()->estado);
    }

    #[TestDox('administracion-app-E24: la API de la administración consulta las cuentas de usuario, abre su ficha y las bloquea, nada más: no tiene rutas para darlas de alta, editarlas ni borrarlas')]
    public function test_administracion_app_e24_solo_hay_consulta_ficha_y_bloqueo(): void
    {
        $rutas = array_filter(
            $this->rutasDeLaApi(),
            fn (Ruta $ruta): bool => Str::startsWith($ruta->uri(), 'api/administracion/usuarios'),
        );

        $this->assertSame([
            'DELETE api/administracion/usuarios/{usuario}/bloqueo',
            'GET api/administracion/usuarios',
            'GET api/administracion/usuarios/{usuario}',
            'POST api/administracion/usuarios/{usuario}/bloqueo',
        ], array_keys($rutas));
    }

    #[TestDox('administracion-app-E24: el contrato de la lista con la app: cada cuenta con su id_publico, su correo, su nombre y su estado, y la página; con una búsqueda sin coincidencias, la lista vacía')]
    public function test_administracion_app_e24_el_contrato_de_la_lista(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $this->beto->forceFill(['estado' => Usuario::BLOQUEADO, 'bloqueado_en' => now()])->save();

        $this->assertCumpleSuContrato('usuarios.lista', 'GET /api/administracion/usuarios', $this->llamarConToken($token, 'GET', self::USUARIOS));
        $this->assertCumpleSuContrato(
            'usuarios.lista-vacia',
            'GET /api/administracion/usuarios',
            $this->llamarConToken($token, 'GET', self::USUARIOS, ['buscar' => 'Sin coincidencia']),
        );
    }

    #[TestDox('administracion-app-E24 y administracion-app-E12: el contrato de la ficha con la app: la cuenta con su teléfono y su historial, un pedido con monto y otro sin él; y el 404 de la que no existe')]
    public function test_administracion_app_e24_el_contrato_de_la_ficha(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $ficha = $this->llamarConToken($token, 'GET', $this->fichaDe($this->ana));

        $this->assertCumpleSuContrato('usuarios.ficha', 'GET /api/administracion/usuarios/{usuario}', $ficha);
        $this->assertCount(3, $ficha->json('usuario.historial'));
        $this->assertCumpleSuContrato(
            'usuarios.no-existe',
            'GET /api/administracion/usuarios/{usuario}',
            $this->llamarConToken($token, 'GET', self::USUARIOS.'/'.Str::ulid()),
            404,
        );
    }

    #[TestDox('administracion-app-E25: el contrato del bloqueo con la app: la ficha de la cuenta bloqueada y la de la que vuelve a estar activa')]
    public function test_administracion_app_e25_el_contrato_del_bloqueo(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $url = $this->fichaDe($this->ana).'/bloqueo';

        $this->assertCumpleSuContrato('usuarios.bloqueo', 'POST /api/administracion/usuarios/{usuario}/bloqueo', $this->llamarConToken($token, 'POST', $url));
        $this->assertCumpleSuContrato(
            'usuarios.levantar-bloqueo',
            'DELETE /api/administracion/usuarios/{usuario}/bloqueo',
            $this->llamarConToken($token, 'DELETE', $url),
        );
    }

    private function fichaDe(Usuario $cuenta): string
    {
        return self::USUARIOS.'/'.$cuenta->id_publico;
    }

    /**
     * Una dirección nueva de la cuenta en la plaza 2, dentro de su zona.
     */
    private function direccionEnLaPlazaDos(Usuario $cuenta): Direccion
    {
        return $this->direccionDe($cuenta, [
            'id_plazas' => $this->plazaDos->getKey(),
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -117.5],
        ]);
    }

    private function iniciarSesionDeAna(): TestResponse
    {
        return $this->llamarConToken(null, 'POST', '/api/sesion', [
            'correo' => 'ana@ejemplo.com',
            'password' => UsuarioFactory::CONTRASENA,
        ]);
    }

    /**
     * Cómo quedó la cuenta: su estado, la hora de su bloqueo en UTC y cuántos tokens le quedan.
     *
     * @return array{0: string, 1: string|null, 2: int}
     */
    private function estadoDelBloqueo(Usuario $cuenta): array
    {
        $cuenta = $cuenta->fresh();

        return [$cuenta->estado, $cuenta->bloqueado_en?->utc()->toIso8601ZuluString(), $cuenta->tokens()->count()];
    }
}
