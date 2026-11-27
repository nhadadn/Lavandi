<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Autorizacion\Autorizacion;
use App\Finanzas\Ingresos;
use App\Finanzas\Pesos;
use App\Http\Controllers\Controller;
use App\Indicadores\Rango;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los ingresos del periodo, en los reportes financieros de la administración en la app (NSV-2026-001-v8.pdf:19:ANEXO A,
 * Sección A.3, Módulo D #6, "Ingresos por periodo"; openspec/changes/administracion-en-la-app/, tarea 13.1;
 * administracion-app-E37).
 *
 * Las cifras son las de la página de ingresos del panel, de la misma clase: App\Finanzas\Ingresos, con el rango de
 * App\Indicadores\Rango y los montos como los lee el panel, con App\Finanzas\Pesos (ADR-0022, decisión 9). Ingresos son
 * los pagos que confirmó la pasarela, por el día de la confirmación en la hora de la plaza (administracion-E17). La
 * ruta no valida ni calcula nada por su cuenta.
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
 *     GET /api/administracion/ingresos?desde=2027-03-01&hasta=2027-03-31&granularidad=semana
 *
 *     desde, hasta     Opcionales, AAAA-MM-DD, los dos días incluidos. Sin ellos, el mes en curso hasta hoy, en la zona
 *                      horaria de referencia de lo que no tiene plaza (indicadores-E24). Un día que no se entiende se
 *                      toma como no dado, y un rango al revés se voltea, como en el panel: nunca responde 422.
 *     granularidad     Opcional: dia (por omisión), semana o mes. Otro valor es por día.
 *
 *     200  {
 *            "rango": {                                     el rango que se usó, ajustado a periodos completos: por
 *              "desde": "2027-03-01",                       semana, del lunes de la primera al domingo de la última;
 *              "hasta": "2027-04-04",                       por mes, del día 1 al último día
 *              "granularidad": "semana",
 *              "descripcion": "Del 01/03/2027 al 04/04/2027, por semana"
 *            },
 *            "total": {                                     todo el rango
 *              "pagos": 2,                                  cuántos pagos confirmados
 *              "ingresos": "350.00",                        cuánto suman, en pesos con dos decimales, como texto
 *              "ingresos_texto": "$350.00"                  la misma cifra como la lee el panel
 *            },
 *            "periodos": [                                  uno por periodo del rango, en orden, también los que no
 *              {                                            tienen pagos, con 0 y "0.00"
 *                "inicio": "2027-03-01",                    su primer día
 *                "periodo": "Semana del 01/03/2027",        cómo se lee: "lun. 01/03/2027", "Semana del 01/03/2027"
 *                "pagos": 1,                                o "marzo 2027"
 *                "ingresos": "150.00",
 *                "ingresos_texto": "$150.00"
 *              }
 *            ]
 *          }
 *          Una orden sin confirmar, un intento rechazado o un cobro vencido no cuentan. Los reembolsos y ajustes esperan
 *          DD-012 y DD-016 (administracion-app-E39), y las comisiones del periodo, DD-020 (administracion-app-E40): no
 *          vienen en la respuesta. La pantalla no se consulta sola.
 */
class IngresosController extends Controller
{
    /** Los filtros de la consulta, los mismos del formulario de la página del panel (App\Filament\Pages\ReporteDeIngresos). */
    private const FILTROS = ['desde', 'hasta', 'granularidad'];

    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function __invoke(Request $peticion, Ingresos $ingresos): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        abort_unless($this->autorizacion->puedeVerLosReportesFinancieros($cuenta), 403, 'Esta cuenta no tiene acceso.');

        $rango = Rango::deLosFiltros($this->filtros($peticion));
        $total = $ingresos->delRango($rango);
        $periodos = [];

        foreach ($ingresos->porPeriodo($rango) as $inicio => $periodo) {
            $periodos[] = ['inicio' => $inicio, ...$periodo, 'ingresos_texto' => Pesos::texto($periodo['ingresos'])];
        }

        return response()->json([
            'rango' => [
                'desde' => $rango->desde->toDateString(),
                'hasta' => $rango->hasta->toDateString(),
                'granularidad' => $rango->granularidad->value,
                'descripcion' => $rango->descripcion(),
            ],
            'total' => [...$total, 'ingresos_texto' => Pesos::texto($total['ingresos'])],
            'periodos' => $periodos,
        ]);
    }

    /**
     * Los filtros que llegan en la consulta como texto. Uno que llega de otra forma, como desde[]=…, se toma como no dado,
     * igual que un día que Rango no entiende: el rango no tiene más regla que la de Rango.
     *
     * @return array<string, string>
     */
    private function filtros(Request $peticion): array
    {
        return array_filter($peticion->query->all(), fn (mixed $valor, string $filtro): bool => is_string($valor)
            && in_array($filtro, self::FILTROS, true), ARRAY_FILTER_USE_BOTH);
    }
}
