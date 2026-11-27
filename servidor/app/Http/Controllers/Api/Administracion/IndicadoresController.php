<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Autorizacion\Autorizacion;
use App\Finanzas\Ingresos;
use App\Finanzas\Pesos;
use App\Http\Controllers\Controller;
use App\Indicadores\AgrupacionDelMapa;
use App\Indicadores\CortesDelSemaforo;
use App\Indicadores\EntregasPorRepartidor;
use App\Indicadores\Intensidad;
use App\Indicadores\MontoPromedio;
use App\Indicadores\PedidosPorColonia;
use App\Indicadores\PedidosPorEstado;
use App\Indicadores\PedidosPorZona;
use App\Indicadores\PedidosTotales;
use App\Indicadores\Rango;
use App\Indicadores\RankingDeLavanderias;
use App\Indicadores\Refresco;
use App\Indicadores\RetencionPorMesDeRegistro;
use App\Indicadores\UsuariosActivos;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los indicadores del negocio, en la administración en la app (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5;
 * NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4; openspec/changes/administracion-en-la-app/, tareas 17.1, 17.2 y 17.3;
 * administracion-app-E45 a E53).
 *
 * Una categoría del A.5 por llamada: la pestaña que la app tiene a la vista. Cada cifra sale de la misma clase que su
 * widget del panel (App\Indicadores y App\Finanzas\Ingresos), con el rango de App\Indicadores\Rango y el intervalo de
 * App\Indicadores\Refresco (ADR-0022, decisión 9). La ruta no valida ni calcula nada por su cuenta: solo pone en lista
 * lo que da cada clase y los montos como los lee el panel, con App\Finanzas\Pesos. Cuenta los datos de todas las
 * plazas, como el panel: filtrar por plaza está fuera del contrato.
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
 *     GET /api/administracion/indicadores/{categoria}?desde=2026-11-30&hasta=2026-12-06&granularidad=dia
 *
 *     categoria        operaciones, usuarios, socios, financiero o geografico, las cinco categorías del A.5, en
 *                      minúsculas y sin acento. Otra responde 404 { "message": "No existe la categoría." }.
 *     desde, hasta     Opcionales, AAAA-MM-DD, los dos días incluidos. Sin ellos, el mes en curso hasta hoy, en la zona
 *                      horaria de referencia de lo que no tiene plaza (indicadores-E24). Un día que no se entiende se
 *                      toma como no dado, y un rango al revés se voltea, como en el panel: nunca responde 422.
 *     granularidad     Opcional: dia (por omisión), semana o mes. Otro valor es por día.
 *     agrupacion       Solo en geografico, opcional: colonia (por omisión) o zona. Otro valor es por colonia, como en el
 *                      panel: la regla es la de App\Indicadores\AgrupacionDelMapa.
 *
 *     200  {
 *            "categoria": "operaciones",                    la que se pidió
 *            "rango": {                                     el rango que se usó, ajustado a periodos completos: por
 *              "desde": "2026-11-30",                       semana, del lunes de la primera al domingo de la última;
 *              "hasta": "2026-12-06",                       por mes, del día 1 al último día. Vale para las cinco
 *              "granularidad": "dia",                       categorías (administracion-app-E45)
 *              "descripcion": "Del 30/11/2026 al 06/12/2026, por día"
 *            },
 *            "refresco": "30s",                             cada cuánto se consulta sola la pestaña visible: el
 *                                                           intervalo de indicadores.refresco, el del panel, un entero
 *                                                           mayor que cero con s o ms ("30s", "1500ms"); null si la
 *                                                           configuración no trae uno que sirva: entonces no se
 *                                                           consulta sola (administracion-app-E51; indicadores-E22)
 *            "indicadores": { ... }                         los de la categoría, abajo
 *          }
 *
 *     Los montos van como texto con dos decimales, en pesos. El promedio, el promedio anterior y los ingresos llevan
 *     además su "_texto", la misma cifra como la lee el panel ("$350.00"); la diferencia del monto promedio va sin él.
 *     Un indicador que espera una respuesta va con "en_espera", la lista de sus DD, y sin ninguna cifra
 *     (administracion-app-E53).
 *
 *     operaciones (administracion-app-E46)
 *       "pedidos_totales": {                                F-I01, de PedidosTotales::porPeriodo()
 *         "periodos": [                                     uno por periodo del rango, en orden, también los que no
 *           {                                               tienen pedidos
 *             "inicio": "2026-11-30",                       su primer día
 *             "periodo": "lun. 30/11/2026",                 cómo se lee: "lun. 30/11/2026", "Semana del 30/11/2026"
 *             "pedidos": 1,                                 o "noviembre 2026"
 *             "periodo_anterior": "dom. 29/11/2026",        el periodo de antes, que va junto en la tabla
 *             "pedidos_anterior": 1
 *           }
 *         ]
 *       },
 *       "pedidos_por_estado": { "activos": 3, "completados": 1 }     F-I02, de PedidosPorEstado::delRango(). Los
 *                                                                     cancelados esperan DD-016 (administracion-app-E55)
 *
 *     usuarios (administracion-app-E47)
 *       "usuarios_activos": {                               F-I03, de UsuariosActivos::porPeriodo()
 *         "periodos": [ { "inicio": "2026-11-30", "periodo": "lun. 30/11/2026", "personas": 1 } ]
 *       },
 *       "vuelven_a_pedir": {                                F-I04, de RetencionPorMesDeRegistro::porMes(): un mes por
 *         "meses": [                                        cada mes que toca el rango
 *           {
 *             "inicio": "2026-11-01",
 *             "mes": "noviembre 2026",
 *             "con_primer_pedido": 3,                       las cuentas registradas ese mes que pidieron
 *             "porcentaje": 33.3                            las que volvieron a pedir, con un decimal; null si ninguna
 *           }                                               pidió
 *         ]
 *       },
 *       "monto_promedio": {                                 F-I05, de MontoPromedio::delRango(), frente al periodo
 *         "promedio": "275.00",                             anterior de la misma duración. Cada cifra es null si su
 *         "promedio_texto": "$275.00",                      periodo no tiene pedidos con monto; la diferencia y la
 *         "promedio_anterior": "200.00",                    tendencia, si falta alguno de los dos
 *         "promedio_anterior_texto": "$200.00",
 *         "diferencia": "75.00",                            con signo: "-50.00" si bajó
 *         "tendencia": "subio",                             subio, bajo o igual (App\Indicadores\Tendencia)
 *         "tendencia_texto": "Subió"                        su texto: Subió, Bajó o Igual
 *       }
 *
 *     socios (administracion-app-E48)
 *       "activas_frente_a_registradas": { "en_espera": ["DD-083"] }  F-I06, sin cifra (administracion-app-E54)
 *       "calificacion_por_lavanderia": {                    F-I07, de RankingDeLavanderias::calcular(), con
 *         "semaforo_configurado": true,                     CortesDelSemaforo y Semaforo. No depende del rango. false
 *                                                           si la configuración no trae dos cortes que sirvan: sin
 *                                                           semáforo, el "Sin cortes configurados" del panel. Las
 *                                                           cifras de los cortes no van: las fija EL CLIENTE (DD-114),
 *                                                           y la app no las muestra (imágenes 46 y 47 de la
 *                                                           Propuesta 6)
 *         "lavanderias": [                                  en el orden del ranking; las que no tienen calificaciones
 *           {                                               van al final
 *             "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el de la lavandería
 *             "lugar": 1,                                   null si no tiene calificaciones
 *             "lavanderia": "Lavandería de prueba",
 *             "promedio": 4.8,                              de 1 a 5, con un decimal; null si no tiene calificaciones
 *             "semaforo": "verde",                          verde, amarillo o rojo; null sin calificaciones o sin cortes
 *             "semaforo_texto": "Verde"                     su texto: Verde, Amarillo o Rojo
 *           }
 *         ]
 *       },
 *       "entregas_por_repartidor": {                        F-I08, de EntregasPorRepartidor::delRango()
 *         "repartidores": [                                 de más a menos entregas
 *           {
 *             "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el del repartidor
 *             "repartidor": "Repartidor de prueba",
 *             "plaza": "Plaza de prueba",                   null si no tiene plaza
 *             "entregas": 2
 *           }
 *         ]
 *       }
 *
 *     financiero (administracion-app-E49)
 *       "ingresos_brutos": {                                F-I09, de App\Finanzas\Ingresos::porPeriodo(), la clase
 *         "periodos": [                                     que usa GET /api/administracion/ingresos (tarea 13.1)
 *           {
 *             "inicio": "2027-01-01",
 *             "periodo": "enero 2027",
 *             "pagos": 2,                                   cuántos pagos confirmados
 *             "ingresos": "350.00",
 *             "ingresos_texto": "$350.00"
 *           }
 *         ]
 *       },
 *       "comisiones": { "en_espera": ["DD-020", "DD-013"] }          F-I10, sin cifra (administracion-app-E54)
 *
 *     geografico (administracion-app-E50)
 *       "mapa_de_calor": {                                  F-I11, por colonia, de PedidosPorColonia::delRango()
 *         "agrupacion": "colonia",
 *         "colonias": [                                     de más a menos pedidos
 *           {
 *             "colonia": "Colonia de prueba",               la de la dirección de recolección
 *             "municipio": "Durango",                       null si no tiene
 *             "plaza": "Plaza de prueba",
 *             "pedidos": 5,
 *             "intensidad": 1.0,                            de 0 a 1, frente a la colonia con más pedidos
 *             "color": "#104281",                           el de Intensidad::color(), para el círculo
 *             "latitud": 24.02,                             el centro de sus recolecciones
 *             "longitud": -104.65
 *           }
 *         ]
 *       }
 *       "mapa_de_calor": {                                  F-I11, por zona, de PedidosPorZona::delRango()
 *         "agrupacion": "zona",
 *         "zonas": [                                        las activas, también con 0, y una inactiva solo si tuvo
 *           {                                               pedidos; de más a menos pedidos
 *             "zona": "Centro",
 *             "plaza": "Plaza de prueba",
 *             "pedidos": 5,
 *             "intensidad": 1.0,
 *             "color": "#104281",
 *             "geometria": { "type": "MultiPolygon", "coordinates": [ ... ] }   en GeoJSON, como al panel
 *           }
 *         ]
 *       }
 *
 *     Ninguna categoría trae descargas: son de /api/administracion/descargas.
 */
