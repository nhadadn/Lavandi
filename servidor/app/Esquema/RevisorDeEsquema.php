<?php

namespace App\Esquema;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * La prueba de esquema: revisa las tablas de un esquema de PostgreSQL contra las reglas comunes del modelo de datos
 * (ADR-0016; openspec/changes/modelo-de-datos/, decisión 1) y devuelve la lista de violaciones.
 *
 * Lee los catálogos de la base (information_schema y pg_catalog) y no los archivos de migración, para ver lo que de
 * verdad quedó en la base. Revisa un solo esquema, public por omisión: los esquemas topology y tiger de PostGIS
 * quedan fuera. Las excepciones son las listas cerradas de config/modelo.php.
 *
 * Las violaciones salen en un orden fijo: las tablas en orden alfabético; dentro de cada una, primero las reglas de
 * la tabla, luego las de cada columna en el orden de la tabla y al final las de cada llave foránea, por su nombre.
 */
final class RevisorDeEsquema
{
    /** El formato de un nombre: minúsculas, dígitos y guiones bajos entre palabras. */
    private const SNAKE_CASE = '/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/';

    /** La columna de la plaza (modelo-multi-plaza.md). */
    private const PLAZA = 'id_plazas';

    /** La tabla de las plazas, a la que apunta la columna de la plaza (plazas-E09). */
    private const TABLA_DE_PLAZAS = 'plazas';

    /** Las columnas de alta y de cambio de Laravel, que revisa su propia regla (modelo-E17). */
    private const FECHAS_DE_ALTA_Y_CAMBIO = ['created_at', 'updated_at'];

    private const TIMESTAMPTZ = 'timestamp with time zone';

    /**
     * El tipo por nombre de columna (design.md, decisión 3): patrón del nombre, tipo esperado como lo escribe la
     * base, cómo se pide y la regla. Gana el primero que coincide.
     */
    private const TIPOS_POR_NOMBRE = [
        ['/^(monto|precio)(_[a-z0-9_]+)?$|^envio$/', 'numeric(12,2)', 'numeric(12,2)', Regla::Dinero],
        ['/^([a-z0-9_]+_)?porcentaje$/', 'numeric(5,2)', 'numeric(5,2)', Regla::Porcentaje],
        ['/^cantidad_[a-z0-9_]+$/', 'numeric(8,2)', 'numeric(8,2)', Regla::Cantidad],
        ['/^hora_/', 'time without time zone', 'time', Regla::HoraDelDia],
        ['/_(en|at)$|^(vigente|ventana)_/', self::TIMESTAMPTZ, 'timestamptz', Regla::Instante],
    ];

    private const FLOTANTES = ['real', 'double precision'];

    /** Los tipos de PostGIS de un punto o un polígono. */
    private const TIPOS_DE_MAPA = ['geometry', 'geography'];

    /** geography con su forma y SRID 4326, como lo escribe format_type: geography(Point,4326). */
    private const MAPA_VALIDO = '/^geography\([a-z]+,4326\)$/i';

    /**
     * Nombres de columna que guardarían el número, el código de seguridad o el vencimiento de una tarjeta
     * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3). Se comparan en minúsculas, por palabras separadas con _.
     */
    private const NOMBRES_DE_TARJETA = [
        // El número.
        '/(^|_)(numero|num|number)(_[a-z0-9]+)*_(tarjeta|card)(_|$)/',
        '/(^|_)(tarjeta|card)(_[a-z0-9]+)*_(numero|num|number)(_|$)/',
        '/(^|_)pan(_|$)/',
        // El código de seguridad.
        '/(^|_)(cvv2?|cvc2?|csc)(_|$)/',
        '/(^|_)codigo_(de_)?seguridad(_|$)/',
        // El vencimiento.
        '/(^|_)(vencimiento|expiracion|caducidad|exp|expiry|expiration)(_[a-z0-9]+)*_(tarjeta|card)(_|$)/',
        '/(^|_)(tarjeta|card)(_[a-z0-9]+)*_(vencimiento|expiracion|caducidad|exp|expiry|expiration)(_|$)/',
        '/(^|_)(mes|anio|month|year)_(de_)?(vencimiento|expiracion|caducidad|exp)(_|$)/',
        '/(^|_)(vencimiento|expiracion|caducidad|exp)_(mes|anio|month|year)(_|$)/',
    ];

