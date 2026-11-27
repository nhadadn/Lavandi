// La consulta del perfil de una lavandería (openspec/changes/perfil-de-lavanderia, perfil-lavanderia-E09): por su
// id_publico, con el token de la sesión (sin sesión el servidor no contesta, perfil-lavanderia-E02). Como las demás
// consultas, nunca lanza: devuelve un Resultado.
import { pedir, type Resultado } from '../configuracion/api';
import { esObjeto, fotoFirmada, numero, numeroOVacio, type FotoFirmada } from '../configuracion/lectura';

/**
 * El contrato de la consulta con el servidor (tarea 1.2 de perfil-de-lavanderia), en un solo lugar para ajustarlo:
 * GET <ruta>/<id_publico>, con la sesión, que contesta { "<clave>": PerfilDeLavanderia }.
 */
export const CONTRATO_DEL_PERFIL = {
  ruta: 'lavanderias',
  clave: 'lavanderia',
} as const;

/** Los tres tipos de servicio del contrato (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #6). */
export const CODIGOS_DE_SERVICIO = ['estandar', 'planchado', 'seco'] as const;
export type CodigoDeServicio = (typeof CODIGOS_DE_SERVICIO)[number];

/** Las estrellas de una calificación van de 1 a 5 (perfil-lavanderia-E17). */
export const ESTRELLAS_MAXIMAS = 5;
const ESTRELLAS_MINIMAS = 1;

/** Un servicio que la lavandería ofrece, con su precio y su unidad (perfil-lavanderia-E05). */
export interface ServicioDeLavanderia {
  codigo: CodigoDeServicio;
  nombre: string;
  /** En pesos, como texto con dos decimales: el dinero no viaja como número con decimales. */
  precio: string;
  unidad: { codigo: string; nombre: string };
}

/** Una reseña: sus estrellas, su comentario si lo tiene y su fecha, sin nada de quien calificó (perfil-lavanderia-E08). */
export interface Resena {
  estrellas: number;
  comentario: string | null;
  /** ISO 8601. */
  fecha: string;
}

/** El perfil como lo devuelve el servidor, con sus nombres de campo. */
export interface PerfilDeLavanderia {
  id_publico: string;
  nombre: string;
  descripcion: string | null;
  /**
   * Todas sus fotos, en el orden del servidor; la primera es la principal. Vacía si no tiene. Cada URL está firmada y
   * vence: al volver a entrar al perfil se pide otra vez y llegan nuevas (perfil-lavanderia-E03 y E04).
   */
  fotos: FotoFirmada[];
  servicios: ServicioDeLavanderia[];
  /** El promedio de 1 a 5, o null si aún no tiene calificaciones (perfil-lavanderia-E07). */
  calificacion_promedio: number | null;
  numero_de_calificaciones: number;
  /** De la más reciente a la más antigua. */
  resenas: Resena[];
}

/** GET del perfil de una lavandería por su id_publico, con la sesión (perfil-lavanderia-E09). */
export async function consultarPerfil(token: string, idPublico: string): Promise<Resultado<PerfilDeLavanderia>> {
  const { ruta, clave } = CONTRATO_DEL_PERFIL;
  const resultado = await pedir<unknown>(`${ruta}/${encodeURIComponent(idPublico)}`, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const perfil = esObjeto(resultado.datos) ? leerPerfil(resultado.datos[clave]) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return perfil ? { ok: true, datos: perfil } : { ok: false, motivo: 'servidor' };
}

function leerPerfil(datos: unknown): PerfilDeLavanderia | null {
  if (!esObjeto(datos) || typeof datos.id_publico !== 'string' || typeof datos.nombre !== 'string') {
    return null;
  }
  const descripcion = datos.descripcion === null || typeof datos.descripcion === 'string' ? datos.descripcion : undefined;
  const promedio = numeroOVacio(datos.calificacion_promedio);
  const calificaciones = numero(datos.numero_de_calificaciones);
  const fotos = todas(datos.fotos, fotoFirmada);
  const servicios = todas(datos.servicios, leerServicio);
  const resenas = todas(datos.resenas, leerResena);
  if (
    descripcion === undefined ||
    promedio === undefined ||
    calificaciones === null ||
    !fotos ||
    !servicios ||
    !resenas
  ) {
    return null;
  }
  return {
    id_publico: datos.id_publico,
    nombre: datos.nombre,
    descripcion,
    fotos,
    servicios,
    calificacion_promedio: promedio,
    numero_de_calificaciones: calificaciones,
    resenas,
  };
}

function leerServicio(fila: unknown): ServicioDeLavanderia | null {
  if (
    !esObjeto(fila) ||
    !esCodigoDeServicio(fila.codigo) ||
    typeof fila.nombre !== 'string' ||
    typeof fila.precio !== 'string' ||
    !/^\d+(\.\d+)?$/.test(fila.precio) ||
    !esObjeto(fila.unidad) ||
    typeof fila.unidad.codigo !== 'string' ||
    typeof fila.unidad.nombre !== 'string'
  ) {
    return null;
  }
  return {
    codigo: fila.codigo,
    nombre: fila.nombre,
    precio: fila.precio,
    unidad: { codigo: fila.unidad.codigo, nombre: fila.unidad.nombre },
  };
}

function leerResena(fila: unknown): Resena | null {
  if (!esObjeto(fila) || typeof fila.fecha !== 'string' || Number.isNaN(Date.parse(fila.fecha))) {
    return null;
  }
  const estrellas = numero(fila.estrellas);
  const comentario = fila.comentario === null || typeof fila.comentario === 'string' ? fila.comentario : undefined;
  if (
    estrellas === null ||
    !Number.isInteger(estrellas) ||
    estrellas < ESTRELLAS_MINIMAS ||
    estrellas > ESTRELLAS_MAXIMAS ||
    comentario === undefined
  ) {
    return null;
  }
  return { estrellas, comentario, fecha: fila.fecha };
}

function esCodigoDeServicio(valor: unknown): valor is CodigoDeServicio {
  return CODIGOS_DE_SERVICIO.some((codigo) => codigo === valor);
}

// Una lista donde cada fila se lee bien; si una no, la lista entera no sirve.
function todas<T>(lista: unknown, leer: (fila: unknown) => T | null): T[] | null {
  if (!Array.isArray(lista)) {
    return null;
  }
  const leidas = lista.map(leer);
  return leidas.every((fila) => fila !== null) ? (leidas as T[]) : null;
}
