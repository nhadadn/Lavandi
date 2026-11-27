// Las rutas de la gestión de usuarios de la administración (ADR-0017; design.md, sección 5): la lista, que abre el inicio
// del módulo (administracion-app-E03), y la ficha de cada cuenta por su id_publico (administracion-app-E24).
import type { Href } from 'expo-router';

import { RUTAS_DE_LAS_SECCIONES } from '../rutas';

export const RUTA_DE_LOS_USUARIOS: Href = RUTAS_DE_LAS_SECCIONES.usuarios;

/** La ficha de una cuenta de usuario, por su id_publico. */
export function rutaDeLaFichaDelUsuario(idPublico: string): Href {
  return { pathname: '/administracion/usuarios/[id]', params: { id: idPublico } };
}