    /**
     * Nombres de proveedores de pagos que no van en tablas ni columnas (C-192, DD-079): los dos que se evalúan, el
     * que nombra el contrato y otros del mercado mexicano. Se comparan en minúsculas, por palabras separadas con _.
     */
    private const PROVEEDORES_DE_PAGOS = '/(^|_)(conekta|stripe|mercado_?pago|paypal|openpay|braintree|adyen|clip|srpago)(_|$)/';

    private const SIN_DATOS_DE_TARJETA = 'solo la referencia de la pasarela, la marca y los últimos 4 dígitos';

    private const SIN_PROVEEDOR = 'un nombre sin el proveedor: pasarela o id_pasarela_<cosa>';

    private readonly ConnectionInterface $conexion;

    /** @var list<string> */
    private readonly array $delFramework;

    /** @var list<string> */
    private readonly array $sinPlaza;

    /** @var list<string> */
    private readonly array $plazaOpcional;

    /** @var list<string> */
    private readonly array $deEnlace;

    /**
     * @param  string  $esquema  El esquema que se revisa. Las pruebas usan uno propio para sus tablas de prueba.
     * @param  string|null  $conexion  La conexión de config/database.php; la de omisión si es null.
     */
    public function __construct(
        private readonly string $esquema = 'public',
        ?string $conexion = null,
    ) {
        $this->conexion = DB::connection($conexion);
        $this->delFramework = config('modelo.del_framework', []);
        $this->sinPlaza = config('modelo.sin_plaza', []);
        $this->plazaOpcional = config('modelo.plaza_opcional', []);
        $this->deEnlace = config('modelo.de_enlace', []);
    }

    /**
     * Las violaciones de todas las tablas del esquema, salvo las del framework. Una lista vacía es un esquema que
     * cumple el modelo.
     *
     * @return list<Violacion>
     */
    public function revisar(): array
    {
        $violaciones = [];

        foreach ($this->leerCatalogo() as $tabla => $datos) {
            if (in_array($tabla, $this->delFramework, true)) {
                continue;
            }

            array_push(
                $violaciones,
                ...$this->revisarTabla($tabla, $datos),
                ...$this->revisarColumnas($tabla, $datos),
                ...$this->revisarForaneas($tabla, $datos),
            );
        }

        return $violaciones;
    }

