<?php

namespace Tests\Feature\AltaLavanderias;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\Lavanderia;
use App\Models\Municipio;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;
use Tests\TestCase;

/**
 * La migración que completa lavanderias para el alta real: estado con su valor por omisión, aprobada_en llena en las
 * filas que ya existían y el municipio del catálogo, que la geocodificación de la dirección necesita
 * (openspec/changes/alta-de-lavanderias/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md;
 * ADR-0016, regla 9).
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por el modelo, para probar lo que hace la base.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre lavanderias · Tarea 0.1 · Spec:
 * openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class MigracionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = '2026_11_22_100000_completar_lavanderias_para_el_alta.php';

    /** SQLSTATE de PostgreSQL cuando una columna obligatoria llega vacía. */
    private const VACIO_NO_PERMITIDO = '23502';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    #[TestDox('modelo-E01 a modelo-E17: lavanderias completa sigue cumpliendo la prueba de esquema, y su modelo también')]
    public function test_modelo_e01_a_e17_lavanderias_completa_sigue_cumpliendo_la_prueba_de_esquema(): void
    {
        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->tabla === 'lavanderias',
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([Lavanderia::class])));

        // El modelo no cambia su tabla ni su llave.
        $this->assertSame(['lavanderias', 'id_lavanderias'], [(new Lavanderia)->getTable(), (new Lavanderia)->getKeyName()]);
    }

    #[TestDox('alta-lavanderias 0.1: estado es obligatorio y, si no se dice, vale activa')]
    public function test_alta_lavanderias_0_1_estado_es_obligatorio_y_por_omision_vale_activa(): void
    {
        $plaza = $this->insertarPlaza();
        $sinEstado = $this->insertarLavanderia($plaza, sinEstado: true);

        $this->assertSame('activa', DB::table('lavanderias')->where('id_lavanderias', $sinEstado)->value('estado'));
        $this->assertFalse($this->admiteVacio('estado'));
        $this->assertRechazada(fn () => $this->insertarLavanderia($plaza, ['estado' => null]), self::VACIO_NO_PERMITIDO);
    }

    #[TestDox('alta-lavanderias 0.1: estado solo admite pendiente, activa, suspendida y baja')]
    public function test_alta_lavanderias_0_1_estado_solo_admite_los_cuatro_valores(): void
    {
        $plaza = $this->insertarPlaza();

        foreach (['pendiente', 'activa', 'suspendida', 'baja'] as $estado) {
            $this->insertarLavanderia($plaza, ['estado' => $estado]);
        }

        $this->assertRechazada(fn () => $this->insertarLavanderia($plaza, ['estado' => 'aprobada']), self::CHECK_INCUMPLIDO);
        $this->assertSame(4, DB::table('lavanderias')->count());
    }

    #[TestDox('alta-lavanderias 0.1: aprobada_en es un instante opcional')]
    public function test_alta_lavanderias_0_1_aprobada_en_es_un_instante_opcional(): void
    {
        $this->assertSame('timestamp(0) with time zone', $this->tipo('aprobada_en'));
        $this->assertTrue($this->admiteVacio('aprobada_en'));
    }

    #[TestDox('alta-lavanderias 0.1: la migración llena aprobada_en con su hora en las que ya existían, salvo en las pendientes')]
    public function test_alta_lavanderias_0_1_la_migracion_llena_aprobada_en_en_las_que_ya_existian_salvo_en_las_pendientes(): void
    {
        $plaza = $this->insertarPlaza();
        $activa = $this->insertarLavanderia($plaza, ['estado' => 'activa']);
        $suspendida = $this->insertarLavanderia($plaza, ['estado' => 'suspendida']);
        $deBaja = $this->insertarLavanderia($plaza, ['estado' => 'baja']);
        $pendiente = $this->insertarLavanderia($plaza, ['estado' => 'pendiente']);
        $yaAprobada = $this->insertarLavanderia($plaza, ['estado' => 'activa', 'aprobada_en' => '2026-11-01 12:00:00+00']);

        $this->travelTo(CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC'));
        $this->migracion()->llenarAprobadaEn();

        $aprobadas = DB::table('lavanderias')->pluck('aprobada_en', 'id_lavanderias')
            ->map(fn (?string $instante): ?string => $instante === null ? null : CarbonImmutable::parse($instante)->utc()->toIso8601ZuluString());

        // Las que ya estaban dadas de alta, aunque hoy estén suspendidas o de baja, reciben la hora en que corre.
        foreach ([$activa, $suspendida, $deBaja] as $lavanderia) {
            $this->assertSame('2026-11-22T10:00:00Z', $aprobadas[$lavanderia]);
        }

        // La pendiente nunca tuvo alta, y la que ya tenía fecha no se toca.
        $this->assertNull($aprobadas[$pendiente]);
        $this->assertSame('2026-11-01T12:00:00Z', $aprobadas[$yaAprobada]);
    }

    #[TestDox('alta-lavanderias 0.1: el municipio de la dirección es una llave opcional al catálogo, con su índice, que no deja borrar el municipio')]
    public function test_alta_lavanderias_0_1_el_municipio_es_una_llave_opcional_al_catalogo(): void
    {
        $this->assertTrue($this->admiteVacio('id_municipios'));
        $this->assertContains(
            [['id_municipios'], 'municipios', ['id_municipios'], 'restrict'],
            array_map(
                fn (array $llave): array => [$llave['columns'], $llave['foreign_table'], $llave['foreign_columns'], strtolower($llave['on_delete'])],
                Schema::getForeignKeys('lavanderias'),
            ),
        );
        $this->assertContains(['id_municipios'], array_column(Schema::getIndexes('lavanderias'), 'columns'));

        $municipio = CatalogoDePrueba::municipio();
        $plaza = $this->insertarPlaza();
        $this->insertarLavanderia($plaza, ['id_municipios' => $municipio->getKey()]);

        $this->assertRechazada(fn () => DB::table('municipios')->where('id_municipios', $municipio->getKey())->delete(), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => $this->insertarLavanderia($plaza, ['id_municipios' => 999_999]), self::LLAVE_FORANEA_ROTA);
    }

    #[TestDox('alta-lavanderias 0.1: el modelo Lavanderia recibe el estado, aprobada_en y el municipio, con su relación')]
    public function test_alta_lavanderias_0_1_el_modelo_recibe_el_estado_aprobada_en_y_el_municipio(): void
    {
        $municipio = CatalogoDePrueba::municipio();

        $lavanderia = Lavanderia::factory()->create([
            'estado' => Lavanderia::SUSPENDIDA,
            'aprobada_en' => '2026-11-01 12:00:00+00',
            'id_municipios' => $municipio->getKey(),
        ])->fresh();

        $this->assertSame(Lavanderia::SUSPENDIDA, $lavanderia->estado);
        $this->assertSame('2026-11-01T12:00:00Z', $lavanderia->aprobada_en->utc()->toIso8601ZuluString());
        $this->assertTrue($municipio->is($lavanderia->municipio));
        $this->assertSame('id_municipios', $lavanderia->municipio()->getForeignKeyName());
    }

    /**
     * La migración de este cambio, para volver a correr su llenado de aprobada_en sobre filas sembradas en la prueba.
     */
    private function migracion(): object
    {
        return require database_path('migrations/'.self::MIGRACION);
    }

    private function insertarPlaza(): int
    {
        return DB::table('plazas')->insertGetId([
            'nombre' => 'Plaza de prueba 1',
            'zona_horaria' => 'America/Mexico_City',
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_plazas');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function insertarLavanderia(int $plaza, array $datos = [], bool $sinEstado = false): int
    {
        $usuario = DB::table('usuarios')->insertGetId([
            'id_publico' => (string) Str::ulid(),
            'correo' => Str::lower((string) Str::ulid()).'@ejemplo.com',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuarios');

        $fila = [
            'id_publico' => (string) Str::ulid(),
            'id_plazas' => $plaza,
            'id_usuarios' => $usuario,
            'nombre' => 'Lavandería de prueba',
            'calle' => 'Calle de prueba',
            'numero' => '1',
            'colonia' => 'Colonia de prueba',
            'codigo_postal' => '00000',
            'ubicacion' => DB::raw("ST_GeogFromText('SRID=4326;POINT(-119.5 10.5)')"),
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ];

        if ($sinEstado) {
            unset($fila['estado']);
        }

        return DB::table('lavanderias')->insertGetId($fila, 'id_lavanderias');
    }

    private function tipo(string $columna): ?string
    {
        return DB::scalar(
            'select format_type(a.atttypid, a.atttypmod)
               from pg_catalog.pg_attribute a
              where a.attrelid = to_regclass(?) and a.attname = ? and not a.attisdropped',
            ['lavanderias', $columna],
        );
    }

    private function admiteVacio(string $columna): bool
    {
        $columnas = array_column(Schema::getColumns('lavanderias'), 'nullable', 'name');

        $this->assertArrayHasKey($columna, $columnas, "Falta la columna lavanderias.{$columna}.");

        return $columnas[$columna];
    }

    /**
     * La operación falla con el SQLSTATE dado. Corre en un savepoint, para que el error no aborte la transacción de la
     * prueba.
     */
    private function assertRechazada(callable $operacion, string $sqlstate): void
    {
        try {
            DB::transaction(fn () => $operacion());
        } catch (QueryException $error) {
            $this->assertSame($sqlstate, $error->getCode(), $error->getMessage());

            return;
        }

        $this->fail("La base aceptó una operación que debía rechazar con {$sqlstate}.");
    }
}
