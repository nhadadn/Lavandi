<?php

namespace Tests\Feature\PerfilLavanderia;

use App\Models\Lavanderia;
use App\Models\Rol;
use App\Models\TipoServicio;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Seeders\LavanderiasDePruebaSeeder;
use Database\Seeders\PlazasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El perfil de una lavandería activa: nombre, descripción, fotos, servicios con su precio y su unidad, calificación
 * promedio, número de calificaciones y reseñas sin datos de quien calificó, por GET /api/lavanderias/{id_publico}, con
 * sesión y por el componente de autorización (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5;
 * NSV-2026-001-v8.pdf:10:Cláusula X; ADR-0003).
 *
 * Que cada foto llegue con su URL firmada que vence, y que sin firma no se lea, lo prueba FotosTest (tarea 2.1,
 * perfil-lavanderia-E03 y E04). Aquí solo se cuentan: ninguna ruta del almacenamiento sale como campo de la API.
 *
 * Escenarios: perfil-lavanderia-E01, E02, E05, E07 y E08 · Spec:
 * openspec/changes/perfil-de-lavanderia/specs/perfil-lavanderia/spec.md
 */
class PerfilTest extends TestCase
{
    use LavanderiasDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    #[TestDox('perfil-lavanderia-E01: el perfil trae nombre, descripción, sus dos servicios con precio, sus reseñas, el promedio y el número de calificaciones')]
    public function test_perfil_lavanderia_e01_el_perfil_completo(): void
    {
        $plaza = $this->plazaDePrueba();
        $lavanderia = Lavanderia::factory()->en($plaza)->create([
            'nombre' => 'Lavandería de prueba Centro',
            'descripcion' => 'Descripción de prueba de la lavandería.',
        ]);
        foreach ([1, 2, 3] as $orden) {
            $lavanderia->fotos()->create(['ruta' => "lavanderias/prueba/foto-{$orden}.jpg", 'orden' => $orden]);
        }
        $this->servicioDePrueba($lavanderia, TipoServicio::ESTANDAR, '10.00');
        $this->servicioDePrueba($lavanderia, TipoServicio::PLANCHADO, '15.50');
        $this->calificar($lavanderia, [5, 5, 4, 3]);

        // Otra lavandería, con sus propias calificaciones, que no se mezclan.
        $otra = Lavanderia::factory()->en($plaza)->create();
        $this->calificar($otra, [1, 1]);

        $respuesta = $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk();

        $respuesta->assertJsonPath('lavanderia.id_publico', $lavanderia->id_publico);
        $respuesta->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba Centro');
        $respuesta->assertJsonPath('lavanderia.descripcion', 'Descripción de prueba de la lavandería.');
        $respuesta->assertJsonPath('lavanderia.servicios', [
            ['codigo' => 'estandar', 'nombre' => 'Lavado estándar', 'precio' => '10.00', 'unidad' => ['codigo' => 'kg', 'nombre' => 'kilo']],
            ['codigo' => 'planchado', 'nombre' => 'Lavado con planchado', 'precio' => '15.50', 'unidad' => ['codigo' => 'kg', 'nombre' => 'kilo']],
        ]);
        // (5 + 5 + 4 + 3) / 4.
        $respuesta->assertJsonPath('lavanderia.calificacion_promedio', 4.25);
        $respuesta->assertJsonPath('lavanderia.numero_de_calificaciones', 4);
        $respuesta->assertJsonCount(4, 'lavanderia.resenas');
        $this->assertEqualsCanonicalizing([5, 5, 4, 3], array_column($respuesta->json('lavanderia.resenas'), 'estrellas'));

        // Sus tres fotos, cada una solo con su URL firmada y su vencimiento (perfil-lavanderia-E03, FotosTest). La
        // ruta no sale como campo: fuera de las URLs firmadas, la respuesta no la nombra.
        $fotos = $respuesta->json('lavanderia.fotos');
        $this->assertCount(3, $fotos);
        foreach ($fotos as $foto) {
            $this->assertSame(['url', 'vence_en'], array_keys($foto));
        }
        $sinFotos = $respuesta->json('lavanderia');
        unset($sinFotos['fotos']);
        $this->assertStringNotContainsString('lavanderias/prueba/foto-', json_encode($sinFotos, JSON_UNESCAPED_SLASHES));
        // Ni la llave interna.
        $this->assertArrayNotHasKey('id_lavanderias', $respuesta->json('lavanderia'));
    }

    #[TestDox('perfil-lavanderia-E01: las reseñas van de la más reciente a la más antigua')]
    public function test_perfil_lavanderia_e01_las_resenas_van_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();

        foreach (['2026-11-01 10:00:00', '2026-11-03 10:00:00', '2026-11-02 10:00:00'] as $dia => $fecha) {
            $this->travelTo(CarbonImmutable::parse($fecha, 'UTC'));
            $this->calificar($lavanderia, [$dia + 3], comentario: "Reseña del {$fecha}");
        }
        $this->travelBack();

        $respuesta = $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk();