class IndicadoresController extends Controller
{
    /** Las cinco categorías del A.5, en su orden. */
    public const CATEGORIAS = ['operaciones', 'usuarios', 'socios', 'financiero', 'geografico'];

    /** Los filtros del rango, los mismos del formulario de la página de indicadores del panel. */
    private const FILTROS = ['desde', 'hasta', 'granularidad'];

    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function __invoke(Request $peticion, string $categoria): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        abort_unless($this->autorizacion->puedeVerLosIndicadores($cuenta), 403, 'Esta cuenta no tiene acceso.');
        abort_unless(in_array($categoria, self::CATEGORIAS, true), 404, 'No existe la categoría.');

        $rango = Rango::deLosFiltros($this->filtros($peticion, self::FILTROS));

        return response()->json([
            'categoria' => $categoria,
            'rango' => [
                'desde' => $rango->desde->toDateString(),
                'hasta' => $rango->hasta->toDateString(),
                'granularidad' => $rango->granularidad->value,
                'descripcion' => $rango->descripcion(),
            ],
            'refresco' => Refresco::intervalo(),
            'indicadores' => match ($categoria) {
                'operaciones' => $this->operaciones($rango),
                'usuarios' => $this->usuarios($rango),
                'socios' => $this->socios($rango),
                'financiero' => $this->financiero($rango),
                'geografico' => $this->geografico($rango, AgrupacionDelMapa::elegida($this->filtros($peticion, ['agrupacion'])['agrupacion'] ?? null)),
            },
        ]);
    }

    /**
     * F-I01 y F-I02.
     *
     * @return array<string, mixed>
     */
    private function operaciones(Rango $rango): array
    {
        return [
            'pedidos_totales' => ['periodos' => $this->conSuClave(app(PedidosTotales::class)->porPeriodo($rango), 'inicio')],
            'pedidos_por_estado' => app(PedidosPorEstado::class)->delRango($rango),
        ];
    }

    /**
     * F-I03, F-I04 y F-I05.
     *
     * @return array<string, mixed>
     */
    private function usuarios(Rango $rango): array
    {
        $monto = app(MontoPromedio::class)->delRango($rango);

        return [
            'usuarios_activos' => ['periodos' => $this->conSuClave(app(UsuariosActivos::class)->porPeriodo($rango), 'inicio')],
            'vuelven_a_pedir' => ['meses' => $this->conSuClave(app(RetencionPorMesDeRegistro::class)->porMes($rango), 'inicio')],
            'monto_promedio' => [
                'promedio' => $monto['promedio'],
                'promedio_texto' => $this->pesos($monto['promedio']),
                'promedio_anterior' => $monto['promedio_anterior'],
                'promedio_anterior_texto' => $this->pesos($monto['promedio_anterior']),
                'diferencia' => $monto['diferencia'],
                'tendencia' => $monto['tendencia']?->value,
                'tendencia_texto' => $monto['tendencia']?->getLabel(),
            ],
        ];
    }

    /**
     * F-I06, en espera, F-I07 y F-I08.
     *
     * @return array<string, mixed>
     */
    private function socios(Rango $rango): array
    {
        $ranking = [];

        foreach ($this->conSuClave(app(RankingDeLavanderias::class)->calcular(), 'id_publico') as $fila) {
            $ranking[] = [
                ...$fila,
                'semaforo' => $fila['semaforo']?->value,
                'semaforo_texto' => $fila['semaforo']?->getLabel(),
            ];
        }

        return [
            // Qué lavandería cuenta como activa espera DD-083 (indicadores-E11).
            'activas_frente_a_registradas' => ['en_espera' => ['DD-083']],
            'calificacion_por_lavanderia' => [
                'semaforo_configurado' => CortesDelSemaforo::deLaConfiguracion() !== null,
                'lavanderias' => $ranking,
            ],
            'entregas_por_repartidor' => [
                'repartidores' => $this->conSuClave(app(EntregasPorRepartidor::class)->delRango($rango), 'id_publico'),
            ],
        ];
    }

    /**
     * F-I09 y F-I10, en espera.
     *
     * @return array<string, mixed>
     */
    private function financiero(Rango $rango): array
    {
        $periodos = [];

        foreach ($this->conSuClave(app(Ingresos::class)->porPeriodo($rango), 'inicio') as $periodo) {
            $periodos[] = [...$periodo, 'ingresos_texto' => Pesos::texto($periodo['ingresos'])];
        }

        return [
            'ingresos_brutos' => ['periodos' => $periodos],
            // La base de la comisión espera DD-020, y la comisión de la pasarela, DD-013 (indicadores-E18).
            'comisiones' => ['en_espera' => ['DD-020', 'DD-013']],
        ];
    }

    /**
     * F-I11, por colonia o por zona de servicio. Las filas van sin su clave: la de cada colonia y la de cada zona llevan
     * llaves internas de la base, que la API no da (ADR-0016, regla 5).
     *
     * @return array<string, mixed>
     */
    private function geografico(Rango $rango, AgrupacionDelMapa $agrupacion): array
    {
        if ($agrupacion === AgrupacionDelMapa::Zona) {
            $zonas = array_map(fn (array $zona): array => [
                ...array_diff_key($zona, ['geometria' => true]),
                'color' => Intensidad::color($zona['intensidad']),
                'geometria' => $zona['geometria'],
            ], array_values(app(PedidosPorZona::class)->delRango($rango)));

            return ['mapa_de_calor' => ['agrupacion' => $agrupacion->value, 'zonas' => $zonas]];
        }

        $colonias = array_map(fn (array $colonia): array => [
            ...array_diff_key($colonia, ['latitud' => true, 'longitud' => true]),
            'color' => Intensidad::color($colonia['intensidad']),
            'latitud' => $colonia['latitud'],
            'longitud' => $colonia['longitud'],
        ], array_values(app(PedidosPorColonia::class)->delRango($rango)));

        return ['mapa_de_calor' => ['agrupacion' => $agrupacion->value, 'colonias' => $colonias]];
    }

    /**
     * Las filas de una clase de dominio, en su orden, con su clave (el primer día del periodo o el id_publico) en un
     * campo con ese nombre, al principio.
     *
     * @param  array<string, array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function conSuClave(array $filas, string $campo): array
    {
        $lista = [];

        foreach ($filas as $clave => $fila) {
            $lista[] = [$campo => (string) $clave, ...$fila];
        }

        return $lista;
    }

    /**
     * Un monto como lo lee el panel, o null si no hay monto.
     */
    private function pesos(?string $monto): ?string
    {
        return $monto === null ? null : Pesos::texto($monto);
    }

    /**
     * Esos filtros, si llegan en la consulta como texto. Uno que llega de otra forma, como desde[]=…, se toma como no
     * dado, igual que un día que Rango no entiende: el rango no tiene más regla que la de Rango.
     *
     * @param  list<string>  $nombres
     * @return array<string, string>
     */
    private function filtros(Request $peticion, array $nombres): array
    {
        return array_filter($peticion->query->all(), fn (mixed $valor, string $filtro): bool => is_string($valor)
            && in_array($filtro, $nombres, true), ARRAY_FILTER_USE_BOTH);
    }
}
