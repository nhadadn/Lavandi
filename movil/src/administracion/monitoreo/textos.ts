// Los textos del monitoreo de pedidos de la administración (F-D04; administracion-app-E28 a E30), juntos para revisarlos
// en un solo lugar. Son los de las imágenes 25 a 27 de la Propuesta 06 de los mocks; el estado vacío es el del monitoreo
// del panel de respaldo ("No hay pedidos activos.", servidor/app/Filament/Pages/MonitoreoDePedidos.php:84), y el del
// mapa que no se monta, el de la búsqueda de lavanderías (src/cercanas/textos.ts), para que la app diga lo mismo en todos
// sus mapas. Los nombres de los estados, las plazas y las etiquetas de los marcadores llegan del servidor. Ninguno trae
// el nombre del producto ni el dominio (CLAUDE.md §5; administracion-app-E03).
import { MENSAJES } from '../../acceso/mensajes';
import { TEXTOS as TEXTOS_DE_LAS_CERCANAS } from '../../cercanas/textos';
import { pedidoConFolio } from '../../estados/textos';
import { CADA_CUANTO_SE_CONSULTA_MS } from '../../estados/useConsultaPeriodica';
import type { FallaDeLaAdministracion } from '../api';
import { TEXTOS_DE_LA_ADMINISTRACION } from '../textos';
import type { MarcadorDelMonitoreo, TipoDeMarcador, VentanaDelMonitoreo } from './api';

export const TEXTOS_DEL_MONITOREO = {
  // El nombre de su entrada en el inicio del módulo, para que el inicio y el encabezado digan lo mismo.
  titulo: TEXTOS_DE_LA_ADMINISTRACION.secciones.monitoreo,
  cargando: 'Cargando los pedidos activos…',
  pedidosActivos: 'Pedidos activos',
  sinPedidos: 'No hay pedidos activos.',
  // Bajo el mapa, lo que dice cada letra (imagen 25).
  leyenda: 'R: recolección · E: entrega en otra dirección',
  // Para el lector de pantalla, como en la imagen 25.
  elMapa: 'Mapa con los pedidos activos: R en la recolección y E en la entrega',
  // Solo en una compilación de Android sin la llave del mapa (imagen 26; src/cercanas/mapa.ts).
  mapaNoDisponible: TEXTOS_DE_LAS_CERCANAS.mapaNoDisponible,
  intentarDeNuevo: 'Intentar de nuevo',
  // La letra de cada marcador, como en el mapa del panel.
  letras: { recoleccion: 'R', entrega: 'E' } satisfies Record<TipoDeMarcador, string>,
} as const;

/** Cada cuánto se pone al día, con los segundos de la consulta periódica (ADR-0007): "se pone al día cada 30 s". */
const SE_PONE_AL_DIA = `se pone al día cada ${CADA_CUANTO_SE_CONSULTA_MS / 1000} s`;

/**
 * Bajo el encabezado: cuántos pedidos activos hay y que se pone al día solo, "3 pedidos activos · todas las plazas · se
 * pone al día cada 30 s". Sin pedidos, o mientras no llega la primera respuesta, sin cuántos: "Todas las plazas · se
 * pone al día cada 30 s" (imagen 27).
 */
export function resumenDelMonitoreo(cuantos: number | null): string {
  if (!cuantos) {
    return `Todas las plazas · ${SE_PONE_AL_DIA}`;
  }
  const hay = cuantos === 1 ? '1 pedido activo' : `${cuantos} pedidos activos`;
  return `${hay} · todas las plazas · ${SE_PONE_AL_DIA}`;
}

/** "24/11/2026": el día de la recolección, AAAA-MM-DD como lo da el servidor, en el orden de la imagen 25. */
function fechaCorta(fecha: string): string {
  const [anio, mes, dia] = fecha.split('-');
  return `${dia}/${mes}/${anio}`;
}

/** "Recolección: 24/11/2026, 09:00 a 10:00": la recolección del pedido en la hora de su plaza (imagen 25). */
export function recoleccionEnTexto({ fecha, hora_inicio, hora_fin }: VentanaDelMonitoreo): string {
  return `Recolección: ${fechaCorta(fecha)}, ${hora_inicio} a ${hora_fin}`;
}

/** "En proceso · Recolección": el estado del pedido y qué punto marca, el renglón del medio al tocar el marcador. */
export function estadoYEtiqueta({ estado, etiqueta }: Pick<MarcadorDelMonitoreo, 'estado' | 'etiqueta'>): string {
  return `${estado} · ${etiqueta}`;
}

/**
 * Lo que lee el lector de pantalla en un marcador, porque la letra sola no dice qué es: "Pedido 7K2M9QXA, En proceso,
 * Recolección, Plaza de prueba Durango".
 */
export function marcadorParaLeer({ folio, estado, etiqueta, plaza }: MarcadorDelMonitoreo): string {
  return [pedidoConFolio(folio), estado, etiqueta, plaza].join(', ');
}

/**
 * El mensaje de una consulta que no salió, sin escenario propio: los mensajes generales de la app, como las demás
 * listas. El 401 no llega aquí: el cliente de la administración ya abrió el inicio de sesión.
 */
export function mensajeDelMonitoreo(falla: FallaDeLaAdministracion): string {
  return falla.motivo === 'sin-conexion' ? MENSAJES.sinConexion : MENSAJES.servidor;
}
