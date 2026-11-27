// Apoyo de las pruebas de los reportes financieros en la app (F-D06; administracion-app-E37 y E38). No es una prueba:
// Jest no lo corre como tal (testPathIgnorePatterns en package.json). Son los manejadores que la prueba registra en el
// servidor simulado de la administración (__tests__/apoyo/administracion.ts), con el contrato del comentario de
// servidor/app/Http/Controllers/Api/Administracion/IngresosController.php y ConciliacionesController.php:
//
// - GET /administracion/ingresos, con desde, hasta y granularidad opcionales: sin ellos, el mes en curso hasta hoy, por
//   día; con ellos, la respuesta que la prueba registró para esos filtros. El rango, las cifras y los textos los calcula
//   el servidor real (App\Indicadores\Rango, App\Finanzas\Ingresos y App\Finanzas\Pesos); aquí van ya calculados;
// - POST /administracion/conciliaciones con desde y hasta: el reporte de marzo, con un pago de cada resultado; otro
//   periodo, sin pagos ni movimientos; 422 en hasta con el mensaje de App\Validacion\RangoDeFechas si la fecha de fin es
//   anterior a la de inicio; 422 con el mensaje del validador de Laravel, en inglés, en la fecha que falta o que no
//   viene en AAAA-MM-DD; y 503 con "No se pudo leer la pasarela" si la pasarela no responde, sin guardar nada.
//
// Los datos son de prueba, los mismos de servidor/tests/Feature/AdministracionEnLaApp/ReportesFinancierosTest.php: marzo
// de 2027, con folios, órdenes y montos ficticios.
import { respuesta, type Peticion, type ServidorDeLaAdministracion } from '../administracion';

export const RUTA_DE_LOS_INGRESOS = '/administracion/ingresos';
export const RUTA_DE_LAS_CONCILIACIONES = '/administracion/conciliaciones';

/** El mensaje de App\Validacion\RangoDeFechas para la fecha de fin anterior a la de inicio (administracion-app-E38). */
export const FIN_ANTERIOR = 'La fecha de fin no puede ser anterior a la de inicio.';

/** El 503 de la conciliación cuando la pasarela no responde (ConciliacionesController::SIN_PASARELA). */
export const SIN_PASARELA = 'No se pudo leer la pasarela';

/**
 * Los mensajes del validador de Laravel para una fecha de la conciliación que falta o que no es fecha, en inglés: el
 * servidor no trae lang/es y RangoDeFechas solo trae el del rango al revés (el contrato de ConciliacionesController).
 */
export const MENSAJES_DEL_VALIDADOR = {
  falta: (campo: string) => `The ${campo} field is required.`,
  noEsFecha: (campo: string) => `The ${campo} field must be a valid date.`,
} as const;

type Granularidad = 'dia' | 'semana' | 'mes';

interface Cifras {
  pagos: number;
  ingresos: string;
  ingresos_texto: string;
}

/** La respuesta de GET /api/administracion/ingresos. */
export interface IngresosDeLaRespuesta {
  rango: { desde: string; hasta: string; granularidad: Granularidad; descripcion: string };
  total: Cifras;
  periodos: (Cifras & { inicio: string; periodo: string })[];
}

function cifras(pagos: number, ingresos: string): Cifras {
  return { pagos, ingresos, ingresos_texto: `$${ingresos}` };
}

/**
 * Sin filtros: el mes en curso hasta hoy, por día, como Rango::deLosFiltros(). Hoy, en el servidor simulado, es el
 * viernes 5 de marzo de 2027, con un pago confirmado.
 */
export const INGRESOS_DEL_MES_EN_CURSO: IngresosDeLaRespuesta = {
  rango: {
    desde: '2027-03-01',
    hasta: '2027-03-05',
    granularidad: 'dia',
    descripcion: 'Del 01/03/2027 al 05/03/2027, por día',
  },
  total: cifras(1, '150.00'),
  periodos: [
    { inicio: '2027-03-01', periodo: 'lun. 01/03/2027', ...cifras(0, '0.00') },
    { inicio: '2027-03-02', periodo: 'mar. 02/03/2027', ...cifras(0, '0.00') },
    { inicio: '2027-03-03', periodo: 'mié. 03/03/2027', ...cifras(0, '0.00') },
    { inicio: '2027-03-04', periodo: 'jue. 04/03/2027', ...cifras(0, '0.00') },
    { inicio: '2027-03-05', periodo: 'vie. 05/03/2027', ...cifras(1, '150.00') },
  ],
};

