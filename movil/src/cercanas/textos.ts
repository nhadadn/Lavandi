// Los textos de la búsqueda de lavanderías cercanas, juntos para revisarlos en un solo lugar. El del permiso de
// ubicación vive en permiso-de-ubicacion.json porque app.config.ts también lo lee para el diálogo de iPhone.
import { MENSAJES } from '../acceso/mensajes';
import type { Falla } from '../configuracion/api';
import type { LavanderiaCercana } from './api';
import permisoDeUbicacion from './permiso-de-ubicacion.json';

export const TEXTOS = {
  titulo: 'Lavanderías cerca de ti',
  // La lista que abre "Pedido nuevo" (cercanas-E15), con el título del tablero "Búsqueda" de la Propuesta 5 corregida.
  tituloDeLaLista: 'Elige tu lavandería',
  pedidoNuevo: 'Pedido nuevo',
  // El estado vacío (cercanas-E14).
  sinLavanderias: 'No hay lavanderías cerca de ti.',
  // Para qué se usa la ubicación, antes de pedirla (cercanas-E03).
  permiso: permisoDeUbicacion.texto,
  buscando: 'Buscando lavanderías cercanas…',
  // Sin permiso, lo que la app consulta espera a DD-083 (cercanas-E05). Mientras, lo dice en lugar de dejar el mapa en
  // blanco.
  sinPermiso: 'Sin permiso de ubicación no podemos mostrarte las lavanderías cercanas.',
  // El teléfono no dio su ubicación, por ejemplo con la ubicación apagada. Sin escenario: lo dice y no consulta.
  sinUbicacion: 'No pudimos obtener tu ubicación. Revisa que esté activada.',
  // Solo en una compilación de Android sin la llave de Google Maps (src/cercanas/mapa.ts); en iPhone no pasa.
  mapaNoDisponible: 'El mapa no está disponible en esta versión de la app.',
  // Los filtros de la búsqueda (cercanas-E21), con los textos del mock A04.
  filtroDeDistancia: 'Distancia',
  filtroDeCalificacion: 'Calificación',
  sinCoincidencias: 'Ninguna lavandería coincide con estos filtros.',
  quitarFiltros: 'Quitar filtros',
} as const;

const METROS_POR_KILOMETRO = 1000;

/**
 * La distancia y la calificación de la tarjeta (cercanas-E12), como en las imágenes de la Propuesta 04: "A 1.2 km ·
 * Calificación 4.8". Menos de un kilómetro va en metros. Sin calificaciones no hay promedio que mostrar: lo dice, como
 * el perfil (perfil-lavanderia-E10).
 */
export function detalleDeLavanderia({ distancia_metros, calificacion_promedio }: LavanderiaCercana): string {
  return `${distanciaEnTexto(distancia_metros)} · ${calificacionEnTexto(calificacion_promedio)}`;
}

/** "A 800 m", "A 1.2 km": menos de un kilómetro va en metros. */
function distanciaEnTexto(distanciaMetros: number): string {
  const metros = Math.round(distanciaMetros);
  return metros < METROS_POR_KILOMETRO ? `A ${metros} m` : `A ${(distanciaMetros / METROS_POR_KILOMETRO).toFixed(1)} km`;
}

/**
 * El promedio de calificaciones con un decimal, "Calificación 4.8", o, sin calificaciones, el texto que lo dice y
 * ningún número: la tarjeta (cercanas-E12) y el perfil (perfil-lavanderia-E10) lo dicen igual.
 */
export function calificacionEnTexto(promedio: number | null): string {
  return promedio === null ? 'Aún sin calificaciones' : `Calificación ${promedio.toFixed(1)}`;
}

/** Un corte de distancia de los filtros (cercanas-E21): "Hasta 500 m", "Hasta 3 km". */
export function corteDeDistancia(metros: number): string {
  return metros < METROS_POR_KILOMETRO ? `Hasta ${metros} m` : `Hasta ${metros / METROS_POR_KILOMETRO} km`;
}

/** Un corte de calificación de los filtros (cercanas-E21): "4.5 o más". */
export function corteDeCalificacion(estrellas: number): string {
  return `${estrellas} o más`;
}

/**
 * Cuántas lavanderías hay, bajo el mapa: sin ninguna, el estado vacío (cercanas-E14); con alguna, que se toque un pin
 * para ver su tarjeta (cercanas-E12), si hay mapa.
 */
export function resumenDeLasCercanas(cuantas: number, conMapa: boolean): string {
  if (cuantas === 0) {
    return TEXTOS.sinLavanderias;
  }
  const hay = cuantas === 1 ? '1 lavandería cerca de ti.' : `${cuantas} lavanderías cerca de ti.`;
  if (!conMapa) {
    return hay;
  }
  return `${hay} ${cuantas === 1 ? 'Toca el pin.' : 'Toca un pin.'}`;
}

/** El mensaje de una consulta que no salió. Sin escenario propio: los mensajes generales de la app. */
export function mensajeDeLaFalla(falla: Falla): string {
  return falla.motivo === 'sin-conexion' ? MENSAJES.sinConexion : MENSAJES.servidor;
}
