// Los textos del Módulo D en la app, para revisarlos en un solo lugar. Ninguno trae el nombre del producto ni el dominio
// (CLAUDE.md §5; ADR-0022, decisión 17; administracion-app-E03). Los del inicio del módulo son los de la imagen
// 03-inicio-del-modulo de la Propuesta 06 de los mocks: el título, los dos grupos, el nombre de cada sección y sus dos
// conteos.
import type { Seccion } from './rutas';

export const TEXTOS_DE_LA_ADMINISTRACION = {
  titulo: 'Administración',
  /** El Módulo D, del #1 al #7 (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3). */
  gestion: 'Gestión',
  /** Los indicadores y las descargas del A.5 (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5). */
  estadoDelNegocio: 'Estado del negocio',
  secciones: {
    lavanderias: 'Lavanderías',
    repartidores: 'Repartidores',
    usuarios: 'Usuarios',
    monitoreo: 'Monitoreo de pedidos',
    comisiones: 'Comisiones',
    ingresos: 'Ingresos',
    conciliacion: 'Conciliación',
    avisos: 'Avisos masivos',
    indicadores: 'Indicadores',
    descargas: 'Descargas',
  } satisfies Record<Seccion, string>,
  /** La insignia junto a Repartidores: los pendientes de alta (imágenes 03 y 56; administracion-app-E03). */
  pendientes: (cuantos: number) => `${cuantos} ${cuantos === 1 ? 'pendiente' : 'pendientes'}`,
  /** La insignia junto a Monitoreo de pedidos: los pedidos activos (imágenes 03 y 56; administracion-app-E03). */
  activos: (cuantos: number) => `${cuantos} ${cuantos === 1 ? 'activo' : 'activos'}`,
  /** El botón que deja todo como estaba en el diálogo de una confirmación (src/administracion/confirmar.ts). */
  cancelar: 'Cancelar',
} as const;
