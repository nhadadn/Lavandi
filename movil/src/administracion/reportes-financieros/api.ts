// Los reportes financieros de la administración en la app (F-D06; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo
// D #6, "Ingresos por periodo, comisiones y conciliación con Conekta"; administracion-app-E37 y E38): los ingresos del
// periodo y la conciliación de un periodo con la pasarela.
//
// El contrato es el del comentario de servidor/app/Http/Controllers/Api/Administracion/IngresosController.php y
// ConciliacionesController.php, copiado aquí:
//
//     GET /api/administracion/ingresos?desde=AAAA-MM-DD&hasta=AAAA-MM-DD&granularidad=dia|semana|mes
//         Los tres opcionales: sin ellos, el mes en curso hasta hoy, por día. Un día que no se entiende se toma como no
//         dado y un rango al revés se voltea: nunca responde 422.
//         200 { rango: { desde, hasta, granularidad, descripcion },          el rango que se usó, ajustado
//               total: { pagos, ingresos: "350.00", ingresos_texto: "$350.00" },
//               periodos: [ { inicio, periodo: "Semana del 01/03/2027", pagos, ingresos, ingresos_texto } ] }
//
//     POST /api/administracion/conciliaciones   { desde: AAAA-MM-DD, hasta: AAAA-MM-DD }, los dos días incluidos; los
//         dos obligatorios, en AAAA-MM-DD (ver el 422)
//         200 { rango: { desde, hasta, descripcion },
//               resumen: [ { resultado, resultado_texto, cuantos } ],     siempre los cuatro, en su orden
//               filas: [ { resultado, resultado_texto, orden, folio, plaza, fecha, monto_plataforma,
//                          monto_plataforma_texto, monto_pasarela, monto_pasarela_texto } ] }
//         422 { message, errors: { hasta: ["La fecha de fin no puede ser anterior a la de inicio."] } }
//             también sin alguna de las dos fechas, con una que no es fecha o con una que no viene en AAAA-MM-DD
//             ("03/01/2027", "1 March 2027"), en la suya: la regla del rango pide ese formato (date_format:Y-m-d en
//             App\Validacion\RangoDeFechas), así la conciliación nunca corre sobre otro periodo. No lee la pasarela.
//             En esos casos el mensaje es el del validador de Laravel, en inglés: RangoDeFechas solo trae el del rango
//             al revés
//         503 { message: "No se pudo leer la pasarela" }: no guarda nada; se puede conciliar otra vez más tarde
//
// Por eso la pantalla no confirma un periodo con una fecha vacía, a medias o que no existe: lo dice junto a su campo,
// en español, y no manda nada (app/administracion/conciliacion.tsx). Lo único que deja al 422 es el rango al revés.
//
// Las cifras, el rango ajustado, los textos de cada monto y de cada resultado y la regla del rango los da el servidor,
// con las mismas clases que el panel (ADR-0022, decisión 9): la app no calcula ni valida ninguno. Las comisiones del
// periodo (administracion-app-E40, DD-020), los reembolsos y ajustes (E39, DD-012 y DD-016) y la comisión de la
// pasarela (E41, DD-013) no vienen en la respuesta.
//
// Va sobre el cliente de la administración (src/administracion/api.ts), que pone el token y borra la sesión con un 401.
// Como las demás consultas, nunca lanza: devuelve un Resultado. Una respuesta fuera del contrato no se pinta a medias:
// cuenta como falla del servidor.
import { esObjeto } from '../../configuracion/lectura';
import type { FallaDeLaAdministracion, PedirALaAdministracion, ResultadoDeLaAdministracion } from '../api';
import { GRANULARIDADES, type Granularidad, type RangoAjustado } from '../rango/rango';

/** Las rutas y los campos de los reportes financieros, bajo /api/administracion, en un solo lugar para ajustarlos. */
export const CONTRATO_DE_LOS_REPORTES_FINANCIEROS = {
  ingresos: 'ingresos',
  conciliaciones: 'conciliaciones',
  desde: 'desde',
  hasta: 'hasta',
  granularidad: 'granularidad',
  /** El mensaje del 503 de la conciliación cuando la pasarela no responde (ConciliacionesController::SIN_PASARELA). */
  sinPasarela: 'No se pudo leer la pasarela',
} as const;

/** Cuántos pagos confirmados y cuánto suman: en pesos con dos decimales y como lo lee el panel. */
export interface CifrasDeLosIngresos {
  pagos: number;
  ingresos: string;
  ingresos_texto: string;
}

/** Un periodo del rango: su primer día y cómo se lee ("lun. 01/03/2027", "Semana del 01/03/2027" o "marzo 2027"). */
export interface PeriodoDeLosIngresos extends CifrasDeLosIngresos {
  inicio: string;
  periodo: string;
}