    /**
     * El nombre de la tabla, su llave primaria, su plaza y sus fechas de alta y de cambio.
     *
     * @param  array<string, mixed>  $datos
     * @return list<Violacion>
     */
    private function revisarTabla(string $tabla, array $datos): array
    {
        $violaciones = [];
        $columnas = $datos['columnas'];
        $deEnlace = in_array($tabla, $this->deEnlace, true);

        if (! preg_match(self::SNAKE_CASE, $tabla)) {
            $violaciones[] = new Violacion($tabla, null, Regla::Minusculas, $this->enMinusculas($tabla));
        }

        if ($this->nombraUnProveedor($tabla)) {
            $violaciones[] = new Violacion($tabla, null, Regla::ProveedorDePagos, self::SIN_PROVEEDOR);
        }

        $llaveEsperada = $this->llavePrimariaEsperada($tabla);

        if (! $deEnlace && $datos['llave_primaria'] !== [$llaveEsperada]) {
            $violaciones[] = new Violacion(
                $tabla,
                $datos['llave_primaria'] === [] ? null : implode(', ', $datos['llave_primaria']),
                Regla::LlavePrimaria,
                $llaveEsperada,
            );
        }

        if (! in_array($tabla, $this->sinPlaza, true)) {
            if (! isset($columnas[self::PLAZA])) {
                $violaciones[] = new Violacion($tabla, null, Regla::Plaza, self::PLAZA);
            } else {
                if ($columnas[self::PLAZA]['admite_vacio'] && ! in_array($tabla, $this->plazaOpcional, true)) {
                    $violaciones[] = new Violacion($tabla, self::PLAZA, Regla::PlazaNoNula, self::PLAZA.' not null');
                }

                if (! $this->tieneForaneaAPlazas($datos['foraneas'])) {
                    $violaciones[] = new Violacion(
                        $tabla,
                        self::PLAZA,
                        Regla::PlazaForanea,
                        'llave foránea a '.self::TABLA_DE_PLAZAS.' ('.self::PLAZA.')',
                    );
                }
            }
        }

        foreach (self::FECHAS_DE_ALTA_Y_CAMBIO as $fecha) {
            // Una tabla de enlace no las pide; si las tiene, van en timestamptz como las demás.
            $falta = ! isset($columnas[$fecha]) && ! $deEnlace;
            $sinZona = isset($columnas[$fecha]) && $columnas[$fecha]['tipo_base'] !== self::TIMESTAMPTZ;

            if ($falta || $sinZona) {
                $violaciones[] = new Violacion($tabla, $fecha, Regla::FechasDeAltaYCambio, 'timestamptz');
            }
        }

        return $violaciones;
    }

    /**
     * El nombre y el tipo de cada columna, y que ninguna guarde datos de tarjeta ni nombre a un proveedor.
     *
     * @param  array<string, mixed>  $datos
     * @return list<Violacion>
     */
    private function revisarColumnas(string $tabla, array $datos): array
    {
        $violaciones = [];
        $deEnlace = in_array($tabla, $this->deEnlace, true);

        foreach ($datos['columnas'] as $columna => $tipo) {
            if (! preg_match(self::SNAKE_CASE, $columna)) {
                $violaciones[] = new Violacion($tabla, $columna, Regla::Minusculas, $this->enMinusculas($columna));
            }

            // La llave primaria de una tabla que no es de enlace ya la revisa la regla de la llave primaria.
            $esLaLlavePrimaria = ! $deEnlace && $datos['llave_primaria'] === [$columna];

            if (! $esLaLlavePrimaria && ($columna === 'id' || str_ends_with($columna, '_id'))) {
                $violaciones[] = new Violacion(
                    $tabla,
                    $columna,
                    Regla::Identificador,
                    'un nombre que empiece con id_ y no termine en _id',
                );
            }

            if (($violacion = $this->revisarTipo($tabla, $columna, $tipo)) !== null) {
                $violaciones[] = $violacion;
            }

            if ($this->guardaDatosDeTarjeta($columna)) {
                $violaciones[] = new Violacion($tabla, $columna, Regla::DatosDeTarjeta, self::SIN_DATOS_DE_TARJETA);
            }

            if ($this->nombraUnProveedor($columna)) {
                $violaciones[] = new Violacion($tabla, $columna, Regla::ProveedorDePagos, self::SIN_PROVEEDOR);
            }
        }

        return $violaciones;
    }

    /**
     * El tipo que pide el nombre de la columna; si el nombre no pide ninguno, al menos que no sea flotante.
     *
     * @param  array{tipo: string, tipo_base: string, admite_vacio: bool}  $tipo
     */
    private function revisarTipo(string $tabla, string $columna, array $tipo): ?Violacion
    {
        if (in_array($columna, self::FECHAS_DE_ALTA_Y_CAMBIO, true)) {
            return null;
        }

        if (in_array($tipo['tipo_base'], self::TIPOS_DE_MAPA, true)) {
            return preg_match(self::MAPA_VALIDO, $tipo['tipo'])
                ? null
                : new Violacion($tabla, $columna, Regla::Mapa, 'geography con SRID 4326');
        }

        foreach (self::TIPOS_POR_NOMBRE as [$patron, $esperado, $comoSePide, $regla]) {
            if (preg_match($patron, $columna)) {
                return in_array($esperado, [$tipo['tipo'], $tipo['tipo_base']], true)
                    ? null
                    : new Violacion($tabla, $columna, $regla, $comoSePide);
            }
        }

        return in_array($tipo['tipo_base'], self::FLOTANTES, true)
            ? new Violacion($tabla, $columna, Regla::SinFlotantes, 'numeric')
            : null;
    }

