<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Autorizacion\Autorizacion;
use App\Cobro\PasarelaNoDisponible;
use App\Finanzas\ConciliacionConLaPasarela;
use App\Finanzas\Pesos;
use App\Finanzas\ResultadoDeConciliacion;
use App\Http\Controllers\Controller;
use App\Indicadores\Rango;
use App\Models\Usuario;
use App\Validacion\RangoDeFechas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La conciliación de un periodo con la pasarela, en los reportes financieros de la administración en la app
 * (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6, "conciliación con Conekta";
 * openspec/changes/administracion-en-la-app/, tarea 13.2; administracion-app-E38).
 *
 * Hace lo mismo que "Conciliar un periodo" en la página de conciliación del panel, con las mismas clases: la regla del
 * rango de App\Validacion\RangoDeFechas, y App\Finanzas\ConciliacionConLaPasarela, que lee de la pasarela configurada
 * sus pagos del periodo, los guarda, uno por orden, y los compara con los pagos que registró la plataforma
 * (administracion-E20 y E21; segunda adenda del 2026-11-22 de ADR-0010, decisiones 6 y 7). Los montos van también como
 * los lee el panel, con App\Finanzas\Pesos (ADR-0022, decisión 9). La ruta no valida ni calcula nada por su cuenta.
 *
 * Contrato para la app. La ruta lleva sesión y pasa por el componente de autorización:
 *
 *     Authorization: Bearer <token de administración>   (auth:sanctum y autorizar:administracion)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión, o con el token vencido o revocado
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no es de administración, o su token no lleva la
 *                                                         habilidad de administración (administracion-app-E06 y E07)
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/administracion/conciliaciones
 *
 *     { "desde": "2027-03-01", "hasta": "2027-03-31" }     los dos días incluidos; los dos obligatorios, en
 *                                                          AAAA-MM-DD (ver el 422)
 *
 *     200  {
 *            "rango": { "desde": "2027-03-01", "hasta": "2027-03-31", "descripcion": "Del 01/03/2027 al 31/03/2027" },
 *            "resumen": [                                   cuántas filas hay de cada resultado: siempre los cuatro, en
 *              {                                            este orden, también los que tienen 0
 *                "resultado": "conciliado",
 *                "resultado_texto": "Conciliado",
 *                "cuantos": 3
 *              }
 *            ],
 *            "filas": [                                     una por pago de la plataforma y por movimiento de la
 *              {                                            pasarela, de la más antigua a la más nueva
 *                "resultado": "conciliado",                 conciliado, movimiento_sin_pedido, pedido_sin_movimiento o
 *                                                           monto_distinto
 *                "resultado_texto": "Conciliado",           su texto: Conciliado, Movimiento sin pedido, Pedido pagado
 *                                                           sin movimiento o Monto que no coincide
 *                "orden": "ord_2x9…",                       la orden en la pasarela
 *                "folio": "7KQ2M9XA",                       el del pedido; null en un movimiento sin pedido
 *                "plaza": "…",                              la del pedido; null en un movimiento sin pedido
 *                "fecha": "05/03/2027 10:00",               la del pago, en la hora de la plaza; la de un movimiento
 *                                                           sin pedido, en la zona de referencia de lo que no tiene
 *                                                           plaza (indicadores-E24)
 *                "monto_plataforma": "150.00",              en pesos con dos decimales, como texto; null en un
 *                "monto_plataforma_texto": "$150.00",       movimiento sin pedido, y su texto también
 *                "monto_pasarela": "150.00",                null en un pedido pagado sin movimiento, y su texto
 *                "monto_pasarela_texto": "$150.00"          también
 *              }
 *            ]
 *          }
 *          Sin pagos ni movimientos en el periodo, "filas" llega vacía y el resumen, en 0. Conciliar otra vez el mismo
 *          periodo vuelve a leer la pasarela y no repite ningún movimiento. La comisión que retiene la pasarela se
 *          guarda, pero no viene en la respuesta: quién la absorbe espera DD-013 (administracion-app-E41).
 *     422  { "message": "…", "errors": { "hasta": ["La fecha de fin no puede ser anterior a la de inicio."] } }
 *          también sin alguna de las dos fechas, con una que no es fecha o con una que no viene en AAAA-MM-DD
 *          ("03/01/2027", "1 March 2027"), en la suya: la regla del rango pide ese formato (date_format:Y-m-d en
 *          App\Validacion\RangoDeFechas), el único que lee Rango::deLosFiltros(), así la conciliación nunca corre
 *          sobre otro periodo. No lee la pasarela. En esos casos el mensaje es el del validador de Laravel, en inglés:
 *          RangoDeFechas solo trae el del rango al revés. El panel no llega a ellos: su selector de fecha manda siempre
 *          AAAA-MM-DD.
 *     503  { "message": "No se pudo leer la pasarela" }
 *          no hay pasarela en el entorno, le falta su llave o no responde. No guarda nada; se puede conciliar otra vez
 *          más tarde.
 */
