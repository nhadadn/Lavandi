// Las rutas de la gestión de lavanderías (ADR-0017; ADR-0022, decisión 11; design.md, sección 5): la lista es la sección
// del inicio del módulo (src/administracion/rutas.ts); la ficha y la edición de una lavandería, por su id_publico, son
// otras pantallas de la pila de app/administracion/.
import type { Href } from 'expo-router';

/** La ficha de una lavandería (administracion-app-E13). */
export function rutaDeLaFicha(idPublico: string): Href {
  return { pathname: '/administracion/lavanderias/[id]', params: { id: idPublico } };
}

/** La edición de una lavandería (administracion-app-E14). */
export function rutaDeLaEdicion(idPublico: string): Href {
  return { pathname: '/administracion/lavanderias/editar/[id]', params: { id: idPublico } };
}