    /**
     * Cada llave foránea: su nombre, la plaza compuesta, ON DELETE RESTRICT y su índice.
     *
     * @param  array<string, mixed>  $datos
     * @return list<Violacion>
     */
    private function revisarForaneas(string $tabla, array $datos): array
    {
        $violaciones = [];

        foreach ($datos['foraneas'] as $foranea) {
            $columnas = $foranea['columnas'];
            $llave = $foranea['llave'];

            foreach ($columnas as $posicion => $columna) {
                $columnaPadre = $foranea['columnas_padre'][$posicion];

                if ($columna !== $columnaPadre) {
                    $violaciones[] = new Violacion($tabla, $columna, Regla::NombreDeForanea, $columnaPadre, llave: $llave);
                }
            }

            // Hacia una tabla que lleva plaza, la llave es compuesta con ella: la base impide mezclar plazas.
            $conPlaza = isset($datos['columnas'][self::PLAZA]) && $foranea['padre_con_plaza'];
            $posicionDeLaPlaza = array_search(self::PLAZA, $columnas, true);
            $compuesta = $posicionDeLaPlaza !== false && $foranea['columnas_padre'][$posicionDeLaPlaza] === self::PLAZA;

            if ($conPlaza && ! $compuesta) {
                $violaciones[] = new Violacion(
                    $tabla,
                    implode(', ', $columnas),
                    Regla::PlazaCompuesta,
                    '('.implode(', ', [...$columnas, self::PLAZA]).')',
                    llave: $llave,
                );
            }

            // r es RESTRICT en pg_constraint.confdeltype; NO ACTION, lo que queda sin ON DELETE, es a.
            if ($foranea['al_borrar'] !== 'r') {
                $violaciones[] = new Violacion(
                    $tabla,
                    implode(', ', $columnas),
                    Regla::ForaneaRestrict,
                    'on delete restrict',
                    llave: $llave,
                );
            }

            if (! $this->tieneIndice($columnas, $datos['indices'])) {
                $violaciones[] = new Violacion(
                    $tabla,
                    implode(', ', $columnas),
                    Regla::ForaneaConIndice,
                    'un índice que empiece con ('.implode(', ', $columnas).')',
                    llave: $llave,
                );
            }
        }

        return $violaciones;
    }

