// Los textos de los pedidos de la lavandería (openspec/changes/pedidos-de-la-lavanderia), juntos para revisarlos en un
// solo lugar. Los nombres de los estados y de los botones no viven aquí: llegan del servidor (estado.nombre y
// siguiente.boton).
import { fechaEnTexto, ventanaEnTexto } from '../agenda/textos';
import type { PedidoDeLaLavanderia } from './api';

export const TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA = {
  titulo: 'Pedidos activos',
  cargando: 'Cargando tus pedidos…',
  // La lista vacía: un mensaje neutro, como "Mis pedidos".
  sinPedidos: 'No tienes pedidos activos.',
  // Las respuestas de la tarjeta (pedidos-lavanderia-E03), con la nota del componente 4 del design system.
  aceptar: 'Aceptar',
  rechazar: 'Rechazar',
  notaDelRechazo: 'Si rechazas, se te pedirá el motivo.',
  // El rechazo (pedidos-lavanderia-E12 y E13), con los textos de la imagen b02-rechazo de la Propuesta 04. Sin motivo,
  // el botón no se puede tocar y dice qué falta, como "Elige una ventana" en la agenda (agenda-E08).
  tituloDelRechazo: 'Rechazar pedido',
  motivo: 'Motivo del rechazo',
  rechazarPedido: 'Rechazar pedido',
  escribeElMotivo: 'Escribe el motivo',
  // El 409 sin mensaje del servidor: el pedido ya cambió (pedidos-lavanderia-E08; estados-E05 y E09). El servidor manda
  // el suyo: "El pedido ya fue respondido." o "Ese no es el paso siguiente del pedido.".
  yaCambio: 'El pedido ya cambió. Revisa cómo está.',
} as const;

/**
 * "Recolección jueves 3 de diciembre, 11:00 – 12:00 · Lavado estándar": la fecha y la ventana en la hora de la plaza,
 * como las da el servidor, y el servicio, como la línea de la tarjeta del componente 4.
 */
export function recoleccionYServicio({ ventana, servicios }: Pick<PedidoDeLaLavanderia, 'ventana' | 'servicios'>) {
  const servicio = servicios.map(({ nombre }) => nombre).join(', ');
  return `Recolección ${fechaEnTexto(ventana.fecha)}, ${ventanaEnTexto(ventana)} · ${servicio}`;
}
