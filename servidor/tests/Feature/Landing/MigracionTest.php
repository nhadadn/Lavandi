<?php

namespace Tests\Feature\Landing;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\Testimonio;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * La tabla testimonios y su modelo de Eloquent, Testimonio (openspec/changes/landing/design.md, "Tablas y migración";
 * vault/arquitectura/modelo-de-datos.md, "Landing"; ADR-0011; ADR-0016).
 *
 * Los testimonios de la portada se editan desde el panel, con su interruptor de activo y su orden (NSV-2026-001-v8.pdf:15:
 * ANEXO A, Sección A.2, sección 8). No llevan plaza: la portada es una para todo el sitio, y la tabla entra a la lista
 * sin_plaza de config/modelo.php (modelo-E05). No lleva el trigger impedir_cambio(): es contenido, no historial.
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por el modelo, para probar lo que hace la base.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre la tabla, y la base de landing-E15, E16 y E19 · Spec:
 * openspec/changes/landing/specs/landing/spec.md · Tarea 4.0
 */
class MigracionTest extends TestCase
{
    use RefreshDatabase;

    /** SQLSTATE de PostgreSQL cuando una columna no nula queda vacía. */
    private const VALOR_VACIO = '23502';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    private const INSTANTE = 'timestamp(0) with time zone';

    private function tipo(string $tabla, string $columna): string
    {
        return (string) DB::scalar(
            'select format_type(atttypid, atttypmod) from pg_attribute where attrelid = ?::regclass and attname = ?',
            [$tabla, $columna],
        );
    }

    private function sqlstate(callable $insertar): ?string
    {
        try {
            DB::transaction(fn () => $insertar());
        } catch (QueryException $error) {
            return (string) $error->getCode();
        }

        return null;
    }

    #[TestDox('modelo-E01 a modelo-E17: la tabla testimonios existe y cumple la prueba de esquema, y su modelo también')]
    public function test_modelo_e01_a_e17_la_tabla_existe_y_cumple_la_prueba_de_esquema(): void
    {
        $this->assertTrue(Schema::hasTable('testimonios'));

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->tabla === 'testimonios',
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([Testimonio::class])));
        $this->assertSame(['testimonios', 'id_testimonios'], [(new Testimonio)->getTable(), (new Testimonio)->getKeyName()]);
    }

    #[TestDox('modelo-E05: testimonios no lleva plaza: está en la lista sin_plaza, como decisión visible')]
    public function test_modelo_e05_testimonios_esta_en_la_lista_sin_plaza(): void
    {
        $this->assertContains('testimonios', config('modelo.sin_plaza'));
        $this->assertNotContains('testimonios', config('modelo.plaza_opcional'));
        $this->assertFalse(Schema::hasColumn('testimonios', 'id_plazas'));
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden, con sus tipos')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos(): void
    {
        $this->assertSame(
            ['id_testimonios', 'autor', 'texto', 'activo', 'orden', 'created_at', 'updated_at'],
            Schema::getColumnListing('testimonios'),
        );

        $this->assertSame('bigint', $this->tipo('testimonios', 'id_testimonios'));
        $this->assertSame('character varying(255)', $this->tipo('testimonios', 'autor'));
        $this->assertSame('text', $this->tipo('testimonios', 'texto'));
        $this->assertSame('boolean', $this->tipo('testimonios', 'activo'));
        $this->assertSame('smallint', $this->tipo('testimonios', 'orden'));
        $this->assertSame(self::INSTANTE, $this->tipo('testimonios', 'created_at'));
        $this->assertSame(self::INSTANTE, $this->tipo('testimonios', 'updated_at'));
    }

    #[TestDox('landing-E15 y landing-E16: un testimonio nuevo nace inactivo y al principio del orden, hasta que el panel lo active')]
    public function test_landing_e15_un_testimonio_nuevo_nace_inactivo(): void
    {
        DB::table('testimonios')->insert(['autor' => 'Persona de prueba', 'texto' => 'Opinión de prueba.']);

        $fila = DB::table('testimonios')->sole();
        $this->assertFalse((bool) $fila->activo);
        $this->assertSame(0, (int) $fila->orden);
    }

    #[TestDox('modelo-E07: la base rechaza un testimonio sin autor o sin texto, o con uno en blanco, y un orden negativo')]
    public function test_modelo_e07_la_base_rechaza_un_testimonio_incompleto(): void
    {
        $completo = ['autor' => 'Persona de prueba', 'texto' => 'Opinión de prueba.'];

        $this->assertSame(self::VALOR_VACIO, $this->sqlstate(fn () => DB::table('testimonios')->insert(['texto' => 'Opinión de prueba.'])));
        $this->assertSame(self::VALOR_VACIO, $this->sqlstate(fn () => DB::table('testimonios')->insert(['autor' => 'Persona de prueba'])));
        $this->assertSame(self::CHECK_INCUMPLIDO, $this->sqlstate(fn () => DB::table('testimonios')->insert([...$completo, 'autor' => '   '])));
        $this->assertSame(self::CHECK_INCUMPLIDO, $this->sqlstate(fn () => DB::table('testimonios')->insert([...$completo, 'texto' => ''])));
        $this->assertSame(self::CHECK_INCUMPLIDO, $this->sqlstate(fn () => DB::table('testimonios')->insert([...$completo, 'orden' => -1])));

        $this->assertNull($this->sqlstate(fn () => DB::table('testimonios')->insert($completo)));
        $this->assertSame(1, DB::table('testimonios')->count());
    }
}
