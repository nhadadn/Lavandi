<?php

namespace App\Direcciones;

use App\Models\Colonia;
use App\Models\EntidadFederativa;
use App\Models\Municipio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carga el catálogo del territorio desde los archivos del Catálogo Nacional de Códigos Postales de Correos de México
 * (SEPOMEX): los estados, los municipios y las colonias de config/territorio.php, y nada más (direcciones-E16;
 * NSV-2026-001-v8.pdf:4:Cláusula I; openspec/changes/direcciones/design.md, "La carga del catálogo").
 *
 * El formato (grado E, verificado contra los archivos descargados el 2026-11-21): texto separado por |; la primera
 * línea es la nota de Correos de México y la segunda, los nombres de las columnas; la codificación es ISO-8859-1. Una
 * fila por asentamiento.
 *
 * - Busca cada columna por su nombre, no por su posición. Si falta una, no carga nada y dice cuál.
 * - Convierte el texto a UTF-8. Un archivo que ya está en UTF-8 se lee como está, para no convertirlo dos veces.
 * - Toma de cada estado del territorio los municipios de la configuración: todos ('*') o los de su lista. Las demás
 *   filas quedan fuera y se cuentan.
 * - Compara el nombre del estado y el de cada municipio de una lista con el de la configuración, sin acentos ni
 *   mayúsculas, y exige que vengan todos los de la lista: una clave equivocada no carga otro municipio en silencio.
 * - Lee y revisa todos los archivos antes de escribir, y escribe en una transacción: si algo falla, no cambia nada.
 * - Actualiza o crea los estados y los municipios por su código, y reemplaza las colonias de los municipios que trajo
 *   el archivo. Un municipio nunca se borra, porque las direcciones lo apuntan; correrla otra vez con los mismos
 *   archivos deja el catálogo igual.
 */
final class CargaDelCatalogo
{
    /** Las columnas del archivo que usa la carga. */
    public const COLUMNAS = ['d_codigo', 'd_asenta', 'd_tipo_asenta', 'D_mnpio', 'd_estado', 'c_estado', 'c_mnpio'];

    /** La configuración que pide todos los municipios del estado. */
    private const TODOS = '*';

    private const CONFIGURACION = 'config/territorio.php';

    private const FILAS_POR_INSERCION = 500;

    /**
     * @param  list<string>  $archivos  Las rutas de los archivos, uno por estado o el de todo el país.
     *
     * @throws CatalogoNoValido
     */
    public function cargar(array $archivos): ResumenDeLaCarga
    {
        $territorio = $this->territorio();
        /** @var array<string, string> $estados El nombre de cada estado del archivo, por su código. */
        $estados = [];
        /** @var array<string, array{estado: string, nombre: string}> $municipios Por su código de cinco números. */
        $municipios = [];
        /** @var list<array{municipio: string, codigo_postal: string, nombre: string, tipo: string}> $colonias */
        $colonias = [];
        $primerasLineas = [];
        $fuera = 0;

        foreach ($archivos as $archivo) {
            [$primeraLinea, $filas] = $this->leer($archivo);
            $primerasLineas[basename($archivo)] = $primeraLinea;

            foreach ($filas as $linea => $fila) {
                $configuracion = $territorio[$fila['c_estado']] ?? null;
                $lista = $configuracion['municipios'] ?? null;

                if ($configuracion === null || ($lista !== self::TODOS && ! isset($lista[$fila['c_mnpio']]))) {
                    $fuera++;

                    continue;
                }

                $this->revisarFila($archivo, $linea, $fila);
                $estado = $fila['c_estado'];
                $municipio = $estado.$fila['c_mnpio'];

                $this->compararNombre('El estado', $estado, $fila['d_estado'], $configuracion['nombre']);

                if ($lista !== self::TODOS) {
                    $this->compararNombre('El municipio', $municipio, $fila['D_mnpio'], $lista[$fila['c_mnpio']]);
                }

                $estados[$estado] ??= $fila['d_estado'];
                $municipios[$municipio] ??= ['estado' => $estado, 'nombre' => $fila['D_mnpio']];
                $colonias[] = [
                    'municipio' => $municipio,
                    'codigo_postal' => $fila['d_codigo'],
                    'nombre' => $fila['d_asenta'],
                    'tipo' => $fila['d_tipo_asenta'],
                ];
            }
        }

        $this->exigirLosMunicipiosDeLaConfiguracion($territorio, $estados, $municipios);

        DB::transaction(fn () => $this->guardar($estados, $municipios, $colonias));

        return new ResumenDeLaCarga(
            porEstado: $this->contarPorEstado($territorio, $estados, $municipios, $colonias),
            primerasLineas: $primerasLineas,
            fueraDelTerritorio: $fuera,
            estadosQueNoVinieron: array_map(
                fn (array $configuracion): string => $configuracion['nombre'],
                array_diff_key($territorio, $estados),
            ),
        );
    }

