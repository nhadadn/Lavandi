<?php

namespace Tests\Feature\Modelo\Soporte;

use App\Esquema\Regla;
use App\Esquema\RevisorDeEsquema;
use App\Esquema\Violacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Un esquema de prueba, prueba_modelo, para los escenarios negativos de la prueba de esquema.
 *
 * RefreshDatabase migra la base desde cero una vez y abre una transacción por prueba. El esquema nace dentro de esa
 * transacción y se deshace con ella, así que las tablas que rompen una regla nunca ensucian la base
 * (openspec/changes/modelo-de-datos/design.md, decisión 1). Si una prueba anterior lo hubiera dejado, crear el
 * esquema fallaría.
 *
 * Vive fuera de public para no chocar con las tablas reales que crearán los cambios siguientes, como zonas o pedidos.
 * Dentro de la transacción, el search_path pone primero prueba_modelo: las sentencias de las pruebas escriben los
 * nombres sin esquema, como en una migración.
 *
 * Una tabla de prueba que cumple las reglas lleva id_plazas con su llave a plazas y su índice (plazas-E09). Si la
 * prueba no crea su propia tabla plazas en prueba_modelo, references plazas apunta a la de public, que ya migró.
 */
trait EsquemaDePrueba
{
    use RefreshDatabase;

    protected const ESQUEMA_DE_PRUEBA = 'prueba_modelo';

    protected function setUpEsquemaDePrueba(): void
    {
        DB::statement('create schema '.self::ESQUEMA_DE_PRUEBA);
        DB::statement('set local search_path to '.self::ESQUEMA_DE_PRUEBA.', public');
    }

    /**
     * Crea las tablas de prueba con SQL de PostgreSQL, varias sentencias a la vez.
     */
    protected function crear(string $sql): void
    {
        DB::unprepared($sql);
    }

    /**
     * Las violaciones de las tablas de prueba.
     *
     * @return list<Violacion>
     */
    protected function violacionesDePrueba(): array
    {
        return (new RevisorDeEsquema(self::ESQUEMA_DE_PRUEBA))->revisar();
    }

    /**
     * Las violaciones del esquema real, public, de las reglas dadas, como texto para que el fallo las muestre.
     *
     * @param  list<Regla>  $reglas
     * @return list<string>
     */
    protected function violacionesDelEsquemaReal(array $reglas): array
    {
        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => in_array($violacion->regla, $reglas, true),
        );

        return array_values(array_map(fn (Violacion $violacion): string => (string) $violacion, $violaciones));
    }
}