    /**
     * Si la tabla tiene una llave foránea de id_plazas a plazas(id_plazas). La llave compuesta hacia una tabla padre
     * que lleva la plaza, como (id_lavanderias, id_plazas), no la sustituye: apunta a la tabla padre, no a plazas.
     *
     * La tabla padre se reconoce por su nombre, plazas, en cualquier esquema: las tablas de prueba de un esquema propio
     * pueden apuntar a la de public.
     *
     * @param  list<array{columnas: list<string>, columnas_padre: list<string>, tabla_padre: string}>  $foraneas
     */
    private function tieneForaneaAPlazas(array $foraneas): bool
    {
        foreach ($foraneas as $foranea) {
            if ($foranea['tabla_padre'] === self::TABLA_DE_PLAZAS
                && $foranea['columnas'] === [self::PLAZA]
                && $foranea['columnas_padre'] === [self::PLAZA]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Si algún índice empieza con las columnas de la llave, en cualquier orden entre ellas.
     *
     * @param  list<string>  $columnas
     * @param  list<list<string|null>>  $indices
     */
    private function tieneIndice(array $columnas, array $indices): bool
    {
        $buscadas = $columnas;
        sort($buscadas);

        foreach ($indices as $indice) {
            $inicio = array_slice($indice, 0, count($columnas));
            sort($inicio);

            if ($inicio === $buscadas) {
                return true;
            }
        }

        return false;
    }

    private function guardaDatosDeTarjeta(string $columna): bool
    {
        foreach (self::NOMBRES_DE_TARJETA as $patron) {
            if (preg_match($patron, strtolower($columna))) {
                return true;
            }
        }

        return false;
    }

    private function nombraUnProveedor(string $nombre): bool
    {
        return (bool) preg_match(self::PROVEEDORES_DE_PAGOS, strtolower($nombre));
    }

    private function llavePrimariaEsperada(string $tabla): string
    {
        return 'id_'.$this->enMinusculas($tabla);
    }

    /**
     * El nombre en minúsculas y en snake_case: Id_Pedidos da id_pedidos; idPedidos, también.
     */
    private function enMinusculas(string $nombre): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $nombre));
    }

