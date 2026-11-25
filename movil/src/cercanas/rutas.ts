// Las rutas de la búsqueda (ADR-0017): la lista que abre "Pedido nuevo" (cercanas-E15), con el punto de la pantalla
// principal en sus parámetros, y el perfil de cada lavandería por su id_publico (cercanas-E12). El punto viaja en la
// navegación y no se guarda (design.md, decisión 6).
import type { Href } from 'expo-router';

import type { Punto } from './api';

export interface ParametrosDeLaLista {
  latitud?: string;
  longitud?: string;
}

/** La lista de lavanderías cercanas; con el punto, la lista consulta con él y no vuelve a leer la ubicación. */
export function rutaDeLaLista(punto: Punto | null): Href {
  if (!punto) {
    return '/lavanderias';
  }
  return { pathname: '/lavanderias', params: { latitud: String(punto.latitud), longitud: String(punto.longitud) } };
}

/** El perfil de una lavandería (perfil-de-lavanderia). */
export function rutaDelPerfil(idPublico: string): Href {
  return { pathname: '/lavanderias/[id]', params: { id: idPublico } };
}

// Los grados que existen: un parámetro fuera de ellos no es un punto.
const LATITUD_MAXIMA = 90;
const LONGITUD_MAXIMA = 180;

/** El punto de los parámetros de la lista, si es un punto válido; si no, la lista lo busca por su cuenta. */
export function puntoDeLosParametros({ latitud, longitud }: ParametrosDeLaLista): Punto | null {
  const enNumero = (valor: string | undefined) => (valor?.trim() ? Number(valor) : NaN);
  const punto = { latitud: enNumero(latitud), longitud: enNumero(longitud) };
  const valido =
    Number.isFinite(punto.latitud) &&
    Number.isFinite(punto.longitud) &&
    Math.abs(punto.latitud) <= LATITUD_MAXIMA &&
    Math.abs(punto.longitud) <= LONGITUD_MAXIMA;
  return valido ? punto : null;
}
