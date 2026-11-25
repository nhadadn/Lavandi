// Los textos del perfil de la lavandería (perfil-lavanderia-E09 y E10), juntos para revisarlos en un solo lugar.
import { calificacionEnTexto } from '../cercanas/textos';
import { ESTRELLAS_MAXIMAS, type Resena, type ServicioDeLavanderia } from './api';

export const TEXTOS_DEL_PERFIL = {
  encabezado: 'Lavandería',
  cargando: 'Cargando la lavandería…',
  servicios: 'Servicios y precios',
  resenas: 'Reseñas',
  // DD-135, punto 3: las tres más recientes y "ver todas" cuando hay más.
  verTodas: 'Ver todas las reseñas',
  // El acceso para pedir en esa lavandería (perfil-lavanderia-E09), como en el mock A05.
  pedirAqui: 'Pedir aquí',
} as const;

/** Cada foto para el lector de pantalla: "Foto 2 de 3 de la lavandería" (perfil-lavanderia-E09). */
export function fotoParaLeer(numero: number, total: number): string {
  return `Foto ${numero} de ${total} de la lavandería`;
}

/** El contador de las fotos, solo para la vista: "2 / 3", como en el mock a05-lavanderia. */
export function contadorDeFotos(numero: number, total: number): string {
  return `${numero} / ${total}`;
}

/** El precio de un servicio en su unidad, como lo entrega el servidor: "$45.00 por kilo" (perfil-lavanderia-E05). */
export function precioEnTexto({ precio, unidad }: Pick<ServicioDeLavanderia, 'precio' | 'unidad'>): string {
  return `$${precio} por ${unidad.nombre}`;
}

/**
 * La calificación promedio con el número de calificaciones: "Calificación 4.5 · 4 calificaciones". Sin calificaciones,
 * solo el texto que lo dice, sin promedio ni número (perfil-lavanderia-E10).
 */
export function calificacionDelPerfil(promedio: number | null, numeroDeCalificaciones: number): string {
  if (promedio === null) {
    return calificacionEnTexto(null);
  }
  const cuantas = numeroDeCalificaciones === 1 ? '1 calificación' : `${numeroDeCalificaciones} calificaciones`;
  return `${calificacionEnTexto(promedio)} · ${cuantas}`;
}

/** Las estrellas de una reseña a la vista: llenas las que tiene, vacías las que le faltan para 5. */
export function estrellasALaVista(estrellas: number): string {
  return '★'.repeat(estrellas) + '☆'.repeat(ESTRELLAS_MAXIMAS - estrellas);
}

/** Las estrellas de una reseña para el lector de pantalla: "4 de 5 estrellas". */
export function estrellasParaLeer(estrellas: number): string {
  return `${estrellas} de ${ESTRELLAS_MAXIMAS} estrellas`;
}

/** Los meses en español, de enero a diciembre, para las fechas que se leen (las reseñas y la agenda). */
export const MESES = [
  'enero',
  'febrero',
  'marzo',
  'abril',
  'mayo',
  'junio',
  'julio',
  'agosto',
  'septiembre',
  'octubre',
  'noviembre',
  'diciembre',
] as const;

/** La fecha de una reseña en la zona horaria del teléfono: "20 de noviembre de 2026". */
export function fechaDeLaResena({ fecha }: Pick<Resena, 'fecha'>): string {
  const dia = new Date(fecha);
  return `${dia.getDate()} de ${MESES[dia.getMonth()]} de ${dia.getFullYear()}`;
}
