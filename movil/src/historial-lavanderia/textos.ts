// Los textos del historial y de las estadísticas de la lavandería (openspec/changes/historial-de-la-lavanderia), juntos
// para revisarlos en un solo lugar. Los títulos son los de las imágenes b04-historial y b05-estadisticas de la Propuesta
// 04. Sin monto, ingreso ni calificación: esperan DD-013, DD-020, DD-012 y DD-052.
import { fechaEnTexto } from '../agenda/textos';
import { pedidoConFolio } from '../estados/textos';
import type { PedidoEntregado } from './api';

export const TEXTOS_DEL_HISTORIAL_DE_LA_LAVANDERIA = {
  // El historial (historial-lavanderia-E01).
  historial: 'Historial de pedidos',
  cargandoElHistorial: 'Cargando tu historial…',
  // Sin escenario propio: el historial vacío, con un mensaje neutro, como "Mis pedidos".
  sinEntregados: 'Todavía no tienes pedidos entregados.',
  // Las estadísticas (historial-lavanderia-E05): completado es Entregado (design.md, decisión 2).
  estadisticas: 'Estadísticas',
  cargandoLasEstadisticas: 'Cargando tus estadísticas…',
  pedidosDelDia: 'Pedidos completados hoy',
  pedidosDeLaSemana: 'Pedidos completados esta semana',
  // "Del día" y "de la semana" se cuentan en la hora de la plaza, de lunes a domingo (design.md, decisión 3).
  laSemana: 'La semana va de lunes a domingo.',
} as const;

/** "Entregado el jueves 3 de diciembre": la fecha de la plaza que da el servidor, sin pasarla a la zona del teléfono. */
export function entregadoEl(fecha: string): string {
  return `Entregado el ${fechaEnTexto(fecha)}`;
}

/** Lo que lee el lector de pantalla en cada pedido, todo junto: "Pedido 7KQ2M9XA, entregado el jueves 3 de diciembre". */
export function pedidoEntregadoParaLeer({ folio, fecha }: PedidoEntregado): string {
  return `${pedidoConFolio(folio)}, entregado el ${fechaEnTexto(fecha)}`;
}
