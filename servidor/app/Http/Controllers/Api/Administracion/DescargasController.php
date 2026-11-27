<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Autorizacion\Autorizacion;
use App\Exportacion\CreacionDeDescarga;
use App\Exportacion\EstadoDeLaDescarga;
use App\Exportacion\Exportacion;
use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La descarga de los reportes en CSV y en Excel desde la app de la administración (NSV-2026-001-v8.pdf:22:ANEXO A,
 * Sección A.5, "Descarga de reportes en formato CSV y Excel para cualquier rango de fechas"; :21:ANEXO A, Sección
 * A.4.4; openspec/changes/administracion-en-la-app/, tareas 19.2 a 19.4; administracion-app-E56, E57 y E59 a E62).
 *
 * Hace lo mismo que el botón "Descargar" del panel, con las mismas clases (ADR-0022, decisiones 9 y 15): la crea
 * App\Exportacion\CreacionDeDescarga, con la regla del rango de App\Validacion\RangoDeFechas; la escribe la cola de la
 * exportación de Filament con App\Exportacion\EscribirArchivoDelReporte; su estado lo da App\Exportacion\Exportacion; y el
 * archivo lo entrega el mismo descargador que usa la ruta de descarga de Filament. Quién pide y quién baja lo decide el
 * componente de autorización (puedeDescargarReportes() y puedeDescargarLaExportacion()). La ruta no valida ni calcula
 * nada por su cuenta.
 *
 * Contrato para la app. Cada ruta lleva sesión y pasa por el componente de autorización:
 *
 *     Authorization: Bearer <token de administración>   (auth:sanctum y autorizar:administracion)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión, o con el token vencido o revocado
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no es de administración, o su token no lleva la
 *                                                         habilidad de administración (administracion-app-E06 y E07)
 *
 * Una descarga, como llega en cada respuesta:
 *
 *     {
 *       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",       el ULID con el que se nombra en las rutas de abajo
 *       "reporte": "pedidos-totales",                     su código: uno de los doce de POST, abajo
 *       "titulo": "Pedidos totales",                      su nombre, como en el botón del panel
 *       "desde": "2026-11-30",                            los dos días del rango que cubre su archivo, AAAA-MM-DD, ya
 *       "hasta": "2026-12-06",                            ajustado a periodos completos: los de su nombre, en "archivos"
 *                                                         (ExportadorDeIndicador::diasDelArchivo()). La granularidad no
 *                                                         llega: la fila de exports no guarda las opciones de la
 *                                                         descarga, y la única migración del cambio es la del
 *                                                         id_publico (ADR-0022, decisión 15)
 *       "pedida_en": "2026-11-25T18:00:00Z",              cuándo se pidió, en UTC
 *       "estado": "generandose" | "lista" | "no_se_pudo",
 *                                                         generandose: la cola todavía no la termina; se consulta otra
 *                                                         vez con GET /api/administracion/descargas/{id_publico}.
 *                                                         lista: sus dos archivos se bajan con la ruta del archivo.
 *                                                         no_se_pudo: la cola terminó sin escribir su archivo; se pide
 *                                                         otra vez con POST (ADR-0022, decisión 15)
 *       "archivos": {                                     el nombre de cada archivo, con su extensión: el reporte y los
 *         "csv": "pedidos-totales-2026-11-30-al-2026-12-06.csv",     dos días de su rango, ya ajustado a periodos
 *         "xlsx": "pedidos-totales-2026-11-30-al-2026-12-06.xlsx"    completos, como en el panel
 *       }
 *     }
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/administracion/descargas                      (administracion-app-E56 y E60)
 *
 *     {
 *       "reporte": "pedidos-totales",                     uno de los doce códigos, uno por exportador del panel:
 *                                                           pedidos-totales               (F-I01)
 *                                                           pedidos-por-estado            (F-I02)
 *                                                           usuarios-activos              (F-I03)
 *                                                           usuarios-que-vuelven-a-pedir  (F-I04)
 *                                                           monto-promedio-por-pedido     (F-I05)
 *                                                           calificacion-por-lavanderia   (F-I07)
 *                                                           entregas-por-repartidor       (F-I08)
 *                                                           ingresos-brutos               (F-I09)
 *                                                           pedidos-por-colonia           (F-I11, por colonia)
 *                                                           pedidos-por-zona              (F-I11, por zona)
 *                                                           ingresos-por-periodo          (F-D06, los ingresos)
 *                                                           conciliacion-con-la-pasarela  (F-D06, la conciliación)
 *                                                         F-I06, F-I10 y las comisiones del periodo no tienen descarga
 *                                                         mientras su regla no exista (administracion-app-E63)
 *       "desde": "2026-11-30",                            los dos días incluidos, AAAA-MM-DD; los dos obligatorios;
 *       "hasta": "2026-12-06",                            sin tope de duración (exportacion-E04 y E05)
 *       "granularidad": "dia" | "semana" | "mes"          obligatoria, salvo en la conciliación, que no la lleva y la
 *                                                         ignora si llega
 *     }
 *
 *     202  { "descarga": { …la descarga… } }
 *          La descarga nace generándose y la cola escribe su archivo; con la cola en la misma petición, ya llega lista.
 *          La de la conciliación trae los movimientos que ya se guardaron al conciliar ese periodo, sin volver a leer
 *          la pasarela.
 *     422  { "message": "…", "errors": { "reporte" | "desde" | "hasta" | "granularidad": ["…"] } }
 *          "Ese no es un reporte que se pueda descargar.", "La fecha de fin no puede ser anterior a la de inicio."
 *          (exportacion-E06) o "Elige ver el reporte por día, por semana o por mes."; una fecha que no viene en
 *          AAAA-MM-DD, como "December 5, 2026", también responde 422 en su campo (administracion-app-E60). En ninguno
 *          crea una descarga.
 *
 *     GET /api/administracion/descargas                       (administracion-app-E59)
 *
 *     200  { "descargas": [ { …la descarga… } ], "dias_de_vida": 7 }
 *          Las de la cuenta que siguen vivas (los días de EXPORTACION_DIAS_DE_VIDA, 7 por omisión; exportacion-E12), de
 *          la más nueva a la más vieja. Ni las de otra cuenta, ni las borradas, ni las de antes de que existiera su
 *          id_publico. Vacía si no hay. dias_de_vida: cuántos días vive cada una, al menos uno, para que la app los diga.
 *
 *     GET /api/administracion/descargas/{id_publico}           (administracion-app-E57)
 *
 *     200  { "descarga": { …la descarga… } }               lo que consulta la app cada 5 s mientras se genera
 *     404  { "message": "No existe la descarga." }          no existe, no es ULID, ya se borró o es de otra cuenta
 *                                                         (exportacion-E11; ADR-0019, decisión 3)
 *
 *     GET /api/administracion/descargas/{id_publico}/{formato}  (administracion-app-E58, E61 y E62)
 *
 *     {formato}  csv o xlsx
 *
 *     200  el archivo, con Content-Disposition: attachment y su nombre de "archivos". El CSV va como text/csv, en UTF-8
 *          con su marca de orden de bytes; el XLSX, con el tipo de Excel que le da el descargador. Es el mismo archivo
 *          que baja el panel para el mismo reporte y el mismo rango (exportacion-E07 a E09).
 *     404  { "message": "No existe la descarga." }          como arriba
 *     404  { "message": "No existe ese archivo." }          el formato no es csv ni xlsx, o la descarga no está lista
 */
