<?php

namespace Tests\Feature\PerfilUsuario;

use App\Models\ReporteSoporte;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilUsuario\Soporte\PerfilDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El formulario de reporte de Soporte: se guarda en reportes_soporte con la cuenta, la descripción, la fecha y, si la
 * persona quiere, uno de sus pedidos; el de otra cuenta se rechaza (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3,
 * Módulo A #16, "formulario de reporte"; :20, Separación por tipo de usuario).
 *
 * La plaza del reporte (decisión 27 de acta-2026-11-20-modelo-y-pasarela): con pedido, la del pedido; sin pedido, la
 * del último pedido de la cuenta; sin pedidos, la de su dirección guardada más reciente que tiene plaza. Sin ninguna,
 * el reporte se guarda igual, sin plaza: el Módulo A #16 pide el formulario sin condiciones (perfil-usuario-E17).
 *
 * A quién le llega el reporte y cómo se le responde a la persona es DD-108 (perfil-usuario-E14): aquí solo se guarda.
 *
 * Escenarios: perfil-usuario-E11, perfil-usuario-E12, perfil-usuario-E13 y perfil-usuario-E17 · Spec:
 * openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md · Tarea 1.4 de
 * openspec/changes/perfil-y-soporte/tasks.md
 */
class ReporteTest extends TestCase
{
    use PerfilDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const DESCRIPCION = 'La camisa azul llegó con una mancha.';

    private const SIN_DESCRIPCION = 'Cuéntanos qué pasó.';

    private const NO_EXISTE_EL_PEDIDO = ['message' => 'No existe el pedido.'];

    #[TestDox('perfil-usuario-E11: el reporte con un pedido propio queda guardado con la cuenta, el pedido, su plaza, la descripción y la fecha')]
    public function test_perfil_usuario_e11_el_reporte_con_un_pedido_propio_queda_guardado(): void
    {
        $this->travelTo('2026-12-03 18:30:00');
        $plaza = $this->plazaDePrueba();
        $ana = $this->cuentaDeUsuario();
        $pedido = $this->pedidoDe($ana, $plaza);

        $respuesta = $this->reportar($ana, ['descripcion' => self::DESCRIPCION, 'pedido' => $pedido->id_publico]);

        $respuesta->assertCreated()->assertExactJson(['reporte' => [
            'descripcion' => self::DESCRIPCION,
            'pedido' => $pedido->id_publico,
            'estado' => 'recibido',
            'recibido_en' => '2026-12-03T18:30:00Z',
        ]]);

        $reporte = ReporteSoporte::sole();
        $this->assertSame($ana->getKey(), $reporte->id_usuarios);
        $this->assertSame($pedido->getKey(), $reporte->id_pedidos);
        $this->assertSame($plaza->getKey(), $reporte->id_plazas);
        $this->assertSame(self::DESCRIPCION, $reporte->mensaje);
        $this->assertSame(ReporteSoporte::RECIBIDO, $reporte->estado);
        $this->assertSame('2026-12-03 18:30:00', $reporte->created_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($reporte->atendido_en);

        // La respuesta no trae llaves internas ni la plaza.
        $this->assertStringNotContainsString('id_reportes_soporte', $respuesta->getContent());
        $this->assertStringNotContainsString('id_plazas', $respuesta->getContent());
    }

    #[TestDox('perfil-usuario-E11: el pedido se acepta con su ULID en mayúsculas, y la descripción se guarda sin los espacios de los lados')]
    public function test_perfil_usuario_e11_el_pedido_en_mayusculas_y_la_descripcion_sin_espacios(): void
    {
        $ana = $this->cuentaDeUsuario();
        $pedido = $this->pedidoDe($ana, $this->plazaDePrueba());

        $this->reportar($ana, ['descripcion' => '  '.self::DESCRIPCION."\n", 'pedido' => Str::upper($pedido->id_publico)])
            ->assertCreated()
            ->assertJsonPath('reporte.pedido', $pedido->id_publico)
            ->assertJsonPath('reporte.descripcion', self::DESCRIPCION);

        $this->assertSame(self::DESCRIPCION, ReporteSoporte::sole()->mensaje);
    }

    #[TestDox('perfil-usuario-E11: la misma persona manda varios reportes, también del mismo pedido')]
    public function test_perfil_usuario_e11_la_misma_persona_manda_varios_reportes(): void
    {
        $ana = $this->cuentaDeUsuario();
        $pedido = $this->pedidoDe($ana, $this->plazaDePrueba());

        $this->reportar($ana, ['descripcion' => self::DESCRIPCION, 'pedido' => $pedido->id_publico])->assertCreated();
        $this->reportar($ana, ['descripcion' => 'Otra cosa.', 'pedido' => $pedido->id_publico])->assertCreated();
        $this->reportar($ana, ['descripcion' => 'Sin pedido.'])->assertCreated();

        $this->assertSame(3, ReporteSoporte::where('id_usuarios', $ana->getKey())->count());
    }

    #[TestDox('perfil-usuario-E11: sin pedido, el reporte va a la plaza del último pedido de la cuenta')]
    public function test_perfil_usuario_e11_sin_pedido_el_reporte_va_a_la_plaza_del_ultimo_pedido(): void
    {
        $plazaA = $this->plazaDePrueba('Plaza de prueba A');
        $plazaB = $this->plazaDePrueba('Plaza de prueba B');
        $plazaC = $this->plazaDePrueba('Plaza de prueba C');
        $ana = $this->cuentaDeUsuario();
        $this->pedidoDe($ana, $plazaA, ['created_at' => '2026-11-01 12:00:00']);
        $this->pedidoDe($ana, $plazaB, ['created_at' => '2026-11-20 12:00:00']);
        // Una dirección más reciente no gana: con pedidos, cuenta el último pedido.
        $this->direccionDe($ana, $plazaC, ['created_at' => '2026-11-21 12:00:00']);
        // El pedido más reciente de otra cuenta no cuenta.
        $this->pedidoDe($this->cuentaDeUsuario(), $plazaC, ['created_at' => '2026-11-21 12:00:00']);

        $this->reportar($ana, ['descripcion' => self::DESCRIPCION, 'pedido' => null])
            ->assertCreated()
            ->assertJsonPath('reporte.pedido', null);

        $reporte = ReporteSoporte::sole();
        $this->assertNull($reporte->id_pedidos);
        $this->assertSame($plazaB->getKey(), $reporte->id_plazas);
    }

    #[TestDox('perfil-usuario-E11: sin pedidos, el reporte va a la plaza de la dirección guardada más reciente que tiene plaza')]
    public function test_perfil_usuario_e11_sin_pedidos_el_reporte_va_a_la_plaza_de_la_direccion_mas_reciente(): void
    {
        $plazaA = $this->plazaDePrueba('Plaza de prueba A');
        $plazaB = $this->plazaDePrueba('Plaza de prueba B');
        $plazaC = $this->plazaDePrueba('Plaza de prueba C');
        $ana = $this->cuentaDeUsuario();
        $this->direccionDe($ana, $plazaA, ['created_at' => '2026-11-01 12:00:00']);
        $this->direccionDe($ana, $plazaB, ['created_at' => '2026-11-10 12:00:00']);
        // Fuera de toda zona: no tiene plaza que dar.
        $this->direccionDe($ana, null, ['created_at' => '2026-11-15 12:00:00']);
        // Borrada: ya no es una dirección guardada.
        $this->direccionDe($ana, $plazaC, ['created_at' => '2026-11-20 12:00:00'])->delete();
        // La de otra cuenta no cuenta.
        $this->direccionDe($this->cuentaDeUsuario(), $plazaC, ['created_at' => '2026-11-21 12:00:00']);

        $this->reportar($ana, ['descripcion' => self::DESCRIPCION])->assertCreated();

        $reporte = ReporteSoporte::sole();
        $this->assertNull($reporte->id_pedidos);
        $this->assertSame($plazaB->getKey(), $reporte->id_plazas);
    }

    #[TestDox('perfil-usuario-E17: sin pedidos ni direcciones con plaza, el reporte se guarda igual, sin plaza, con la cuenta, la descripción y la fecha, y la respuesta es la de cualquier reporte')]
    public function test_perfil_usuario_e17_sin_pedidos_ni_direcciones_con_plaza_el_reporte_se_guarda_sin_plaza(): void
    {
        $this->travelTo('2026-12-03 18:30:00');
        $plaza = $this->plazaDePrueba();
        $ana = $this->cuentaDeUsuario();
        // Lo de otra cuenta no le da plaza a Ana.
        $beto = $this->cuentaDeUsuario();
        $this->pedidoDe($beto, $plaza);
        $this->direccionDe($beto, $plaza);

        $this->reportar($ana, ['descripcion' => self::DESCRIPCION])
            ->assertCreated()
            ->assertExactJson(['reporte' => [
                'descripcion' => self::DESCRIPCION,
                'pedido' => null,
                'estado' => 'recibido',
                'recibido_en' => '2026-12-03T18:30:00Z',
            ]]);

        // Ni una dirección fuera de toda zona ni una borrada le dan plaza: el segundo también se guarda sin ella.
        $this->direccionDe($ana, null);
        $this->direccionDe($ana, $plaza)->delete();
        $this->reportar($ana, ['descripcion' => 'Otra cosa.'])->assertCreated()->assertJsonPath('reporte.pedido', null);

        $reportes = ReporteSoporte::query()->orderBy('id_reportes_soporte')->get();
        $this->assertCount(2, $reportes);
        foreach ($reportes as $reporte) {
            $this->assertSame($ana->getKey(), $reporte->id_usuarios);
            $this->assertNull($reporte->id_pedidos);
            $this->assertNull($reporte->id_plazas);
            $this->assertNull($reporte->plaza);
            $this->assertSame(ReporteSoporte::RECIBIDO, $reporte->estado);
        }
        $this->assertSame([self::DESCRIPCION, 'Otra cosa.'], $reportes->pluck('mensaje')->all());
        $this->assertSame('2026-12-03 18:30:00', $reportes[0]->created_at->utc()->format('Y-m-d H:i:s'));
    }

    #[TestDox('perfil-usuario-E17: con un pedido o una dirección con plaza, el reporte sigue llevando su plaza, como antes')]
    public function test_perfil_usuario_e17_con_plaza_el_reporte_sigue_llevandola(): void
    {
        $plaza = $this->plazaDePrueba();
        $ana = $this->cuentaDeUsuario();
        $this->reportar($ana, ['descripcion' => 'Antes de tener plaza.'])->assertCreated();

        $this->direccionDe($ana, $plaza);
        $this->reportar($ana, ['descripcion' => self::DESCRIPCION])->assertCreated();

        $this->assertSame(
            [null, $plaza->getKey()],
            ReporteSoporte::query()->orderBy('id_reportes_soporte')->pluck('id_plazas')->all(),
        );
    }

    #[TestDox('perfil-usuario-E12: un reporte con la descripción vacía, solo con espacios, nula o que no viene se rechaza y no se guarda')]
    public function test_perfil_usuario_e12_un_reporte_sin_descripcion_se_rechaza_y_no_se_guarda(): void
    {
        $ana = $this->cuentaDeUsuario();
        $pedido = $this->pedidoDe($ana, $this->plazaDePrueba());

        foreach ([['descripcion' => ''], ['descripcion' => "  \n "], ['descripcion' => null], []] as $cuerpo) {
            $this->reportar($ana, [...$cuerpo, 'pedido' => $pedido->id_publico])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['descripcion' => self::SIN_DESCRIPCION]);
        }

        $this->assertSame(0, ReporteSoporte::count());
    }

    #[TestDox('perfil-usuario-E12: una descripción que no es texto, o de más de 5000 caracteres, se rechaza y no se guarda')]
    public function test_perfil_usuario_e12_una_descripcion_que_no_es_texto_o_demasiado_larga_se_rechaza(): void
    {
        $ana = $this->cuentaDeUsuario();
        $this->pedidoDe($ana, $this->plazaDePrueba());

        foreach ([123, ['texto'], true] as $descripcion) {
            $this->reportar($ana, ['descripcion' => $descripcion])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['descripcion' => 'La descripción va en texto.']);
        }

        $this->reportar($ana, ['descripcion' => str_repeat('a', 5001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['descripcion' => 'La descripción es demasiado larga.']);

        $this->assertSame(0, ReporteSoporte::count());

        $this->reportar($ana, ['descripcion' => str_repeat('a', 5000)])->assertCreated();
    }

    #[TestDox('perfil-usuario-E13: un reporte con el pedido de otra cuenta responde igual que uno que no existe, sin ningún dato, y no se guarda')]
    public function test_perfil_usuario_e13_un_reporte_con_el_pedido_de_otra_cuenta_se_rechaza(): void
    {
        $plaza = $this->plazaDePrueba();
        $ana = $this->cuentaDeUsuario();
        $this->pedidoDe($ana, $plaza);
        $pedidoDeBeto = $this->pedidoDe($this->cuentaDeUsuario(), $plaza);

        $intentos = [
            $pedidoDeBeto->id_publico,
            Str::upper($pedidoDeBeto->id_publico),
            // Un ULID bien formado que no es de ningún pedido.
            '01jd8x6m3v2c9q4w5e7r8t0y1z',
            // Ni la llave interna ni un folio identifican un pedido en la API.
            (string) $pedidoDeBeto->getKey(),
            'ABCD2345',
        ];

        foreach ($intentos as $pedido) {
            $respuesta = $this->reportar($ana, ['descripcion' => self::DESCRIPCION, 'pedido' => $pedido]);

            $respuesta->assertNotFound()->assertExactJson(self::NO_EXISTE_EL_PEDIDO);
            $this->assertStringNotContainsString($pedidoDeBeto->id_publico, $respuesta->getContent());
        }

        $this->assertSame(0, ReporteSoporte::count());
    }

    #[TestDox('perfil-usuario-E13: el pedido de otra cuenta responde 404 aunque la cuenta no tenga plaza, y un pedido que no es texto se rechaza')]
    public function test_perfil_usuario_e13_el_pedido_ajeno_responde_404_aunque_la_cuenta_no_tenga_plaza(): void
    {
        $ana = $this->cuentaDeUsuario();
        $pedidoDeBeto = $this->pedidoDe($this->cuentaDeUsuario(), $this->plazaDePrueba());

        $this->reportar($ana, ['descripcion' => self::DESCRIPCION, 'pedido' => $pedidoDeBeto->id_publico])
            ->assertNotFound()
            ->assertExactJson(self::NO_EXISTE_EL_PEDIDO);

        $this->reportar($ana, ['descripcion' => self::DESCRIPCION, 'pedido' => $pedidoDeBeto->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pedido' => 'El pedido va en texto.']);

        $this->assertSame(0, ReporteSoporte::count());
    }

    #[TestDox('perfil-usuario-E13: sin sesión, o sin el rol de usuario, no se guarda ningún reporte')]
    public function test_perfil_usuario_e13_sin_sesion_o_sin_el_rol_de_usuario_no_se_guarda_ningun_reporte(): void
    {
        $sinRol = Usuario::factory()->create();
        $this->pedidoDe($sinRol, $this->plazaDePrueba());

        $this->postJson('/api/soporte/reportes', ['descripcion' => self::DESCRIPCION])->assertUnauthorized();
        $this->reportar($sinRol, ['descripcion' => self::DESCRIPCION])->assertForbidden();

        $this->assertSame(0, ReporteSoporte::count());
    }

    /**
     * La app manda el formulario de reporte con la sesión de la cuenta.
     *
     * @param  array<string, mixed>  $cuerpo
     */
    private function reportar(Usuario $cuenta, array $cuerpo): TestResponse
    {
        return $this->comoCuenta($cuenta)->postJson('/api/soporte/reportes', $cuerpo);
    }
}