    /**
     * El territorio de la configuración, con las claves como texto: '05' y '035' llevan su cero, y PHP convierte '10'
     * en un número.
     *
     * @return array<string, array{nombre: string, municipios: string|array<string, string>}>
     */
    private function territorio(): array
    {
        $territorio = [];

        foreach ((array) config('territorio.estados', []) as $codigo => $estado) {
            $municipios = $estado['municipios'] ?? [];

            $territorio[str_pad((string) $codigo, 2, '0', STR_PAD_LEFT)] = [
                'nombre' => (string) $estado['nombre'],
                'municipios' => $municipios === self::TODOS
                    ? self::TODOS
                    : collect((array) $municipios)
                        ->mapWithKeys(fn (string $nombre, int|string $clave): array => [
                            str_pad((string) $clave, 3, '0', STR_PAD_LEFT) => $nombre,
                        ])
                        ->all(),
            ];
        }

        return $territorio;
    }

    /**
     * La primera línea del archivo y sus filas, por su número de línea, con solo las columnas que usa la carga.
     *
     * @return array{0: string, 1: array<int, array<string, string>>}
     *
     * @throws CatalogoNoValido
     */
    private function leer(string $archivo): array
    {
        $contenido = is_file($archivo) && is_readable($archivo) ? file_get_contents($archivo) : false;

        if ($contenido === false) {
            throw new CatalogoNoValido("No se pudo leer el archivo {$archivo}. No se cargó ningún archivo.");
        }

        $lineas = preg_split('/\r\n|\n|\r/', $this->enUtf8($contenido)) ?: [];
        $encabezado = array_map(fn (string $columna): string => Str::lower(trim($columna)), explode('|', $lineas[1] ?? ''));
        $posiciones = [];
        $faltan = [];

        foreach (self::COLUMNAS as $columna) {
            $posicion = array_search(Str::lower($columna), $encabezado, true);

            if ($posicion === false) {
                $faltan[] = $columna;
            } else {
                $posiciones[$columna] = $posicion;
            }
        }

        if ($faltan !== []) {
            throw new CatalogoNoValido(sprintf(
                '%s no trae la %s %s en su segunda línea. No se cargó ningún archivo.',
                basename($archivo),
                count($faltan) === 1 ? 'columna' : 'columnas',
                implode(', ', $faltan),
            ));
        }

        $filas = [];

        foreach (array_slice($lineas, 2, preserve_keys: true) as $indice => $linea) {
            if (trim($linea) === '') {
                continue;
            }

            $campos = explode('|', $linea);
            // El número de línea del archivo, contando desde 1.
            $filas[$indice + 1] = array_map(fn (int $posicion): string => trim($campos[$posicion] ?? ''), $posiciones);
        }

        return [trim($lineas[0] ?? ''), $filas];
    }

    /**
     * El contenido en UTF-8, sin la marca de orden de bytes. El archivo de Correos de México viene en ISO-8859-1.
     */
    private function enUtf8(string $contenido): string
    {
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }

        return mb_check_encoding($contenido, 'UTF-8')
            ? $contenido
            : mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * Una fila del territorio tiene la forma del archivo: el código postal y el del municipio son números, y los nombres
     * no están vacíos.
     *
     * @param  array<string, string>  $fila
     *
     * @throws CatalogoNoValido
     */
    private function revisarFila(string $archivo, int $linea, array $fila): void
    {
        $errores = array_keys(array_filter([
            'd_codigo' => ! preg_match('/^[0-9]{5}$/', $fila['d_codigo']),
            'c_mnpio' => ! preg_match('/^[0-9]{3}$/', $fila['c_mnpio']),
            'd_asenta' => $fila['d_asenta'] === '',
            'd_tipo_asenta' => $fila['d_tipo_asenta'] === '',
            'D_mnpio' => $fila['D_mnpio'] === '',
            'd_estado' => $fila['d_estado'] === '',
        ]));

        if ($errores !== []) {
            throw new CatalogoNoValido(sprintf(
                '%s, línea %d: la fila no tiene la forma del archivo (%s). No se cargó ningún archivo.',
                basename($archivo),
                $linea,
                implode(', ', array_map(fn (string $columna): string => "{$columna}: '{$fila[$columna]}'", $errores)),
            ));
        }
    }