/** Los filtros de marzo por semana, como los manda la app (administracion-app-E37). */
export const MARZO_POR_SEMANA = { desde: '2027-03-01', hasta: '2027-03-31', granularidad: 'semana' } as const;

/**
 * Marzo por semana: el rango se ajusta de lunes a domingo, así que llega al domingo 4 de abril. Dos pagos confirmados;
 * la orden de marzo sin confirmar no cuenta.
 */
export const INGRESOS_DE_MARZO_POR_SEMANA: IngresosDeLaRespuesta = {
  rango: {
    desde: '2027-03-01',
    hasta: '2027-04-04',
    granularidad: 'semana',
    descripcion: 'Del 01/03/2027 al 04/04/2027, por semana',
  },
  total: cifras(2, '350.00'),
  periodos: [
    { inicio: '2027-03-01', periodo: 'Semana del 01/03/2027', ...cifras(1, '150.00') },
    { inicio: '2027-03-08', periodo: 'Semana del 08/03/2027', ...cifras(0, '0.00') },
    { inicio: '2027-03-15', periodo: 'Semana del 15/03/2027', ...cifras(0, '0.00') },
    { inicio: '2027-03-22', periodo: 'Semana del 22/03/2027', ...cifras(1, '200.00') },
    { inicio: '2027-03-29', periodo: 'Semana del 29/03/2027', ...cifras(0, '0.00') },
  ],
};

type Resultado = 'conciliado' | 'movimiento_sin_pedido' | 'pedido_sin_movimiento' | 'monto_distinto';

/** Los cuatro resultados con su texto, en el orden de App\Finanzas\ResultadoDeConciliacion. */
export const RESULTADOS: readonly { resultado: Resultado; resultado_texto: string }[] = [
  { resultado: 'conciliado', resultado_texto: 'Conciliado' },
  { resultado: 'movimiento_sin_pedido', resultado_texto: 'Movimiento sin pedido' },
  { resultado: 'pedido_sin_movimiento', resultado_texto: 'Pedido pagado sin movimiento' },
  { resultado: 'monto_distinto', resultado_texto: 'Monto que no coincide' },
];

/** Una fila del reporte de la conciliación, como sale en la API. */
export interface FilaDelReporte {
  resultado: Resultado;
  resultado_texto: string;
  orden: string;
  folio: string | null;
  plaza: string | null;
  fecha: string;
  monto_plataforma: string | null;
  monto_plataforma_texto: string | null;
  monto_pasarela: string | null;
  monto_pasarela_texto: string | null;
}

/** La respuesta de POST /api/administracion/conciliaciones. */
export interface ReporteDeLaRespuesta {
  rango: { desde: string; hasta: string; descripcion: string };
  resumen: { resultado: Resultado; resultado_texto: string; cuantos: number }[];
  filas: FilaDelReporte[];
}

const PLAZA = 'Plaza de prueba Durango';

function fila(
  resultado: Resultado,
  datos: Pick<FilaDelReporte, 'orden' | 'folio' | 'fecha'> & { plataforma: string | null; pasarela: string | null },
): FilaDelReporte {
  const texto = RESULTADOS.find((uno) => uno.resultado === resultado)?.resultado_texto as string;
  return {
    resultado,
    resultado_texto: texto,
    orden: datos.orden,
    folio: datos.folio,
    plaza: datos.folio === null ? null : PLAZA,
    fecha: datos.fecha,
    monto_plataforma: datos.plataforma,
    monto_plataforma_texto: datos.plataforma === null ? null : `$${datos.plataforma}`,
    monto_pasarela: datos.pasarela,
    monto_pasarela_texto: datos.pasarela === null ? null : `$${datos.pasarela}`,
  };
}

/** El pago que cuadra: la misma orden y el mismo monto. */
export const CONCILIADO = fila('conciliado', {
  orden: 'ord_prueba_0001',
  folio: '7KQ2M9XA',
  fecha: '05/03/2027 10:00',
  plataforma: '100.00',
  pasarela: '100.00',
});

