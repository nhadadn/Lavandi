// Las rutas del Módulo D (ADR-0017; ADR-0022, decisión 11): el inicio del módulo, a donde entra la cuenta de
// administración (administracion-app-E02), y la pantalla de cada sección que el inicio lista (administracion-app-E03;
// design.md, sección 5). Cada sección es una pantalla de la pila de app/administracion/; las fichas de una lavandería, un
// repartidor o un usuario las nombra la sección en sus propias rutas.
import type { Href } from 'expo-router';

export const RUTA_DEL_INICIO_DE_LA_ADMINISTRACION: Href = '/administracion';

/** La pantalla de cada sección del inicio, en el orden en que el inicio las lista. */
export const RUTAS_DE_LAS_SECCIONES = {
  lavanderias: '/administracion/lavanderias',
  repartidores: '/administracion/repartidores',
  usuarios: '/administracion/usuarios',
  monitoreo: '/administracion/monitoreo',
  comisiones: '/administracion/comisiones',
  ingresos: '/administracion/ingresos',
  conciliacion: '/administracion/conciliacion',
  avisos: '/administracion/avisos',
  indicadores: '/administracion/indicadores',
  descargas: '/administracion/descargas',
} as const satisfies Record<string, Href>;

export type Seccion = keyof typeof RUTAS_DE_LAS_SECCIONES;