    /**
     * @throws CatalogoNoValido
     */
    private function compararNombre(string $que, string $codigo, string $enElArchivo, string $enLaConfiguracion): void
    {
        if (self::comparable($enElArchivo) !== self::comparable($enLaConfiguracion)) {
            throw new CatalogoNoValido(sprintf(
                '%s %s se llama %s en el archivo y %s en %s. No se cargó ningún archivo.',
                $que,
                $codigo,
                $enElArchivo,
                $enLaConfiguracion,
                self::CONFIGURACION,
            ));
        }
    }

    /**
     * Si vino un estado con lista de municipios, vinieron todos los de su lista.
     *
     * @param  array<string, array{nombre: string, municipios: string|array<string, string>}>  $territorio
     * @param  array<string, string>  $estados
     * @param  array<string, array{estado: string, nombre: string}>  $municipios
     *
     * @throws CatalogoNoValido
     */
    private function exigirLosMunicipiosDeLaConfiguracion(array $territorio, array $estados, array $municipios): void
    {
        foreach ($territorio as $estado => $configuracion) {
            $estado = (string) $estado;

            if (! isset($estados[$estado]) || $configuracion['municipios'] === self::TODOS) {
                continue;
            }

            foreach ($configuracion['municipios'] as $clave => $nombre) {
                if (! isset($municipios[$estado.$clave])) {
                    throw new CatalogoNoValido(sprintf(
                        'Los archivos de %s no traen el municipio %s (%s) de %s. No se cargó ningún archivo.',
                        $configuracion['nombre'],
                        $estado.$clave,
                        $nombre,
                        self::CONFIGURACION,
                    ));
                }
            }
        }
    }

    /**
     * Los códigos que son llaves de un arreglo pueden haberse vuelto números ('10', '10005'), así que se escriben como
     * texto.
     *
     * @param  array<string, string>  $estados
     * @param  array<string, array{estado: string, nombre: string}>  $municipios
     * @param  list<array{municipio: string, codigo_postal: string, nombre: string, tipo: string}>  $colonias
     */
    private function guardar(array $estados, array $municipios, array $colonias): void
    {
        $idsDeEstados = [];

        foreach ($estados as $codigo => $nombre) {
            $idsDeEstados[$codigo] = EntidadFederativa::query()
                ->updateOrCreate(['codigo' => (string) $codigo], ['nombre' => $nombre])
                ->getKey();
        }

        $idsDeMunicipios = [];

        foreach ($municipios as $codigo => $municipio) {
            $idsDeMunicipios[$codigo] = Municipio::query()
                ->updateOrCreate(
                    ['codigo' => (string) $codigo],
                    ['id_entidades_federativas' => $idsDeEstados[$municipio['estado']], 'nombre' => $municipio['nombre']],
                )
                ->getKey();
        }

        Colonia::query()->whereIn('id_municipios', array_values($idsDeMunicipios))->delete();

        $ahora = now();

        foreach (array_chunk($colonias, self::FILAS_POR_INSERCION) as $bloque) {
            Colonia::query()->insert(array_map(fn (array $colonia): array => [
                'id_municipios' => $idsDeMunicipios[$colonia['municipio']],
                'codigo_postal' => $colonia['codigo_postal'],
                'nombre' => $colonia['nombre'],
                'tipo' => $colonia['tipo'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], $bloque));
        }
    }

    /**
     * Cuántos municipios y colonias trajo cada estado, en el orden de la configuración.
     *
     * @param  array<string, array{nombre: string, municipios: string|array<string, string>}>  $territorio
     * @param  array<string, string>  $estados
     * @param  array<string, array{estado: string, nombre: string}>  $municipios
     * @param  list<array{municipio: string, codigo_postal: string, nombre: string, tipo: string}>  $colonias
     * @return array<string, array{nombre: string, municipios: int, colonias: int}>
     */
    private function contarPorEstado(array $territorio, array $estados, array $municipios, array $colonias): array
    {
        $porEstado = [];

        foreach (array_keys(array_intersect_key($territorio, $estados)) as $estado) {
            $estado = (string) $estado;
            $porEstado[$estado] = [
                'nombre' => $estados[$estado],
                'municipios' => count(array_filter($municipios, fn (array $municipio): bool => $municipio['estado'] === $estado)),
                'colonias' => count(array_filter(
                    $colonias,
                    fn (array $colonia): bool => str_starts_with($colonia['municipio'], $estado),
                )),
            ];
        }

        return $porEstado;
    }

    private static function comparable(string $nombre): string
    {
        return Str::lower(Str::ascii(trim($nombre)));
    }
}
