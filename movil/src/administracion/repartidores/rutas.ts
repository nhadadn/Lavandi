// Las rutas de la gestión de repartidores (ADR-0017; ADR-0022, decisión 11; design.md, sección 5): la lista, que abre
// "Repartidores" en el inicio del módulo (administracion-app-E03), la ficha de cada uno, por su id_publico
// (administracion-app-E19), y su edición, que abre "Editar" en la ficha (administracion-app-E23).
import type { Href } from 'expo-router';

import { RUTAS_DE_LAS_SECCIONES } from '../rutas';

export const RUTA_DE_LOS_REPARTIDORES: Href = RUTAS_DE_LAS_SECCIONES.repartidores;

/** La ficha de un repartidor. */
export function rutaDelRepartidor(idPublico: string): Href {
  return { pathname: '/administracion/repartidores/[id]', params: { id: idPublico } };
}

/** La edición de sus datos. */
export function rutaDeLaEdicionDelRepartidor(idPublico: string): Href {
  return { pathname: '/administracion/repartidores/editar/[id]', params: { id: idPublico } };
}
