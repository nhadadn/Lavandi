// Los textos de las descargas en CSV y en Excel de la administración, para revisarlos en un solo lugar. Son los de las
// imágenes 51 a 55 y 70 de la Propuesta 06 de los mocks (C-248): "Descargar en CSV y en Excel" con su rango y su
// granularidad (51), la espera (52), la descarga lista (53) y la pantalla Descargas (54 y 55). Ninguno trae el nombre del
// producto ni el dominio (CLAUDE.md §5; ADR-0022, decisión 17).
import { MENSAJES } from '../../acceso/mensajes';
import { TEXTOS_DEL_HISTORIAL } from '../../historial/textos';
import type { FallaDeLaAdministracion } from '../api';
import { TEXTOS_DE_LA_ADMINISTRACION } from '../textos';
import type { CodigoDelReporte, Descarga, EstadoDeLaDescarga, Formato, Granularidad } from './api';

/** ADR-0022, decisión 15: mientras se genera, la app consulta su estado cada 5 segundos, como "Procesando pago". */
export const CADA_CUANTO_SE_CONSULTA_LA_DESCARGA_MS = 5_000;

export const TEXTOS_DE_LAS_DESCARGAS = {
  titulo: TEXTOS_DE_LA_ADMINISTRACION.secciones.descargas,
  cargando: 'Cargando las descargas…',
  /** Si la lista no llega, debajo del mensaje de la app, como la imagen 05. */
  intentarDeNuevo: 'Intentar de nuevo',
  sinDescargas: 'Todavía no hay descargas. Pide una con «Descargar» en Indicadores, Ingresos o Conciliación.',
  /** El botón o el enlace con el que cada pantalla abre la pieza de la descarga. */
  descargar: 'Descargar',
  // El rango y la granularidad que se confirman antes de pedirla (imagen 51).
  descargarEnCsvYEnExcel: 'Descargar en CSV y en Excel',
  desde: 'Desde',
  hasta: 'Hasta',
  verPor: 'Ver por',
  granularidades: { dia: 'Día', semana: 'Semana', mes: 'Mes' } satisfies Record<Granularidad, string>,
  pedirLaDescarga: 'Pedir la descarga',
  cancelar: TEXTOS_DE_LA_ADMINISTRACION.cancelar,
  /** Una fecha a medias o que no existe, antes de mandarla: el mismo texto del filtro de "Mis pedidos". */
  fechaInvalida: TEXTOS_DEL_HISTORIAL.fechaInvalida,
  // La espera (imagen 52) y la que no se pudo generar (imagen 54).
  seEstaGenerando: 'El archivo se está generando.',
  seRevisa: `Se revisa cada ${CADA_CUANTO_SE_CONSULTA_LA_DESCARGA_MS / 1000} s mientras esta pantalla está abierta.`,
  noSePudoGenerar: 'No se pudo generar el archivo.',
  pedirlaOtraVez: 'Pedirla otra vez',
  /** El estado de cada descarga en la pantalla Descargas (imagen 54). */
  estados: {
    generandose: 'Generándose',
    lista: 'Lista',
    no_se_pudo: 'No se pudo generar',
  } satisfies Record<EstadoDeLaDescarga, string>,
  guardar: { csv: 'Guardar .csv', xlsx: 'Guardar .xlsx' } satisfies Record<Formato, string>,
} as const;

/**
 * Los dos reportes que son una pantalla del módulo: en la app llevan el nombre de su pantalla, como en la imagen 54
 * ("Ingresos" y "Conciliación") y en el texto de la pantalla Descargas sin descargas.
 */
const NOMBRE_DE_SU_PANTALLA: Partial<Record<CodigoDelReporte, string>> = {
  'ingresos-por-periodo': TEXTOS_DE_LA_ADMINISTRACION.secciones.ingresos,
  'conciliacion-con-la-pasarela': TEXTOS_DE_LA_ADMINISTRACION.secciones.conciliacion,
};

/**
 * El nombre de una descarga en la app: el que da el servidor, como en el botón del panel ("Pedidos totales"), salvo los
 * ingresos y la conciliación, que llevan el de su pantalla.
 */
export function nombreDeLaDescarga({ reporte, titulo }: Pick<Descarga, 'reporte' | 'titulo'>): string {
  return NOMBRE_DE_SU_PANTALLA[reporte] ?? titulo;
}