/** Un pago de la pasarela cuya orden no es de ningún pedido. */
export const MOVIMIENTO_SIN_PEDIDO = fila('movimiento_sin_pedido', {
  orden: 'orden-de-otro-sistema',
  folio: null,
  fecha: '10/03/2027 10:00',
  plataforma: null,
  pasarela: '80.00',
});

/** El pedido pagado en la plataforma sin su movimiento en la pasarela. */
export const PEDIDO_SIN_MOVIMIENTO = fila('pedido_sin_movimiento', {
  orden: 'ord_prueba_0003',
  folio: '3HT8W2QZ',
  fecha: '12/03/2027 10:00',
  plataforma: '120.00',
  pasarela: null,
});

/** La misma orden, con 150 en la plataforma y 175 en la pasarela. */
export const MONTO_DISTINTO = fila('monto_distinto', {
  orden: 'ord_prueba_0004',
  folio: 'F2G7H4JS',
  fecha: '20/03/2027 10:00',
  plataforma: '150.00',
  pasarela: '175.00',
});

/** El periodo de la conciliación de marzo, como lo manda la app (administracion-app-E38). */
export const MARZO = { desde: '2027-03-01', hasta: '2027-03-31' } as const;

/** Las filas de marzo, de la más antigua a la más nueva, como ConciliacionConLaPasarela::reporte(). */
export const FILAS_DE_MARZO = [CONCILIADO, MOVIMIENTO_SIN_PEDIDO, PEDIDO_SIN_MOVIMIENTO, MONTO_DISTINTO];

/** "2027-03-01" como lo lee Rango::dias(): "01/03/2027". */
function alDia(dia: string): string {
  const [anio, mes, diaDelMes] = dia.split('-');
  return `${diaDelMes}/${mes}/${anio}`;
}

/** El reporte de un periodo con sus filas: el resumen cuenta los cuatro resultados, también los que tienen 0. */
export function reporteDe(periodo: { desde: string; hasta: string }, filas: FilaDelReporte[]): ReporteDeLaRespuesta {
  return {
    rango: { ...periodo, descripcion: `Del ${alDia(periodo.desde)} al ${alDia(periodo.hasta)}` },
    resumen: RESULTADOS.map((uno) => ({
      ...uno,
      cuantos: filas.filter((otra) => otra.resultado === uno.resultado).length,
    })),
    filas,
  };
}

const DIA = /^\d{4}-\d{2}-\d{2}$/;

function esObjeto(valor: unknown): valor is Record<string, unknown> {
  return typeof valor === 'object' && valor !== null;
}

/** Los reportes financieros del servidor simulado, con lo que la app les pidió. */
export class ReportesFinancierosDelServidor {
  /** Si la pasarela responde al conciliar; si no, 503 sin guardar nada. */
  pasarelaResponde = true;
  /** Las respuestas de GET ingresos con filtros, por su consulta ("desde=…&hasta=…&granularidad=…"). */
  private readonly ingresosPorFiltros = new Map<string, IngresosDeLaRespuesta>([
    [consultaDe(MARZO_POR_SEMANA), INGRESOS_DE_MARZO_POR_SEMANA],
  ]);
  /** Las filas de cada periodo de la conciliación, por "desde|hasta"; otro periodo, sin pagos ni movimientos. */
  private readonly filasPorPeriodo = new Map<string, FilaDelReporte[]>([[`${MARZO.desde}|${MARZO.hasta}`, FILAS_DE_MARZO]]);

  /** Registra la respuesta de GET ingresos para esos filtros. */
  responderIngresos(filtros: Record<string, string>, ingresos: IngresosDeLaRespuesta): void {
    this.ingresosPorFiltros.set(consultaDe(filtros), ingresos);
  }

  /** Contesta lo de /administracion/ingresos y /administracion/conciliaciones; lo demás, undefined. */
  readonly manejador = ({ metodo, ruta, cuerpo }: Peticion): unknown => {
    const [camino, consulta = ''] = ruta.split('?');
    if (metodo === 'GET' && camino === RUTA_DE_LOS_INGRESOS) {
      if (consulta === '') {
        return respuesta(200, INGRESOS_DEL_MES_EN_CURSO);
      }
      const ingresos = this.ingresosPorFiltros.get(consultaDe(Object.fromEntries(new URLSearchParams(consulta))));
      return ingresos ? respuesta(200, ingresos) : undefined;
    }
    if (metodo === 'POST' && camino === RUTA_DE_LAS_CONCILIACIONES) {
      return this.conciliar(cuerpo);
    }
    return undefined;
  };

