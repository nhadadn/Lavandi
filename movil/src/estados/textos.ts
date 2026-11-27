// Los textos del seguimiento del pedido (estados-E02 y E24), juntos para revisarlos en un solo lugar. Los nombres de
// los cinco estados no viven aquí: llegan del servidor (estados-E01). El encabezado es el del mock A12 y la imagen
// a12-seguimiento de la Propuesta 04.
import type { PasoALaVista } from './seguimiento';

export const TEXTOS_DEL_SEGUIMIENTO = {
  encabezado: 'Tu pedido',
  cargando: 'Cargando tu pedido…',
  // Bajo cada estado al que el pedido todavía no llega, en lugar de su hora, como en la imagen a12-seguimiento.
  pendiente: 'Pendiente',
} as const;

/** "Pedido 7KQ2M9XA": el folio del pedido, arriba del seguimiento. */
export function pedidoConFolio(folio: string): string {
  return `Pedido ${folio}`;
}

/**
 * Lo que lee el lector de pantalla en cada estado, porque el color nunca va solo (design system, reglas transversales):
 * "Confirmado, cumplido, 09:30", "Recolectado, estado actual, 10:05" o "En proceso, pendiente".
 */
export function pasoParaLeer({ nombre, situacion, hora }: PasoALaVista): string {
  const como = { cumplido: 'cumplido', actual: 'estado actual', pendiente: 'pendiente' }[situacion];
  return [nombre, como, hora].filter((parte) => parte !== null).join(', ');
}