/** La respuesta de GET ingresos (administracion-app-E37). */
export interface IngresosDelPeriodo {
  rango: RangoAjustado;
  total: CifrasDeLosIngresos;
  periodos: PeriodoDeLosIngresos[];
}

/** Los filtros de los ingresos como los recibe el servidor; el que no va, lo pone el servidor. */
export interface FiltrosDeLosIngresos {
  desde: string;
  hasta: string;
  granularidad: Granularidad;
}

/** Los cuatro resultados de una fila de la conciliación, como App\Finanzas\ResultadoDeConciliacion. */
export const RESULTADOS_DE_LA_CONCILIACION = [
  'conciliado',
  'movimiento_sin_pedido',
  'pedido_sin_movimiento',
  'monto_distinto',
] as const;
export type ResultadoDeLaConciliacion = (typeof RESULTADOS_DE_LA_CONCILIACION)[number];

/** Cuántas filas hay de un resultado. */
export interface ConteoDeLaConciliacion {
  resultado: ResultadoDeLaConciliacion;
  resultado_texto: string;
  cuantos: number;
}

/**
 * Una fila del reporte: un pago de la plataforma o un movimiento de la pasarela, con su resultado. Sin pedido, el folio
 * y la plaza son null; cada monto es null en el lado que no lo tiene, y su texto también.
 */