  // Como ConciliacionesController: primero la regla del rango, que no lee la pasarela; después la pasarela.
  private conciliar(cuerpo: unknown) {
    const desde = esObjeto(cuerpo) ? cuerpo.desde : undefined;
    const hasta = esObjeto(cuerpo) ? cuerpo.hasta : undefined;
    if (typeof desde !== 'string' || typeof hasta !== 'string' || !DIA.test(desde) || !DIA.test(hasta)) {
      return sinFecha({ desde, hasta });
    }
    if (hasta < desde) {
      return respuesta(422, { message: FIN_ANTERIOR, errors: { hasta: [FIN_ANTERIOR] } });
    }
    if (!this.pasarelaResponde) {
      return respuesta(503, { message: SIN_PASARELA });
    }
    return respuesta(200, reporteDe({ desde, hasta }, this.filasPorPeriodo.get(`${desde}|${hasta}`) ?? []));
  }
}

/**
 * El 422 de una fecha que falta o que no viene en AAAA-MM-DD, con el mensaje del validador de Laravel en la suya y el
 * "message" de Laravel: el primer error y cuántos más hay. El servidor real acepta algunas fechas en otro formato, como
 * "03/01/2027", y concilia otro periodo (el contrato de ConciliacionesController); aquí cualquier otro formato es 422.
 * La app no manda ninguna de las dos: no confirma un periodo con una fecha vacía, a medias o que no existe.
 */
function sinFecha(periodo: { desde: unknown; hasta: unknown }) {
  const errores: Record<string, string[]> = {};
  for (const [campo, valor] of Object.entries(periodo)) {
    if (typeof valor !== 'string' || valor === '') {
      errores[campo] = [MENSAJES_DEL_VALIDADOR.falta(campo)];
    } else if (!DIA.test(valor)) {
      errores[campo] = [MENSAJES_DEL_VALIDADOR.noEsFecha(campo)];
    }
  }
  const mensajes = Object.values(errores).flat();
  const mas = mensajes.length - 1;
  const message = mas > 0 ? `${mensajes[0]} (and ${mas} more error${mas > 1 ? 's' : ''})` : mensajes[0];
  return respuesta(422, { message, errors: errores });
}

/** La consulta de unos filtros, con sus claves en orden, para compararla sin importar el orden en que llegaron. */
function consultaDe(filtros: Record<string, string>): string {
  return Object.keys(filtros)
    .sort()
    .map((clave) => `${clave}=${filtros[clave]}`)
    .join('&');
}

/**
 * Prepara cada prueba del archivo con los reportes financieros del servidor simulado de la administración, ya
 * registrados en él. Devuelve los de la prueba en curso. Va después de usarElServidorDeLaAdministracion().
 */
export function usarLosReportesFinancieros(
  servidor: () => ServidorDeLaAdministracion,
): () => ReportesFinancierosDelServidor {
  let reportes: ReportesFinancierosDelServidor;
  beforeEach(() => {
    reportes = new ReportesFinancierosDelServidor();
    servidor().agregar(reportes.manejador);
  });
  return () => reportes;
}

/** Los filtros de cada GET ingresos que hizo la app, en orden: {} sin filtros. */
export function consultasDeLosIngresos(servidor: ServidorDeLaAdministracion): Record<string, string>[] {
  return servidor
    .peticiones()
    .filter(({ metodo, ruta }) => metodo === 'GET' && ruta.split('?')[0] === RUTA_DE_LOS_INGRESOS)
    .map(({ ruta }) => Object.fromEntries(new URLSearchParams(ruta.split('?')[1] ?? '')));
}

/** El cuerpo de cada POST conciliaciones que hizo la app, en orden. */
export function conciliacionesPedidas(servidor: ServidorDeLaAdministracion): unknown[] {
  return servidor
    .peticiones()
    .filter(({ metodo, ruta }) => metodo === 'POST' && ruta === RUTA_DE_LAS_CONCILIACIONES)
    .map(({ cuerpo }) => cuerpo);
}
