// La consulta de lavanderías cercanas (openspec/changes/lavanderias-cercanas, cercanas-E04): el punto viaja en la
// consulta, con el token de la sesión (sin sesión el servidor no contesta lavanderías, cercanas-E10), y la app no lo
// guarda (design.md, decisión 6). Como las demás consultas, nunca lanza: devuelve un Resultado.
import { pedir, type Resultado } from '../configuracion/api';
import { esObjeto, numero, numeroOVacio } from '../configuracion/lectura';

/**
 * El contrato de la consulta con el servidor (tareas 1.1 y 1.3 de lavanderias-cercanas), en un solo lugar para
 * ajustarlo: GET <ruta>?<latitud>=..&<longitud>=.., y los filtros que se pidan (<distanciaMaxima>=.. y
 * <calificacionMinima>=..), con la sesión, que contesta { "<lista>": [ LavanderiaCercana, … ] }.
 */
export const CONTRATO_DE_CERCANAS = {
  ruta: 'lavanderias/cercanas',
  latitud: 'latitud',
  longitud: 'longitud',
  distanciaMaxima: 'distancia_maxima_metros',
  calificacionMinima: 'calificacion_minima',
  lista: 'lavanderias',
} as const;

/** Un punto del mapa, en grados. */
export interface Punto {
  latitud: number;
  longitud: number;
}

/**
 * Una lavandería como la devuelve la consulta, con los nombres de campo del servidor (tarea 1.1 de
 * lavanderias-cercanas). La API la identifica por su id_publico (design.md, decisión 8).
 */
export interface LavanderiaCercana {
  id_publico: string;
  nombre: string;
  latitud: number;
  longitud: number;
  /** La distancia en línea recta desde el punto de la consulta, en metros, calculada en la base (cercanas-E06). */
  distancia_metros: number;
  /** El promedio de sus calificaciones, o null si aún no tiene. */
  calificacion_promedio: number | null;
  /** Cuántas calificaciones tiene. */
  numero_de_calificaciones: number;
}

/**
 * Los filtros de la búsqueda (cercanas-E16 a E18 y E21): la distancia máxima en línea recta, en metros, y la
 * calificación promedio mínima, de 1 a 5. Sin uno, o en null, la consulta no lo lleva. Se combinan.
 */
export interface FiltrosDeCercanas {
  distanciaMaximaMetros?: number | null;
  calificacionMinima?: number | null;
}

/** GET de las lavanderías cercanas a un punto, con la sesión y los filtros que se pidan (cercanas-E04 y E21). */
export async function consultarCercanas(
  token: string,
  punto: Punto,
  { distanciaMaximaMetros = null, calificacionMinima = null }: FiltrosDeCercanas = {},
): Promise<Resultado<LavanderiaCercana[]>> {
  const contrato = CONTRATO_DE_CERCANAS;
  const parametros: [string, number | null][] = [
    [contrato.latitud, punto.latitud],
    [contrato.longitud, punto.longitud],
    [contrato.distanciaMaxima, distanciaMaximaMetros],
    [contrato.calificacionMinima, calificacionMinima],
  ];
  const consulta = parametros
    .filter((parametro): parametro is [string, number] => parametro[1] !== null)
    .map(([nombre, valor]) => `${nombre}=${encodeURIComponent(valor)}`)
    .join('&');
  const resultado = await pedir<unknown>(`${contrato.ruta}?${consulta}`, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const lavanderias = leerLista(resultado.datos);
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return lavanderias ? { ok: true, datos: lavanderias } : { ok: false, motivo: 'servidor' };
}

function leerLista(datos: unknown): LavanderiaCercana[] | null {
  const lista = esObjeto(datos) ? datos[CONTRATO_DE_CERCANAS.lista] : undefined;
  if (!Array.isArray(lista)) {
    return null;
  }
  const lavanderias = lista.map(leerLavanderia);
  return lavanderias.every((lavanderia) => lavanderia !== null) ? (lavanderias as LavanderiaCercana[]) : null;
}

function leerLavanderia(fila: unknown): LavanderiaCercana | null {
  if (!esObjeto(fila) || typeof fila.id_publico !== 'string' || typeof fila.nombre !== 'string') {
    return null;
  }
  const latitud = numero(fila.latitud);
  const longitud = numero(fila.longitud);
  const distancia = numero(fila.distancia_metros);
  const calificaciones = numero(fila.numero_de_calificaciones);
  const promedio = numeroOVacio(fila.calificacion_promedio);
  if (latitud === null || longitud === null || distancia === null || calificaciones === null || promedio === undefined) {
    return null;
  }
  return {
    id_publico: fila.id_publico,
    nombre: fila.nombre,
    latitud,
    longitud,
    distancia_metros: distancia,
    calificacion_promedio: promedio,
    numero_de_calificaciones: calificaciones,
  };
}