export interface FilaDeLaConciliacion {
  resultado: ResultadoDeLaConciliacion;
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

/** La respuesta de POST conciliaciones (administracion-app-E38). */
export interface ReporteDeLaConciliacion {
  rango: { desde: string; hasta: string; descripcion: string };
  resumen: ConteoDeLaConciliacion[];
  filas: FilaDeLaConciliacion[];
}

/** Por qué no salió la conciliación: las fallas de la administración, y la pasarela que no respondió (503). */
export type FallaDeLaConciliacion = FallaDeLaAdministracion | { ok: false; motivo: 'sin-pasarela' };

type Resultado<T> = ResultadoDeLaAdministracion<T>;

const FUERA_DEL_CONTRATO = { ok: false, motivo: 'servidor' } as const;

/** GET ingresos: sin filtros, el rango que da el servidor; con ellos, el que se eligió (administracion-app-E37). */
export async function consultarIngresos(
  pedir: PedirALaAdministracion,
  filtros: FiltrosDeLosIngresos | null,
): Promise<Resultado<IngresosDelPeriodo>> {
  const { ingresos, desde, hasta, granularidad } = CONTRATO_DE_LOS_REPORTES_FINANCIEROS;
  const consulta = filtros
    ? [
        [desde, filtros.desde],
        [hasta, filtros.hasta],
        [granularidad, filtros.granularidad],
      ]
        .filter(([, valor]) => valor !== '')
        .map(([campo, valor]) => `${campo}=${encodeURIComponent(valor)}`)
        .join('&')
    : '';
  const resultado = await pedir<unknown>(consulta ? `${ingresos}?${consulta}` : ingresos);
  if (!resultado.ok) {
    return resultado;
  }
  const leidos = leerIngresos(resultado.datos);
  return leidos ? { ok: true, datos: leidos } : FUERA_DEL_CONTRATO;
}

/**
 * POST conciliaciones con el periodo, los dos días en AAAA-MM-DD: el servidor lee la pasarela, guarda sus movimientos y
 * responde el reporte (administracion-app-E38). El 422 sale con el error de cada campo; el 503, como la pasarela que no
 * respondió.
 */
export async function conciliarPeriodo(
  pedir: PedirALaAdministracion,
  periodo: { desde: string; hasta: string },
): Promise<{ ok: true; datos: ReporteDeLaConciliacion } | FallaDeLaConciliacion> {
  const { conciliaciones, desde, hasta, sinPasarela } = CONTRATO_DE_LOS_REPORTES_FINANCIEROS;
  const resultado = await pedir<unknown>(conciliaciones, {
    metodo: 'POST',
    cuerpo: { [desde]: periodo.desde, [hasta]: periodo.hasta },
  });
  if (!resultado.ok) {
    // El cliente de la administración no pasa el código de este error: el 503 de la pasarela se reconoce por su
    // mensaje, el texto literal de ConciliacionesController::SIN_PASARELA. Si ese texto cambia, aquí también.
    return resultado.motivo === 'servidor' && resultado.mensaje === sinPasarela
      ? { ok: false, motivo: 'sin-pasarela' }
      : resultado;
  }
  const reporte = leerReporte(resultado.datos);
  return reporte ? { ok: true, datos: reporte } : FUERA_DEL_CONTRATO;
}

function esTexto(valor: unknown): valor is string {
  return typeof valor === 'string';
}

function esTextoONulo(valor: unknown): valor is string | null {
  return valor === null || typeof valor === 'string';
}

function esCuenta(valor: unknown): valor is number {
  return typeof valor === 'number' && Number.isInteger(valor) && valor >= 0;
}

function esGranularidad(valor: unknown): valor is Granularidad {
  return GRANULARIDADES.includes(valor as Granularidad);
}

function esResultado(valor: unknown): valor is ResultadoDeLaConciliacion {
  return RESULTADOS_DE_LA_CONCILIACION.includes(valor as ResultadoDeLaConciliacion);
}

function leerCifras(valor: unknown): CifrasDeLosIngresos | null {
  if (!esObjeto(valor) || !esCuenta(valor.pagos) || !esTexto(valor.ingresos) || !esTexto(valor.ingresos_texto)) {
    return null;
  }
  return { pagos: valor.pagos, ingresos: valor.ingresos, ingresos_texto: valor.ingresos_texto };
}

function leerPeriodo(valor: unknown): PeriodoDeLosIngresos | null {
  const cifras = leerCifras(valor);
  if (!cifras || !esObjeto(valor) || !esTexto(valor.inicio) || !esTexto(valor.periodo)) {
    return null;
  }
  return { inicio: valor.inicio, periodo: valor.periodo, ...cifras };
}

function leerIngresos(valor: unknown): IngresosDelPeriodo | null {
  if (!esObjeto(valor) || !esObjeto(valor.rango) || !Array.isArray(valor.periodos)) {
    return null;
  }
  const { rango } = valor;
  if (!esTexto(rango.desde) || !esTexto(rango.hasta) || !esGranularidad(rango.granularidad) || !esTexto(rango.descripcion)) {
    return null;
  }
  const total = leerCifras(valor.total);
  const periodos = valor.periodos.map(leerPeriodo);
  if (!total || !periodos.every((periodo): periodo is PeriodoDeLosIngresos => periodo !== null)) {
    return null;
  }
  return {
    rango: { desde: rango.desde, hasta: rango.hasta, granularidad: rango.granularidad, descripcion: rango.descripcion },
    total,
    periodos,
  };
}

function leerConteo(valor: unknown): ConteoDeLaConciliacion | null {
  if (!esObjeto(valor) || !esResultado(valor.resultado) || !esTexto(valor.resultado_texto) || !esCuenta(valor.cuantos)) {
    return null;
  }
  return { resultado: valor.resultado, resultado_texto: valor.resultado_texto, cuantos: valor.cuantos };
}

function leerFila(valor: unknown): FilaDeLaConciliacion | null {
  if (
    !esObjeto(valor) ||
    !esResultado(valor.resultado) ||
    !esTexto(valor.resultado_texto) ||
    !esTexto(valor.orden) ||
    !esTextoONulo(valor.folio) ||
    !esTextoONulo(valor.plaza) ||
    !esTexto(valor.fecha) ||
    !esTextoONulo(valor.monto_plataforma) ||
    !esTextoONulo(valor.monto_plataforma_texto) ||
    !esTextoONulo(valor.monto_pasarela) ||
    !esTextoONulo(valor.monto_pasarela_texto)
  ) {
    return null;
  }
  return {
    resultado: valor.resultado,
    resultado_texto: valor.resultado_texto,
    orden: valor.orden,
    folio: valor.folio,
    plaza: valor.plaza,
    fecha: valor.fecha,
    monto_plataforma: valor.monto_plataforma,
    monto_plataforma_texto: valor.monto_plataforma_texto,
    monto_pasarela: valor.monto_pasarela,
    monto_pasarela_texto: valor.monto_pasarela_texto,
  };
}

function leerReporte(valor: unknown): ReporteDeLaConciliacion | null {
  if (!esObjeto(valor) || !esObjeto(valor.rango) || !Array.isArray(valor.resumen) || !Array.isArray(valor.filas)) {
    return null;
  }
  const { rango } = valor;
  if (!esTexto(rango.desde) || !esTexto(rango.hasta) || !esTexto(rango.descripcion)) {
    return null;
  }
  const resumen = valor.resumen.map(leerConteo);
  const filas = valor.filas.map(leerFila);
  if (
    !resumen.every((conteo): conteo is ConteoDeLaConciliacion => conteo !== null) ||
    !filas.every((fila): fila is FilaDeLaConciliacion => fila !== null)
  ) {
    return null;
  }
  return { rango: { desde: rango.desde, hasta: rango.hasta, descripcion: rango.descripcion }, resumen, filas };
}