class ConciliacionesController extends Controller
{
    /** Lo que dice la conciliación cuando la pasarela no responde, como el aviso del panel. */
    public const SIN_PASARELA = 'No se pudo leer la pasarela';

    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function __invoke(Request $peticion, ConciliacionConLaPasarela $conciliacion): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        abort_unless($this->autorizacion->puedeVerLosReportesFinancieros($cuenta), 403, 'Esta cuenta no tiene acceso.');

        $datos = $peticion->validate(RangoDeFechas::reglas(), RangoDeFechas::mensajes());
        $rango = Rango::deLosFiltros([
            RangoDeFechas::INICIO => (string) $datos[RangoDeFechas::INICIO],
            RangoDeFechas::FIN => (string) $datos[RangoDeFechas::FIN],
        ]);

        try {
            $reporte = $conciliacion->conciliar($rango);
        } catch (PasarelaNoDisponible $error) {
            // Queda en el registro con su motivo, como cuando la excepción responde sola.
            report($error);

            return response()->json(['message' => self::SIN_PASARELA], 503);
        }

        $resumen = [];

        foreach (ConciliacionConLaPasarela::resumen($reporte) as $resultado => $cuantos) {
            $resumen[] = [
                'resultado' => $resultado,
                'resultado_texto' => ResultadoDeConciliacion::from($resultado)->getLabel(),
                'cuantos' => $cuantos,
            ];
        }

        return response()->json([
            'rango' => [
                'desde' => $rango->desde->toDateString(),
                'hasta' => $rango->hasta->toDateString(),
                'descripcion' => $rango->dias(),
            ],
            'resumen' => $resumen,
            'filas' => array_map(self::fila(...), array_values($reporte)),
        ]);
    }

    /**
     * Una fila del reporte como la recibe la app: el resultado y cada monto, también con su texto.
     *
     * @param  array{resultado: ResultadoDeConciliacion, orden: string, folio: string|null, plaza: string|null, fecha: string, monto_plataforma: string|null, monto_pasarela: string|null}  $fila
     * @return array<string, string|null>
     */
    private static function fila(array $fila): array
    {
        return [
            'resultado' => $fila['resultado']->value,
            'resultado_texto' => $fila['resultado']->getLabel(),
            'orden' => $fila['orden'],
            'folio' => $fila['folio'],
            'plaza' => $fila['plaza'],
            'fecha' => $fila['fecha'],
            'monto_plataforma' => $fila['monto_plataforma'],
            'monto_plataforma_texto' => $fila['monto_plataforma'] === null ? null : Pesos::texto($fila['monto_plataforma']),
            'monto_pasarela' => $fila['monto_pasarela'],
            'monto_pasarela_texto' => $fila['monto_pasarela'] === null ? null : Pesos::texto($fila['monto_pasarela']),
        ];
    }
}
