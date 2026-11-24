// La consulta de "Mis pedidos" (openspec/changes/historial-y-calificacion, historial-E01, E03 y E04): la lista de los
// pedidos del usuario, con el token de la sesión, y, si hay filtro, solo los de un rango de días. El servidor solo
// entrega los del usuario (historial-E02) y solo los confirmados, así que el folio siempre viene. Cada pedido trae lo que
// pinta la lista: el folio, la fecha, la lavandería, el estado y su calificación del servicio, si ya la tiene
// (historial-E06). Y la calificación del servicio de un pedido, de 1 a 5 estrellas con un comentario opcional
// (historial-E06 a E08), con el "Contrato de la API: la calificación" de
// openspec/changes/historial-y-calificacion/design.md. Como las demás consultas, nunca lanza: devuelve un Resultado.
import { pedir, pedirConEstado, type Falla, type Resultado } from '../configuracion/api';
import { esObjeto } from '../configuracion/lectura';
import { leerEstado, type EstadoDelPedido } from '../estados/api';

/**
 * El contrato de la consulta con el servidor, en un solo lugar para ajustarlo: GET <ruta>?<desde>=AAAA-MM-DD&<hasta>=
 * AAAA-MM-DD, con la sesión, que contesta { "<clave>": PedidoDelHistorial[] }, del más reciente al más antiguo. Las dos
 * fechas son opcionales y los dos días entran; una fecha que el servidor no acepta responde 422 con errors.<desde> o
 * errors.<hasta>.
 */
export const CONTRATO_DEL_HISTORIAL = {
  ruta: 'pedidos',
  clave: 'pedidos',
  desde: 'desde',
  hasta: 'hasta',
  // POST <ruta>/<id_publico>/<calificacion>, con { estrellas, comentario }; responde { "<calificacion>": … } con 201.
  calificacion: 'calificacion',
  estrellas: 'estrellas',
  comentario: 'comentario',
} as const;

/** Las estrellas van de 1 a 5 (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14). */
export const ESTRELLAS_DE_LA_CALIFICACION = [1, 2, 3, 4, 5] as const;

/** La calificación del servicio de un pedido: sus estrellas y su comentario, o null si no lo tiene. */
export interface CalificacionDelPedido {
  estrellas: number;
  comentario: string | null;
}

/** Un rango de días de la plaza, AAAA-MM-DD, los dos incluidos (historial-E03). */
export interface RangoDeFechas {
  desde: string;
  hasta: string;
}

/** Un pedido de la lista, con los nombres de campo del servidor. */
export interface PedidoDelHistorial {
  id_publico: string;
  folio: string;
  /** AAAA-MM-DD: la fecha de la recolección en la hora de la plaza, que se muestra tal cual, sin pasarla a otra zona. */
  fecha: string;
  lavanderia: { id_publico: string; nombre: string };
  /** Con visible_usuario en false, la app no lo nombra. */
  estado: EstadoDelPedido;
  /** null mientras no se califica (historial-E06). Un servidor que todavía no lo manda cuenta como sin calificar. */
  calificacion: CalificacionDelPedido | null;
}

const FECHA = /^\d{4}-\d{2}-\d{2}$/;

/** GET de la lista de pedidos del usuario, con la sesión (historial-E01); con un rango, solo los de esos días (E03). */
export async function consultarHistorial(
  token: string,
  rango: RangoDeFechas | null = null,
): Promise<Resultado<PedidoDelHistorial[]>> {
  const { ruta, clave, desde, hasta } = CONTRATO_DEL_HISTORIAL;
  const consulta = rango
    ? `?${desde}=${encodeURIComponent(rango.desde)}&${hasta}=${encodeURIComponent(rango.hasta)}`
    : '';
  const resultado = await pedir<unknown>(`${ruta}${consulta}`, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const filas = esObjeto(resultado.datos) ? resultado.datos[clave] : null;
  const pedidos = Array.isArray(filas) ? filas.map(leerPedido) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  if (pedidos === null || !pedidos.every((pedido) => pedido !== null)) {
    return { ok: false, motivo: 'servidor' };
  }
  return { ok: true, datos: pedidos as PedidoDelHistorial[] };
}

function leerPedido(datos: unknown): PedidoDelHistorial | null {
  if (
    !esObjeto(datos) ||
    typeof datos.id_publico !== 'string' ||
    typeof datos.folio !== 'string' ||
    typeof datos.fecha !== 'string' ||
    !FECHA.test(datos.fecha) ||
    !esObjeto(datos.lavanderia) ||
    typeof datos.lavanderia.id_publico !== 'string' ||
    typeof datos.lavanderia.nombre !== 'string'
  ) {
    return null;
  }
  const estado = leerEstado(datos.estado);
  const calificacion = datos.calificacion === undefined ? null : leerCalificacion(datos.calificacion);
  if (!estado || calificacion === undefined) {
    return null;
  }
  return {
    id_publico: datos.id_publico,
    folio: datos.folio,
    fecha: datos.fecha,
    lavanderia: { id_publico: datos.lavanderia.id_publico, nombre: datos.lavanderia.nombre },
    estado,
    calificacion,
  };
}

/** La calificación leída campo por campo: null si no la tiene, undefined si no es del contrato. */
function leerCalificacion(datos: unknown): CalificacionDelPedido | null | undefined {
  if (datos === null) {
    return null;
  }
  if (
    !esObjeto(datos) ||
    !ESTRELLAS_DE_LA_CALIFICACION.includes(datos.estrellas as (typeof ESTRELLAS_DE_LA_CALIFICACION)[number]) ||
    (datos.comentario !== null && typeof datos.comentario !== 'string')
  ) {
    return undefined;
  }
  return { estrellas: datos.estrellas as number, comentario: datos.comentario };
}

/** Lo que la app manda al calificar: las estrellas y el comentario tal como se escribió, o null sin comentario. */
export interface SolicitudDeCalificacion {
  estrellas: number;
  comentario: string | null;
}

/**
 * Por qué no se guardó la calificación. Además de las fallas generales (con 422, el error de cada campo): el pedido no
 * existe o es de otra cuenta (404), o el servidor no la acepta y lo dice con un mensaje para la persona (409: todavía no
 * se puede calificar o ya está calificado, la lectura provisional de BR-25).
 */
export type FallaAlCalificar =
  | Falla
  | { ok: false; motivo: 'no-encontrado' }
  | { ok: false; motivo: 'no-se-puede'; mensaje: string };

/** POST de la calificación del servicio de un pedido de la cuenta, con la sesión (historial-E06 a E08). */
export async function calificarPedido(
  token: string,
  idPublico: string,
  solicitud: SolicitudDeCalificacion,
): Promise<{ ok: true; datos: CalificacionDelPedido } | FallaAlCalificar> {
  const { ruta, calificacion: clave } = CONTRATO_DEL_HISTORIAL;
  const { resultado, estado } = await pedirConEstado<unknown>(
    `${ruta}/${encodeURIComponent(idPublico)}/${clave}`,
    { metodo: 'POST', token, cuerpo: solicitud },
  );
  if (!resultado.ok) {
    if (estado === 404) {
      return { ok: false, motivo: 'no-encontrado' };
    }
    if (estado === 409 && resultado.motivo === 'servidor' && resultado.mensaje) {
      return { ok: false, motivo: 'no-se-puede', mensaje: resultado.mensaje };
    }
    return resultado;
  }
  const guardada = esObjeto(resultado.datos) ? leerCalificacion(resultado.datos[clave]) : undefined;
  return guardada ? { ok: true, datos: guardada } : { ok: false, motivo: 'servidor' };
}