        $this->assertSame(
            ['2026-11-03T10:00:00Z', '2026-11-02T10:00:00Z', '2026-11-01T10:00:00Z'],
            array_column($respuesta->json('lavanderia.resenas'), 'fecha'),
        );
    }

    #[TestDox('perfil-lavanderia-E02: una lavandería suspendida o dada de baja no tiene perfil, y no se devuelven sus datos')]
    public function test_perfil_lavanderia_e02_suspendida_o_dada_de_baja_sin_perfil(): void
    {
        $plaza = $this->plazaDePrueba();
        $suspendida = Lavanderia::factory()->en($plaza)->suspendida()->create(['nombre' => 'Lavandería de prueba suspendida']);
        $deBaja = Lavanderia::factory()->en($plaza)->deBaja()->create(['nombre' => 'Lavandería de prueba dada de baja']);

        foreach ([$suspendida, $deBaja] as $lavanderia) {
            $this->servicioDePrueba($lavanderia, TipoServicio::ESTANDAR, '10.00');
            $this->calificar($lavanderia, [5], comentario: 'Comentario de prueba');

            $this->assertSinPerfil($this->pedirComoUsuario($this->direccion($lavanderia)), $lavanderia);
        }

        // La misma petición, a una activa de la misma plaza, sí trae su perfil: el 404 no es de la ruta.
        $activa = Lavanderia::factory()->en($plaza)->create();
        $this->pedirComoUsuario($this->direccion($activa))->assertOk()->assertJsonPath('lavanderia.id_publico', $activa->id_publico);
    }

    #[TestDox('perfil-lavanderia-E02: tampoco la de una plaza que no está activa ni la borrada; un identificador que no existe responde igual')]
    public function test_perfil_lavanderia_e02_tampoco_la_de_una_plaza_no_activa_ni_la_borrada(): void
    {
        // "Activa" es lo mismo que en lavanderias-cercanas (design.md, decisión 3): no suspendida ni dada de baja, sin
        // borrado suave y de una plaza activa.
        $deUnaPlazaNoActiva = Lavanderia::factory()
            ->en($this->plazaDePrueba('Plaza de prueba no activa', activa: false))
            ->create(['nombre' => 'Lavandería de prueba de una plaza no activa']);
        $borrada = Lavanderia::factory()->en($this->plazaDePrueba())->create(['nombre' => 'Lavandería de prueba borrada']);
        $borrada->delete();

        foreach ([$deUnaPlazaNoActiva, $borrada] as $lavanderia) {
            $this->assertSinPerfil($this->pedirComoUsuario($this->direccion($lavanderia)), $lavanderia);
        }

        $this->pedirComoUsuario('/api/lavanderias/'.Str::ulid())
            ->assertNotFound()
            ->assertJsonMissingPath('lavanderia');

        // Una activa de una plaza activa sí trae su perfil: el 404 no es de la ruta.
        $activa = Lavanderia::factory()->en($this->plazaDePrueba('Plaza de prueba activa'))->create();
        $this->pedirComoUsuario($this->direccion($activa))->assertOk()->assertJsonPath('lavanderia.id_publico', $activa->id_publico);
    }

    #[TestDox('perfil-lavanderia-E02: sin sesión, el perfil de una lavandería activa se rechaza sin datos')]
    public function test_perfil_lavanderia_e02_sin_sesion_el_perfil_se_rechaza_sin_datos(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create(['nombre' => 'Lavandería de prueba activa']);

        $respuesta = $this->pedirSinSesion($this->direccion($lavanderia));

        $respuesta->assertUnauthorized();
        $respuesta->assertJsonMissingPath('lavanderia');
        $this->assertStringNotContainsString('Lavandería de prueba activa', $respuesta->getContent());
    }

    #[TestDox('perfil-lavanderia-E02: una cuenta sin el rol de usuario no pasa el componente de autorización y no ve el perfil')]
    public function test_perfil_lavanderia_e02_una_cuenta_sin_el_rol_de_usuario_no_ve_el_perfil(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create(['nombre' => 'Lavandería de prueba activa']);

        // La cuenta del socio de otra lavandería, y una sin rol.
        foreach ([$lavanderia->administrador, Usuario::factory()->create()] as $cuenta) {
            $respuesta = $this->pedirComoUsuario($this->direccion($lavanderia), cuenta: $cuenta);

            $respuesta->assertForbidden();
            $respuesta->assertJsonMissingPath('lavanderia');
            $this->assertStringNotContainsString('Lavandería de prueba activa', $respuesta->getContent());
        }
    }

    #[TestDox('perfil-lavanderia-E05: solo los servicios que ofrece, cada uno con su precio de prueba y la unidad kilo')]
    public function test_perfil_lavanderia_e05_solo_los_servicios_que_ofrece_con_precio_por_kilo(): void
    {
        $plaza = $this->plazaDePrueba();
        $lavanderia = Lavanderia::factory()->en($plaza)->create();
        $this->servicioDePrueba($lavanderia, TipoServicio::ESTANDAR, '12.00');
        $this->servicioDePrueba($lavanderia, TipoServicio::PLANCHADO, '18.00');
        // Uno que ya no ofrece, y uno de otra lavandería: no salen.
        $this->servicioDePrueba($lavanderia, TipoServicio::SECO, '30.00', activo: false);
        $this->servicioDePrueba(Lavanderia::factory()->en($plaza)->create(), TipoServicio::SECO, '40.00');

        $servicios = $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk()->json('lavanderia.servicios');

        $this->assertSame(['estandar', 'planchado'], array_column($servicios, 'codigo'));
        $this->assertSame(['12.00', '18.00'], array_column($servicios, 'precio'));
        $this->assertSame(
            [['codigo' => 'kg', 'nombre' => 'kilo'], ['codigo' => 'kg', 'nombre' => 'kilo']],
            array_column($servicios, 'unidad'),
        );
    }

    #[TestDox('perfil-lavanderia-E05: el sembrado de prueba deja cada lavandería de prueba con sus servicios por kilo')]
    public function test_perfil_lavanderia_e05_el_sembrado_de_prueba_deja_cada_lavanderia_con_sus_servicios_por_kilo(): void
    {
        $this->seed([PlazasDePruebaSeeder::class, LavanderiasDePruebaSeeder::class]);

        $activas = Lavanderia::where('estado', Lavanderia::ACTIVA)->get();
        $this->assertNotEmpty($activas);

        foreach ($activas as $lavanderia) {
            $servicios = $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk()->json('lavanderia.servicios');

            $this->assertNotEmpty($servicios, $lavanderia->nombre);
            foreach ($servicios as $servicio) {
                // El lavado en seco espera la unidad de DD-010: no se siembra.
                $this->assertContains($servicio['codigo'], [TipoServicio::ESTANDAR, TipoServicio::PLANCHADO]);
                $this->assertSame(['codigo' => 'kg', 'nombre' => 'kilo'], $servicio['unidad']);
                $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $servicio['precio']);
            }
        }
    }

    #[TestDox('perfil-lavanderia-E07: sin calificaciones, el promedio llega vacío y no en cero, y el número es 0')]
    public function test_perfil_lavanderia_e07_sin_calificaciones_el_promedio_llega_vacio(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();

        $respuesta = $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk();

        $this->assertArrayHasKey('calificacion_promedio', $respuesta->json('lavanderia'));
        $this->assertNull($respuesta->json('lavanderia.calificacion_promedio'));
        $this->assertSame(0, $respuesta->json('lavanderia.numero_de_calificaciones'));
        $this->assertSame([], $respuesta->json('lavanderia.resenas'));
    }

    #[TestDox('perfil-lavanderia-E08: cada reseña trae sus estrellas, su fecha y su comentario si lo tiene, y nada de quien calificó')]
    public function test_perfil_lavanderia_e08_las_resenas_no_dicen_quien_califico(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();
        $conComentario = Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'quien-comenta@ejemplo.com']);
        $conComentario->forceFill(['nombre' => 'Nombre de prueba', 'telefono' => '6180000000', 'foto_ruta' => 'usuarios/foto.jpg'])->save();
        $sinComentario = Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'quien-no-comenta@ejemplo.com']);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 15:30:00', 'UTC'));
        $lavanderia->calificaciones()->create([
            'id_usuarios' => $conComentario->getKey(),
            'estrellas' => 5,
            'comentario' => 'Comentario de prueba.',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-19 09:00:00', 'UTC'));
        $lavanderia->calificaciones()->create(['id_usuarios' => $sinComentario->getKey(), 'estrellas' => 4]);
        $this->travelBack();

        $respuesta = $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk();

        $this->assertSame([
            ['estrellas' => 5, 'comentario' => 'Comentario de prueba.', 'fecha' => '2026-11-20T15:30:00Z'],
            ['estrellas' => 4, 'comentario' => null, 'fecha' => '2026-11-19T09:00:00Z'],
        ], $respuesta->json('lavanderia.resenas'));

        $contenido = $respuesta->getContent();
        foreach ([$conComentario, $sinComentario] as $cuenta) {
            $this->assertStringNotContainsString($cuenta->correo, $contenido);
            $this->assertStringNotContainsString($cuenta->id_publico, $contenido);
        }
        foreach (['Nombre de prueba', '6180000000', 'usuarios/foto.jpg', 'id_usuarios', 'id_calificaciones'] as $dato) {
            $this->assertStringNotContainsString($dato, $contenido);
        }
    }

    private function direccion(Lavanderia $lavanderia): string
    {
        return '/api/lavanderias/'.$lavanderia->id_publico;
    }

    /**
     * El servidor responde que no existe, sin el perfil y sin ningún dato de la lavandería.
     */
    private function assertSinPerfil(TestResponse $respuesta, Lavanderia $lavanderia): void
    {
        $respuesta->assertNotFound();
        $respuesta->assertJsonMissingPath('lavanderia');
        $this->assertStringNotContainsString($lavanderia->nombre, $respuesta->getContent());
        $this->assertStringNotContainsString('Comentario de prueba', $respuesta->getContent());
    }
}
