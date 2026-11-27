<?php

namespace Tests\Feature\Confirmacion;

use App\Models\Direccion;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use App\Models\TipoServicio;
use App\Models\UnidadMedida;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El pedido nace al aceptar el resumen, antes del pago: en el estado previo al pago, sin folio y sin confirmado_en, con
 * la plaza de su lavandería, la ventana, el servicio con el precio por kilo copiado y las dos direcciones copiadas, y
 * su primer evento, todo en una transacción (NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3, Módulo A #9 a #11; C-188;
 * ADR-0016). La copia no cambia si después cambian el precio, la dirección guardada o el nombre del municipio en el
 * catálogo: el municipio se copia como texto (openspec/changes/direcciones/tasks.md, tarea 4.5).
 *
 * El estado previo al pago es uno de prueba: su código y su nombre los fija DD-123.
 *
 * Escenarios: confirmacion-E05 y confirmacion-E14 · Spec:
 * openspec/changes/resumen-y-confirmacion/specs/confirmacion/spec.md · Tarea 1.2
 */
class CrearPedidoTest extends TestCase
{
    use AgendaDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('confirmacion-E05: al aceptar el resumen el pedido nace en el estado previo al pago, sin folio ni confirmado_en, con la plaza de su lavandería y la ventana')]
    public function test_confirmacion_e05_el_pedido_nace_en_el_estado_previo_al_pago_con_su_plaza_y_su_ventana(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $casa = $this->direccionDe($cuenta, ['alias' => 'Casa', 'es_favorita' => true]);

        $respuesta = $this->aceptarResumen($cuenta, $this->resumen($casa))->assertCreated();

        $pedido = Pedido::sole();
        $this->assertSame($this->plaza->getKey(), $pedido->id_plazas);
        $this->assertSame($this->lavanderia->getKey(), $pedido->id_lavanderias);
        $this->assertSame($cuenta->getKey(), $pedido->id_usuarios);
        $this->assertSame(EstadoPedido::POR_PAGAR, $pedido->estado->codigo);
        $this->assertNull($pedido->folio);
        $this->assertNull($pedido->confirmado_en);
        $this->assertNull($pedido->id_repartidores);

        // La ventana es el instante absoluto: las 11:00 en UTC-6 son las 17:00 en UTC.
        $ventana = DB::selectOne(
            "select ventana_inicio = '2026-12-03 17:00:00+00'::timestamptz as inicio,
                    ventana_fin = '2026-12-03 18:00:00+00'::timestamptz as fin
               from pedidos where id_pedidos = ?",
            [$pedido->getKey()],
        );
        $this->assertTrue($ventana->inicio);
        $this->assertTrue($ventana->fin);

        // Lo que espera su fila de la deuda nace vacío: el estimado (DD-012), el envío (DD-019, DD-025) y la comisión.
        $this->assertNull($pedido->monto_estimado);
        $this->assertNull($pedido->envio);
        $this->assertNull($pedido->monto_final);
        $this->assertNull($pedido->comision_porcentaje);

        $respuesta->assertExactJson(['pedido' => [
            'id_publico' => $pedido->id_publico,
            'folio' => null,
            'confirmado_en' => null,
            'estado' => ['codigo' => EstadoPedido::POR_PAGAR, 'nombre' => 'Por pagar', 'visible_usuario' => false],
            // Los cinco del seguimiento, también mientras espera su pago, sin hora: no ha llegado a ninguno (estados-E01
            // y E24).
            'seguimiento' => [
                ['codigo' => 'confirmado', 'nombre' => 'Confirmado', 'ocurrido_en' => null],
                ['codigo' => 'recolectado', 'nombre' => 'Recolectado', 'ocurrido_en' => null],
                ['codigo' => 'en_proceso', 'nombre' => 'En proceso', 'ocurrido_en' => null],
                ['codigo' => 'listo', 'nombre' => 'Listo', 'ocurrido_en' => null],
                ['codigo' => 'entregado', 'nombre' => 'Entregado', 'ocurrido_en' => null],
            ],
            'lavanderia' => ['id_publico' => $this->lavanderia->id_publico, 'nombre' => $this->lavanderia->nombre],
            'servicios' => [[
                'codigo' => 'estandar',
                'nombre' => 'Lavado estándar',
                'unidad' => ['codigo' => 'kg', 'nombre' => 'kilo'],
                'precio_unitario' => '18.00',
                'cantidad_estimada' => null,
                'monto_estimado' => null,
            ]],
            'zona_horaria' => 'America/Mexico_City',
            'ventana' => [
                'inicio' => '2026-12-03T11:00:00-06:00',
                'fin' => '2026-12-03T12:00:00-06:00',
                'fecha' => '2026-12-03',
                'hora_inicio' => '11:00',
                'hora_fin' => '12:00',
            ],
            'recogida' => $this->copiaEsperada($casa),
            'entrega' => $this->copiaEsperada($casa),
            'monto_estimado' => null,
            'envio' => null,
        ]]);
    }

    #[TestDox('confirmacion-E05: el servicio queda en pedido_servicios con su unidad y el precio por kilo copiado de la lavandería, sin cantidad todavía')]
    public function test_confirmacion_e05_el_servicio_queda_con_el_precio_por_kilo_copiado(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta)))->assertCreated();

        $servicio = Pedido::sole()->servicios()->sole();
        $this->assertSame($this->plaza->getKey(), $servicio->id_plazas);
        $this->assertSame(TipoServicio::where('codigo', 'estandar')->sole()->getKey(), $servicio->id_tipos_servicio);
        $this->assertSame(UnidadMedida::where('codigo', 'kg')->sole()->getKey(), $servicio->id_unidades_medida);
        $this->assertSame(self::PRECIO_POR_KILO, $servicio->precio_unitario);
        // La cantidad estimada espera DD-012.
        $this->assertNull($servicio->cantidad_estimada);
        $this->assertNull($servicio->fresh()->monto_estimado);
    }

    #[TestDox('confirmacion-E05: la recogida y la entrega quedan copiadas en pedido_direcciones, con sus señas, su punto y de cuál salieron')]
    public function test_confirmacion_e05_las_dos_direcciones_quedan_copiadas(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $casa = $this->direccionDe($cuenta, ['alias' => 'Casa', 'es_favorita' => true]);
        $trabajo = $this->direccionDe($cuenta, [
            'alias' => 'Trabajo',
            'calle' => 'Avenida de prueba',
            'numero_exterior' => '9',
            'numero_interior' => null,
            'referencias' => null,
            'ubicacion' => ['latitud' => 10.25, 'longitud' => -119.75],
        ]);

        $this->aceptarResumen($cuenta, $this->resumen($casa, $trabajo))->assertCreated()
            ->assertJsonPath('pedido.recogida', $this->copiaEsperada($casa))
            ->assertJsonPath('pedido.entrega', $this->copiaEsperada($trabajo));

        $pedido = Pedido::sole();
        $copias = DB::table('pedido_direcciones')
            ->select('*')
            ->selectRaw('ST_Y(ubicacion::geometry) as latitud, ST_X(ubicacion::geometry) as longitud')
            ->where('id_pedidos', $pedido->getKey())
            ->get()
            ->keyBy('tipo');

        $this->assertEqualsCanonicalizing(['recoleccion', 'entrega'], $copias->keys()->all());

        foreach (['recoleccion' => $casa, 'entrega' => $trabajo] as $tipo => $direccion) {
            $copia = $copias[$tipo];
            $this->assertSame($direccion->getKey(), $copia->id_direcciones, $tipo);
            $this->assertSame($this->plaza->getKey(), $copia->id_plazas, $tipo);

            foreach (['alias', 'calle', 'numero_exterior', 'numero_interior', 'colonia', 'codigo_postal', 'referencias'] as $columna) {
                $this->assertSame($direccion->{$columna}, $copia->{$columna}, "{$tipo}.{$columna}");
            }

            // El municipio se copia como texto, con su nombre del catálogo (direcciones, design.md, tarea 4.5).
            $this->assertSame('Municipio de prueba', $copia->municipio, "{$tipo}.municipio");
        }

        // El mismo punto que la dirección guardada, copiado en la base.
        $this->assertSame([10.5, -119.5], [(float) $copias['recoleccion']->latitud, (float) $copias['recoleccion']->longitud]);
        $this->assertSame([10.25, -119.75], [(float) $copias['entrega']->latitud, (float) $copias['entrega']->longitud]);
    }

    #[TestDox('confirmacion-E05: una dirección marcada en el mapa sin municipio se copia sin municipio')]
    public function test_confirmacion_e05_una_direccion_sin_municipio_se_copia_sin_municipio(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $delMapa = $this->direccionDe($cuenta, ['calle' => null, 'numero_exterior' => null, 'id_municipios' => null]);

        $this->aceptarResumen($cuenta, $this->resumen($delMapa))
            ->assertCreated()
            ->assertJsonPath('pedido.recogida.municipio', null);

        $this->assertSame([null, null], DB::table('pedido_direcciones')->pluck('municipio')->all());
    }

    #[TestDox('confirmacion-E05: sin entrega, la entrega es la copia de la misma dirección de la recogida')]
    public function test_confirmacion_e05_sin_entrega_la_entrega_es_la_copia_de_la_recogida(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $casa = $this->direccionDe($cuenta, ['alias' => 'Casa']);

        $this->aceptarResumen($cuenta, $this->resumen($casa))->assertCreated();

        $this->assertSame(
            ['entrega' => $casa->getKey(), 'recoleccion' => $casa->getKey()],
            DB::table('pedido_direcciones')->orderBy('tipo')->pluck('id_direcciones', 'tipo')->all(),
        );
    }

    #[TestDox('confirmacion-E05: el primer estado queda en pedido_eventos, con la cuenta que aceptó el resumen y su hora')]
    public function test_confirmacion_e05_el_primer_estado_queda_en_pedido_eventos(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta)))->assertCreated();

        $evento = PedidoEvento::sole();
        $pedido = Pedido::sole();
        $this->assertSame($pedido->getKey(), $evento->id_pedidos);
        $this->assertSame($pedido->id_plazas, $evento->id_plazas);
        $this->assertSame($pedido->id_estados_pedido, $evento->id_estados_pedido);
        $this->assertSame($cuenta->getKey(), $evento->id_usuarios);
        $this->assertNull($evento->motivo);
        $this->assertTrue($evento->ocurrido_en->eq(CarbonImmutable::now()));
    }

    #[TestDox('confirmacion-E05: el pedido, su servicio, sus direcciones y su evento se guardan en una transacción: si falla uno, no queda nada')]
    public function test_confirmacion_e05_todo_se_guarda_en_una_transaccion(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        Event::listen('eloquent.creating: '.PedidoEvento::class, fn () => throw new RuntimeException('Falla de prueba.'));

        $this->withoutExceptionHandling();

        try {
            $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta)));
            $this->fail('El evento debió fallar.');
        } catch (RuntimeException $error) {
            $this->assertSame('Falla de prueba.', $error->getMessage());
        }

        foreach (['pedidos', 'pedido_servicios', 'pedido_direcciones', 'pedido_eventos'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), $tabla);
        }
    }

    #[TestDox('confirmacion-E05: sin el estado previo al pago configurado (DD-123), o con uno que ve el usuario, el servidor no crea el pedido')]
    public function test_confirmacion_e05_sin_el_estado_previo_al_pago_no_se_crea_el_pedido(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $resumen = $this->resumen($this->direccionDe($cuenta));

        foreach ([null, 'codigo_que_no_existe', EstadoPedido::CONFIRMADO] as $codigo) {
            config(['pedidos.estado_previo_al_pago' => $codigo]);

            $this->aceptarResumen($cuenta, $resumen)
                ->assertStatus(503)
                ->assertExactJson(['message' => 'Todavía no se pueden crear pedidos.']);
        }

        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('confirmacion-E05: un servicio que la lavandería no ofrece, o que no está activo, se rechaza sin crear el pedido')]
    public function test_confirmacion_e05_un_servicio_que_la_lavanderia_no_ofrece_se_rechaza(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $casa = $this->direccionDe($cuenta);
        $this->servicioDePrueba($this->lavanderia, 'planchado', '25.00', activo: false);

        foreach (['seco', 'planchado', 'calzado'] as $servicio) {
            $this->aceptarResumen($cuenta, $this->resumen($casa, datos: ['servicio' => $servicio]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.servicio', ['La lavandería no ofrece ese servicio.']);
        }

        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('confirmacion-E05: sin lavandería, servicio, ventana o recogida, el resumen se rechaza con el campo que falta')]
    public function test_confirmacion_e05_sin_un_dato_del_resumen_se_rechaza_con_el_campo_que_falta(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $resumen = $this->resumen($this->direccionDe($cuenta));

        foreach (['lavanderia', 'servicio', 'ventana', 'recogida'] as $campo) {
            $sinCampo = $resumen;
            unset($sinCampo[$campo]);

            $this->aceptarResumen($cuenta, $sinCampo)->assertUnprocessable()->assertJsonValidationErrors($campo);
        }

        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('confirmacion-E05: una lavandería que el usuario no ve responde como si no existiera, sin crear el pedido')]
    public function test_confirmacion_e05_una_lavanderia_que_el_usuario_no_ve_responde_404(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $casa = $this->direccionDe($cuenta);
        $suspendida = Lavanderia::factory()->en($this->plaza)->suspendida()->create();

        foreach ([$suspendida->id_publico, '01jd8x6m3v2c9q4w5e7r8t0y1u'] as $lavanderia) {
            $this->aceptarResumen($cuenta, $this->resumen($casa, datos: ['lavanderia' => $lavanderia]))
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe la lavandería.']);
        }

        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('confirmacion-E05: con una dirección de otra cuenta, o borrada, el pedido no se crea y la dirección responde como si no existiera (direcciones-E14)')]
    public function test_confirmacion_e05_con_una_direccion_de_otra_cuenta_no_se_crea_el_pedido(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $propia = $this->direccionDe($cuenta);
        $ajena = $this->direccionDe($this->cuentaDeUsuario(), ['alias' => 'Casa de otra cuenta']);
        $borrada = $this->direccionDe($cuenta);
        $borrada->delete();

        foreach ([$this->resumen($ajena), $this->resumen($propia, $ajena), $this->resumen($borrada)] as $resumen) {
            $this->aceptarResumen($cuenta, $resumen)
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe la dirección.']);
        }

        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('confirmacion-E14: el pedido conserva el precio y la dirección "Casa" que copió aunque la lavandería cambie su precio y la dirección se edite o se borre')]
    public function test_confirmacion_e14_la_copia_no_cambia_con_el_precio_ni_con_la_direccion_guardada(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $casa = $this->direccionDe($cuenta, ['alias' => 'Casa', 'es_favorita' => true]);
        $esperada = $this->copiaEsperada($casa);

        $this->aceptarResumen($cuenta, $this->resumen($casa))->assertCreated();
        $pedido = Pedido::sole();

        // La lavandería cambia su precio, y la dirección "Casa" se edita y después se borra.
        $this->servicio->update(['precio' => '25.50']);
        $casa->update([
            'alias' => 'Casa nueva',
            'calle' => 'Otra calle',
            'numero_exterior' => '999',
            'colonia' => 'Otra colonia',
            'referencias' => 'Otras referencias',
            'ubicacion' => ['latitud' => 10.75, 'longitud' => -119.25],
        ]);
        $casa->delete();
        // Y el catálogo cambia el nombre del municipio, como al cargar un archivo nuevo de Correos de México.
        CatalogoDePrueba::municipio()->update(['nombre' => 'Municipio renombrado']);

        $this->assertSame('25.50', $this->servicio->fresh()->precio);
        $this->assertSame(self::PRECIO_POR_KILO, $pedido->servicios()->sole()->precio_unitario);

        $copias = DB::table('pedido_direcciones')
            ->select('*')
            ->selectRaw('ST_Y(ubicacion::geometry) as latitud, ST_X(ubicacion::geometry) as longitud')
            ->where('id_pedidos', $pedido->getKey())
            ->get();

        $this->assertCount(2, $copias);
        foreach ($copias as $copia) {
            $this->assertSame(
                $esperada,
                [
                    'alias' => $copia->alias,
                    'calle' => $copia->calle,
                    'numero_exterior' => $copia->numero_exterior,
                    'numero_interior' => $copia->numero_interior,
                    'colonia' => $copia->colonia,
                    'codigo_postal' => $copia->codigo_postal,
                    'municipio' => $copia->municipio,
                    'referencias' => $copia->referencias,
                    'latitud' => (float) $copia->latitud,
                    'longitud' => (float) $copia->longitud,
                ],
                $copia->tipo,
            );
            // Sigue apuntando a la dirección de la que salió, aunque esté borrada.
            $this->assertSame($casa->getKey(), $copia->id_direcciones);
        }
    }

    /**
     * La copia de la dirección como la devuelve la API: sus señas y su punto, sin su identificador ni su plaza.
     *
     * @return array<string, mixed>
     */
    private function copiaEsperada(Direccion $direccion): array
    {
        $punto = DB::selectOne(
            'select ST_Y(ubicacion::geometry) as latitud, ST_X(ubicacion::geometry) as longitud
               from direcciones where id_direcciones = ?',
            [$direccion->getKey()],
        );

        return [
            'alias' => $direccion->alias,
            'calle' => $direccion->calle,
            'numero_exterior' => $direccion->numero_exterior,
            'numero_interior' => $direccion->numero_interior,
            'colonia' => $direccion->colonia,
            'codigo_postal' => $direccion->codigo_postal,
            // El nombre del municipio del catálogo, copiado como texto.
            'municipio' => $direccion->municipio?->nombre,
            'referencias' => $direccion->referencias,
            'latitud' => (float) $punto->latitud,
            'longitud' => (float) $punto->longitud,
        ];
    }
}