class DescargasController extends Controller
{
    private const SIN_ACCESO = 'Esta cuenta no tiene acceso.';

    private const NO_EXISTE_LA_DESCARGA = 'No existe la descarga.';

    private const NO_EXISTE_EL_ARCHIVO = 'No existe ese archivo.';

    public function __construct(private readonly Autorizacion $autorizacion) {}

    /**
     * Pide la descarga de un reporte: la crea y pone en cola su archivo, como el botón del panel.
     */
    public function pedir(Request $peticion, CreacionDeDescarga $creacion): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        abort_unless($this->autorizacion->puedeDescargarReportes($cuenta), 403, self::SIN_ACCESO);

        $datos = $peticion->validate(CreacionDeDescarga::reglas(), CreacionDeDescarga::mensajes());

        return response()->json(['descarga' => self::descarga($creacion->pedir($cuenta, $datos))], 202);
    }

    /**
     * Las descargas vivas de la cuenta, de la más nueva a la más vieja.
     */
    public function lista(Request $peticion): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        $descargas = Exportacion::query()
            ->pedidasPor($cuenta)
            ->conIdPublico()
            ->vivas()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Exportacion $exportacion): bool => $this->autorizacion->puedeDescargarLaExportacion($cuenta, $exportacion));

        return response()->json([
            'descargas' => $descargas->map(self::descarga(...))->values()->all(),
            'dias_de_vida' => Exportacion::diasDeVida(),
        ]);
    }

    /**
     * Una descarga de la cuenta, con su estado.
     */
    public function mostrar(Request $peticion, string $descarga): JsonResponse
    {
        $exportacion = $this->descargaDeLaCuenta($peticion, $descarga);

        if ($exportacion === null) {
            return response()->json(['message' => self::NO_EXISTE_LA_DESCARGA], 404);
        }

        return response()->json(['descarga' => self::descarga($exportacion)]);
    }

    /**
     * El archivo de una descarga lista, en ese formato, con el descargador de la ruta de descarga de Filament.
     */
    public function archivo(Request $peticion, string $descarga, string $formato): Response
    {
        $exportacion = $this->descargaDeLaCuenta($peticion, $descarga);

        if ($exportacion === null) {
            return response()->json(['message' => self::NO_EXISTE_LA_DESCARGA], 404);
        }

        $enFormato = ExportFormat::tryFrom($formato);

        if ($enFormato === null || $exportacion->estado() !== EstadoDeLaDescarga::Lista) {
            return response()->json(['message' => self::NO_EXISTE_EL_ARCHIVO], 404);
        }

        return $exportacion->exporter::getDownloader($enFormato)($exportacion);
    }

    /**
     * La descarga de la cuenta por su identificador público. Quién la baja lo dice el componente de autorización: una de
     * otra cuenta, una que no existe y un valor que no es ULID responden igual (exportacion-E11).
     */
    private function descargaDeLaCuenta(Request $peticion, string $descarga): ?Exportacion
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();
        $exportacion = Exportacion::porIdPublico($descarga);

        return $exportacion !== null && $this->autorizacion->puedeDescargarLaExportacion($cuenta, $exportacion)
            ? $exportacion
            : null;
    }

    /**
     * La descarga como la recibe la app.
     *
     * @return array{id_publico: string, reporte: string, titulo: string, desde: string|null, hasta: string|null, pedida_en: string|null, estado: string, archivos: array{csv: string, xlsx: string}}
     */
    private static function descarga(Exportacion $exportacion): array
    {
        $dias = $exportacion->exporter::diasDelArchivo((string) $exportacion->file_name);

        return [
            'id_publico' => (string) $exportacion->id_publico,
            'reporte' => $exportacion->exporter::NOMBRE,
            'titulo' => $exportacion->exporter::titulo(),
            'desde' => $dias['desde'] ?? null,
            'hasta' => $dias['hasta'] ?? null,
            'pedida_en' => $exportacion->created_at?->utc()->toIso8601ZuluString(),
            'estado' => $exportacion->estado()->value,
            'archivos' => [
                ExportFormat::Csv->value => "{$exportacion->file_name}.csv",
                ExportFormat::Xlsx->value => "{$exportacion->file_name}.xlsx",
            ],
        ];
    }
}
