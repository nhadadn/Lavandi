<?php

namespace Tests\Feature\Modelo;

use App\Esquema\Regla;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Modelo\Soporte\Direccion;
use Tests\Feature\Modelo\Soporte\DireccionConOtraLlave;
use Tests\Feature\Modelo\Soporte\DireccionSinDeclarar;
use Tests\Feature\Modelo\Soporte\Plaza;
use Tests\Feature\Modelo\Soporte\Zona;
use Tests\TestCase;

/**
 * La clase base de los modelos, App\Models\Modelo, y el identificador público: los modelos de Eloquent declaran su
 * tabla y su llave igual que la base, y la API identifica las filas por un ULID (ADR-0016, regla 5; design.md,
 * decisiones 5, 7 y 8).
 *
 * Los modelos de prueba viven en Soporte/ y usan tablas temporales, que se deshacen con la transacción de la prueba.
 *
 * Escenarios: modelo-E15 y modelo-E16 · Spec: openspec/changes/modelo-de-datos/specs/modelo-de-datos/spec.md
 */
class ModelosTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('modelo-E15: una fila nueva recibe un identificador público ULID de 26 caracteres, distinto en cada fila')]
    public function test_modelo_e15_una_fila_nueva_recibe_un_identificador_publico_ulid_de_26_caracteres_distinto_en_cada_fila(): void
    {
        $this->crearDirecciones();

        $casa = Direccion::create(['alias' => 'Casa']);
        $trabajo = Direccion::create(['alias' => 'Trabajo']);

        foreach ([$casa, $trabajo] as $direccion) {
            $this->assertSame(26, strlen($direccion->id_publico));
            $this->assertTrue(Str::isUlid($direccion->id_publico));
            // La llave interna sigue siendo el bigint autoincremental.
            $this->assertIsInt($direccion->getKey());
        }

        $this->assertNotSame($casa->id_publico, $trabajo->id_publico);
        $this->assertSame(2, DB::table('direcciones')->distinct()->count('id_publico'));
    }

    #[TestDox('modelo-E15: la API encuentra la fila por su identificador público y nunca por el bigint')]
    public function test_modelo_e15_la_api_encuentra_la_fila_por_su_identificador_publico_y_nunca_por_el_bigint(): void
    {
        $this->crearDirecciones();
        $casa = Direccion::create(['alias' => 'Casa']);

        $this->assertSame('id_publico', $casa->getRouteKeyName());
        $this->assertSame($casa->id_publico, $casa->getRouteKey());
        $this->assertTrue($casa->is((new Direccion)->resolveRouteBinding($casa->id_publico)));

        $this->expectException(ModelNotFoundException::class);
        (new Direccion)->resolveRouteBinding((string) $casa->getKey());
    }

    #[TestDox('modelo-E16: los modelos de app/Models que extienden Modelo coinciden con la base')]
    public function test_modelo_e16_los_modelos_de_app_models_que_extienden_modelo_coinciden_con_la_base(): void
    {
        $revisor = new RevisorDeModelos;

        $violaciones = $revisor->revisar(RevisorDeModelos::modelosDeLaAplicacion());

        $this->assertSame([], array_map('strval', $violaciones));
    }

    #[TestDox('modelo-E16: la búsqueda encuentra los modelos que extienden Modelo y deja fuera lo demás')]
    public function test_modelo_e16_la_busqueda_encuentra_los_modelos_que_extienden_modelo_y_deja_fuera_lo_demas(): void
    {
        // Soporte/ también tiene un trait, EsquemaDePrueba, que no es un modelo.
        $modelos = RevisorDeModelos::modelosEn(__DIR__.'/Soporte', 'Tests\\Feature\\Modelo\\Soporte');

        $this->assertSame([
            Direccion::class,
            DireccionConOtraLlave::class,
            DireccionSinDeclarar::class,
            Plaza::class,
            Zona::class,
        ], $modelos);
    }

    #[TestDox('modelo-E16: Direccion declara direcciones e id_direcciones, igual que la base')]
    public function test_modelo_e16_direccion_declara_direcciones_e_id_direcciones_igual_que_la_base(): void
    {
        $this->crearDirecciones();

        $this->assertSame('direcciones', (new Direccion)->getTable());
        $this->assertSame('id_direcciones', (new Direccion)->getKeyName());
        $this->assertSame([], (new RevisorDeModelos)->revisar([Direccion::class]));
    }

    #[TestDox('modelo-E16: un modelo que no coincide con la base no pasa')]
    public function test_modelo_e16_un_modelo_que_no_coincide_con_la_base_no_pasa(): void
    {
        $this->crearDirecciones();

        $violaciones = (new RevisorDeModelos)->revisar([
            DireccionSinDeclarar::class,
            DireccionConOtraLlave::class,
        ]);

        $this->assertEquals([
            new Violacion(
                tabla: 'direccion_sin_declarars',
                columna: null,
                regla: Regla::ModeloTabla,
                esperado: 'declarar $table',
                modelo: DireccionSinDeclarar::class,
            ),
            new Violacion(
                tabla: 'direccion_sin_declarars',
                columna: null,
                regla: Regla::ModeloTabla,
                esperado: 'una tabla que exista en la base',
                modelo: DireccionSinDeclarar::class,
            ),
            new Violacion(
                tabla: 'direccion_sin_declarars',
                columna: 'id',
                regla: Regla::ModeloLlavePrimaria,
                esperado: 'declarar $primaryKey',
                modelo: DireccionSinDeclarar::class,
            ),
            new Violacion(
                tabla: 'direcciones',
                columna: 'id',
                regla: Regla::ModeloLlavePrimaria,
                esperado: 'id_direcciones',
                modelo: DireccionConOtraLlave::class,
            ),
        ], $violaciones);
    }

    #[TestDox('modelo-E16: hasMany encuentra la llave foránea por el nombre de la llave primaria del padre')]
    public function test_modelo_e16_has_many_encuentra_la_llave_foranea_por_el_nombre_de_la_llave_primaria_del_padre(): void
    {
        Schema::create('plazas', function (Blueprint $tabla) {
            $tabla->temporary();
            $tabla->id('id_plazas');
            $tabla->string('nombre');
            $tabla->timestampsTz();
        });
        Schema::create('zonas', function (Blueprint $tabla) {
            $tabla->temporary();
            $tabla->id('id_zonas');
            $tabla->unsignedBigInteger('id_plazas');
            $tabla->string('nombre');
            $tabla->timestampsTz();
        });

        $plaza = Plaza::create(['nombre' => 'A']);
        $plaza->zonas()->create(['nombre' => 'Centro']);
        $plaza->zonas()->create(['nombre' => 'Norte']);

        $this->assertSame('id_plazas', $plaza->getForeignKey());
        $this->assertSame('id_plazas', $plaza->zonas()->getForeignKeyName());
        $this->assertSame(['Centro', 'Norte'], $plaza->zonas()->orderBy('nombre')->pluck('nombre')->all());
        $this->assertTrue($plaza->is(Zona::where('nombre', 'Norte')->first()->plaza));
        $this->assertSame([], (new RevisorDeModelos)->revisar([Plaza::class, Zona::class]));
    }

    /**
     * La tabla temporal de Direccion, como la escribiría la migración del cambio direcciones.
     */
    private function crearDirecciones(): void
    {
        Schema::create('direcciones', function (Blueprint $tabla) {
            $tabla->temporary();
            $tabla->id('id_direcciones');
            $tabla->ulid('id_publico')->unique();
            $tabla->string('alias');
            $tabla->timestampsTz();
        });
    }
}