/**
 * El mensaje de una falla de la administración: sin conexión, el de la app; la descarga que no existe (404) o un 422 de
 * un campo que no se ve, el del servidor; lo demás, el general de la app.
 */
export function mensajeDeLaFalla(falla: FallaDeLaAdministracion): string {
  switch (falla.motivo) {
    case 'sin-conexion':
      return MENSAJES.sinConexion;
    case 'no-existe':
    case 'conflicto':
    case 'invalido':
      return falla.mensaje || MENSAJES.servidor;
    default:
      return MENSAJES.servidor;
  }
}

/** "Se guardó el archivo .csv en la carpeta Descargas.", debajo de los botones de la pieza (imagen 53). */
export function seGuardo(formato: Formato, carpeta: string): string {
  return `Se guardó el archivo .${formato} en la carpeta ${carpeta}.`;
}

/** "Se guardó el archivo .csv de Pedidos totales en la carpeta Descargas.", arriba de la pantalla Descargas (imagen 54). */
export function seGuardoDe(formato: Formato, titulo: string, carpeta: string): string {
  return `Se guardó el archivo .${formato} de ${titulo} en la carpeta ${carpeta}.`;
}

/**
 * "Tus descargas de los últimos 7 días.", arriba de la pantalla Descargas (imagen 54), con los días de vida que da el
 * servidor (EXPORTACION_DIAS_DE_VIDA; exportacion-E12). Con uno solo, "del último día".
 */
export function vigencia(diasDeVida: number): string {
  return diasDeVida === 1 ? 'Tus descargas del último día.' : `Tus descargas de los últimos ${diasDeVida} días.`;
}

/** "26/10/2026": un día AAAA-MM-DD como se escribe en el campo y como se lee en el rango. */
export function fechaEnElCampo(fecha: string): string {
  const [anio, mes, dia] = fecha.split('-');
  return `${dia}/${mes}/${anio}`;
}

/**
 * El rango de una descarga en un renglón, como en las imágenes 52 y 54: "Del 26/10/2026 al 29/11/2026, por semana", o
 * sin la granularidad si el reporte no la lleva, como la conciliación, o si no se sabe. Los días son los que el servidor
 * da con la descarga: los del archivo, ya ajustados a periodos completos (App\Indicadores\Rango, inicioDe() y finDe()),
 * que pueden no ser los que se escribieron. La granularidad es la que se pidió; la descarga no la trae (ADR-0022,
 * decisión 15).
 */
export function rangoEnTexto(desde: string, hasta: string, granularidad?: Granularidad): string {
  const dias = `Del ${fechaEnElCampo(desde)} al ${fechaEnElCampo(hasta)}`;
  return granularidad
    ? `${dias}, por ${TEXTOS_DE_LAS_DESCARGAS.granularidades[granularidad].toLowerCase()}`
    : dias;
}

const dosDigitos = (numero: number) => String(numero).padStart(2, '0');

/**
 * Cuándo se pidió, en la hora del teléfono, como en la imagen 54: "Pedida hoy, 10:42", "Pedida ayer, 17:20" y, antes,
 * "Pedida el 20/11/2026, 09:15".
 */
export function pedidaEnTexto(pedidaEn: string, ahora: Date = new Date()): string {
  const pedida = new Date(pedidaEn);
  const hora = `${dosDigitos(pedida.getHours())}:${dosDigitos(pedida.getMinutes())}`;
  const mismoDia = (uno: Date, otro: Date) =>
    uno.getFullYear() === otro.getFullYear() && uno.getMonth() === otro.getMonth() && uno.getDate() === otro.getDate();
  const ayer = new Date(ahora.getFullYear(), ahora.getMonth(), ahora.getDate() - 1);
  if (mismoDia(pedida, ahora)) {
    return `Pedida hoy, ${hora}`;
  }
  if (mismoDia(pedida, ayer)) {
    return `Pedida ayer, ${hora}`;
  }
  const dia = `${dosDigitos(pedida.getDate())}/${dosDigitos(pedida.getMonth() + 1)}/${pedida.getFullYear()}`;
  return `Pedida el ${dia}, ${hora}`;
}
