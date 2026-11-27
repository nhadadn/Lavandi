// Las rutas del Módulo B (ADR-0017): la pantalla de pedidos de la lavandería (pedidos-lavanderia-E03) y el rechazo de
// un pedido, por su id_publico (pedidos-lavanderia-E12 y E13).
import type { Href } from 'expo-router';

export const RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA: Href = '/lavanderia/pedidos';

/** El rechazo de un pedido, con su motivo obligatorio. */
export function rutaDelRechazo(idPublico: string): Href {
  return { pathname: '/lavanderia/pedidos/rechazo/[id]', params: { id: idPublico } };
}
