<?php

namespace App\Esquema;

use App\Models\Modelo;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;

/**
 * Compara cada modelo de Eloquent del negocio con la base: que declare su $table y su $primaryKey, que la tabla exista
 * y que su llave primaria sea la de la base (ADR-0016, regla 5; modelo-E16).
 *
 * La tabla se busca con el search_path de la conexión, así que también encuentra las tablas temporales de las
 * pruebas.
 */
final class RevisorDeModelos
{
    private readonly ConnectionInterface $conexion;

    public function __construct(?string $conexion = null)
    {
        $this->conexion = DB::connection($conexion);
    }

    /**
     * Los modelos de app/Models que extienden Modelo.
     *
     * @return list<class-string<Modelo>>
     */
    public static function modelosDeLaAplicacion(): array
    {
        return self::modelosEn(app_path('Models'), 'App\\Models');
    }

    /**
     * Las clases concretas que extienden Modelo en un directorio, con su espacio de nombres PSR-4, en orden
     * alfabético. Deja fuera los traits, las clases abstractas y lo que no es un modelo.
     *
     * @return list<class-string<Modelo>>
     */
    public static function modelosEn(string $directorio, string $espacioDeNombres): array
    {
        $modelos = [];

        foreach (Finder::create()->files()->in($directorio)->name('*.php') as $archivo) {
            $clase = $espacioDeNombres.'\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $archivo->getRelativePathname(),
            );

            if (class_exists($clase)
                && is_subclass_of($clase, Modelo::class)
                && ! (new ReflectionClass($clase))->isAbstract()) {
                $modelos[] = $clase;
            }
        }

        sort($modelos);

        return $modelos;
    }

    /**
     * @param  list<class-string<Modelo>>  $modelos
     * @return list<Violacion>
     */
    public function revisar(array $modelos): array
    {
        $violaciones = [];

        foreach ($modelos as $clase) {
            array_push($violaciones, ...$this->revisarModelo($clase));
        }

        return $violaciones;
    }

    /**
     * @param  class-string<Modelo>  $clase
     * @return list<Violacion>
     */
    private function revisarModelo(string $clase): array
    {
        $modelo = new $clase;
        $tabla = $modelo->getTable();
        $llave = $modelo->getKeyName();
        $violaciones = [];

        if (! $this->declara($clase, 'table')) {
            $violaciones[] = new Violacion($tabla, null, Regla::ModeloTabla, 'declarar $table', modelo: $clase);
        }

        $existe = $this->conexion->scalar('select to_regclass(?) is not null', [$tabla]);

        if (! $existe) {
            $violaciones[] = new Violacion(
                $tabla,
                null,
                Regla::ModeloTabla,
                'una tabla que exista en la base',
                modelo: $clase,
            );
        }

        if (! $this->declara($clase, 'primaryKey')) {
            $violaciones[] = new Violacion(
                $tabla,
                $llave,
                Regla::ModeloLlavePrimaria,
                'declarar $primaryKey',
                modelo: $clase,
            );
        }

        if ($existe) {
            $llaveDeLaBase = $this->llavePrimaria($tabla);

            if ($llaveDeLaBase !== [$llave]) {
                $violaciones[] = new Violacion(
                    $tabla,
                    $llave,
                    Regla::ModeloLlavePrimaria,
                    $llaveDeLaBase === [] ? 'una llave primaria en la base' : implode(', ', $llaveDeLaBase),
                    modelo: $clase,
                );
            }
        }

        return $violaciones;
    }

    /**
     * Si el modelo, o un modelo del negocio del que hereda, declara la propiedad; no cuenta la de Eloquent.
     */
    private function declara(string $clase, string $propiedad): bool
    {
        return (new ReflectionProperty($clase, $propiedad))->getDeclaringClass()->getName() !== Model::class;
    }

    /**
     * Las columnas de la llave primaria de la tabla, en orden.
     *
     * @return list<string>
     */
    private function llavePrimaria(string $tabla): array
    {
        $columnas = $this->conexion->scalar(
            "select array_to_json(array(
                        select a.attname
                          from pg_catalog.pg_constraint con
                         cross join unnest(con.conkey::smallint[]) with ordinality as k(numero, orden)
                          join pg_catalog.pg_attribute a on a.attrelid = con.conrelid and a.attnum = k.numero
                         where con.conrelid = to_regclass(?) and con.contype = 'p'
                         order by k.orden
                    ))",
            [$tabla],
        );

        return json_decode((string) $columnas, true);
    }
}