    /**
     * Las tablas del esquema, en orden alfabético, con sus columnas, su llave primaria, sus llaves foráneas y sus
     * índices.
     *
     * @return array<string, array{
     *     columnas: array<string, array{tipo: string, tipo_base: string, admite_vacio: bool}>,
     *     llave_primaria: list<string>,
     *     foraneas: list<array{llave: string, al_borrar: string, tabla_padre: string, columnas: list<string>, columnas_padre: list<string>, padre_con_plaza: bool}>,
     *     indices: list<list<string|null>>
     * }>
     */
    private function leerCatalogo(): array
    {
        $catalogo = [];

        $tablas = $this->conexion->select(
            "select table_name as tabla
               from information_schema.tables
              where table_schema = ? and table_type = 'BASE TABLE'
              order by table_name",
            [$this->esquema],
        );

        foreach ($tablas as $fila) {
            $catalogo[$fila->tabla] = ['columnas' => [], 'llave_primaria' => [], 'foraneas' => [], 'indices' => []];
        }

        $columnas = $this->conexion->select(
            "select table_name as tabla, column_name as columna,
                    case when data_type = 'USER-DEFINED' then udt_name else data_type end as tipo_base,
                    numeric_precision as precision, numeric_scale as escala, is_nullable = 'YES' as admite_vacio
               from information_schema.columns
              where table_schema = ?
              order by table_name, ordinal_position",
            [$this->esquema],
        );

        foreach ($columnas as $fila) {
            if (! isset($catalogo[$fila->tabla])) {
                continue; // Las columnas de una vista.
            }

            $catalogo[$fila->tabla]['columnas'][$fila->columna] = [
                'tipo' => $fila->tipo_base === 'numeric' && $fila->precision !== null
                    ? "numeric({$fila->precision},{$fila->escala})"
                    : $fila->tipo_base,
                'tipo_base' => $fila->tipo_base,
                'admite_vacio' => (bool) $fila->admite_vacio,
            ];
        }

        // information_schema no da la forma ni el SRID de un mapa: salen de format_type, como geography(Point,4326).
        $mapas = $this->conexion->select(
            "select c.relname as tabla, a.attname as columna, format_type(a.atttypid, a.atttypmod) as tipo
               from pg_catalog.pg_attribute a
               join pg_catalog.pg_class c on c.oid = a.attrelid
               join pg_catalog.pg_namespace n on n.oid = c.relnamespace
               join pg_catalog.pg_type t on t.oid = a.atttypid
              where n.nspname = ? and t.typname in ('geometry', 'geography') and a.attnum > 0 and not a.attisdropped",
            [$this->esquema],
        );

        foreach ($mapas as $fila) {
            if (isset($catalogo[$fila->tabla]['columnas'][$fila->columna])) {
                $catalogo[$fila->tabla]['columnas'][$fila->columna]['tipo'] = $fila->tipo;
            }
        }

        $llavesPrimarias = $this->conexion->select(
            "select c.relname as tabla, {$this->nombresDeColumnas('con.conrelid', 'con.conkey')} as columnas
               from pg_catalog.pg_constraint con
               join pg_catalog.pg_class c on c.oid = con.conrelid
               join pg_catalog.pg_namespace n on n.oid = c.relnamespace
              where n.nspname = ? and con.contype = 'p'",
            [$this->esquema],
        );

        foreach ($llavesPrimarias as $fila) {
            if (isset($catalogo[$fila->tabla])) {
                $catalogo[$fila->tabla]['llave_primaria'] = json_decode($fila->columnas, true);
            }
        }

        $foraneas = $this->conexion->select(
            "select con.conname as llave, c.relname as tabla, con.confdeltype as al_borrar,
                    padre.relname as tabla_padre,
                    {$this->nombresDeColumnas('con.conrelid', 'con.conkey')} as columnas,
                    {$this->nombresDeColumnas('con.confrelid', 'con.confkey')} as columnas_padre,
                    exists (
                        select 1 from pg_catalog.pg_attribute p
                         where p.attrelid = con.confrelid and p.attname = ? and not p.attisdropped
                    ) as padre_con_plaza
               from pg_catalog.pg_constraint con
               join pg_catalog.pg_class c on c.oid = con.conrelid
               join pg_catalog.pg_class padre on padre.oid = con.confrelid
               join pg_catalog.pg_namespace n on n.oid = c.relnamespace
              where n.nspname = ? and con.contype = 'f'
              order by c.relname, con.conname",
            [self::PLAZA, $this->esquema],
        );

        foreach ($foraneas as $fila) {
            if (isset($catalogo[$fila->tabla])) {
                $catalogo[$fila->tabla]['foraneas'][] = [
                    'llave' => $fila->llave,
                    'al_borrar' => $fila->al_borrar,
                    'tabla_padre' => $fila->tabla_padre,
                    'columnas' => json_decode($fila->columnas, true),
                    'columnas_padre' => json_decode($fila->columnas_padre, true),
                    'padre_con_plaza' => (bool) $fila->padre_con_plaza,
                ];
            }
        }

        // Solo los índices válidos y completos: uno parcial no cubre todas las filas de la llave. Una columna de
        // expresión sale como null y no cubre ninguna llave.
        $indices = $this->conexion->select(
            "select c.relname as tabla, {$this->nombresDeColumnas('i.indrelid', 'i.indkey', 'i.indnkeyatts')} as columnas
               from pg_catalog.pg_index i
               join pg_catalog.pg_class c on c.oid = i.indrelid
               join pg_catalog.pg_namespace n on n.oid = c.relnamespace
              where n.nspname = ? and i.indisvalid and i.indpred is null",
            [$this->esquema],
        );

        foreach ($indices as $fila) {
            if (isset($catalogo[$fila->tabla])) {
                $catalogo[$fila->tabla]['indices'][] = json_decode($fila->columnas, true);
            }
        }

        return $catalogo;
    }

    /**
     * Una expresión SQL que convierte un arreglo de números de columna en sus nombres, en orden, como JSON. Si se pasa
     * un límite, toma solo las primeras columnas: las de la llave de un índice, sin las de INCLUDE.
     */
    private function nombresDeColumnas(string $tabla, string $numeros, ?string $limite = null): string
    {
        $hasta = $limite === null ? '' : "where k.orden <= {$limite}";

        return "array_to_json(array(
                    select a.attname
                      from unnest({$numeros}::smallint[]) with ordinality as k(numero, orden)
                      left join pg_catalog.pg_attribute a on a.attrelid = {$tabla} and a.attnum = k.numero
                     {$hasta}
                     order by k.orden
                ))";
    }
}
