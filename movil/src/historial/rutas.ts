// Las rutas de "Mis pedidos" (ADR-0017): la lista (historial-E01), el seguimiento de cada pedido, que ya existe
// (estados-del-pedido, estados-E02), y la calificación de un pedido entregado (historial-E06 a E08).
import type { Href } from 'expo-router';

import type { PedidoDelHistorial } from './api';

export const RUTA_DEL_HISTORIAL: Href = '/pedidos';

/** El seguimiento de un pedido, por su id_publico. */
export function rutaDelSeguimiento(idPublico: string): Href {
  return { pathname: '/pedido/seguimiento/[id]', params: { id: idPublico } };
}

/**
 * La calificación del servicio de un pedido, por su id_publico. Lleva su folio y el nombre de su lavandería, como los
 * tiene la lista, para decir qué pedido se califica sin volver a pedirlo.
 */
export function rutaDeLaCalificacion({ id_publico, folio, lavanderia }: PedidoDelHistorial): Href {
  return { pathname: '/pedido/calificacion/[id]', params: { id: id_publico, folio, lavanderia: lavanderia.nombre } };
}
