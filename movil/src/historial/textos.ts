// Los textos de "Mis pedidos" (historial-E01), juntos para revisarlos en un solo lugar. El título es el del mock A13 y
// la imagen a13-historial de la Propuesta 04. Los nombres de los estados no viven aquí: llegan del servidor.
import { MENSAJES } from '../acceso/mensajes';
import { fechaDelSelector } from '../agenda/textos';
import { pedidoConFolio } from '../estados/textos';
import { ESTRELLAS_DE_LA_CALIFICACION, type FallaAlCalificar, type PedidoDelHistorial } from './api';

const ESTRELLAS_MAXIMAS = ESTRELLAS_DE_LA_CALIFICACION.length;

export const TEXTOS_DEL_HISTORIAL = {
  encabezado: 'Mis pedidos',
  // La entrada desde la pantalla principal.
  misPedidos: 'Mis pedidos',
  cargando: 'Cargando tus pedidos…',
  // La lista vacía, sin filtro: un mensaje neutro.
  sinPedidos: 'Todavía no tienes pedidos.',
  // El filtro por fecha (historial-E03), con los textos del mock A13.
  elegirFechas: 'Elegir fechas',
  desde: 'Desde',
  hasta: 'Hasta',
  aplicar: 'Aplicar',
  quitarFiltro: 'Quitar filtro',
  // Un rango sin pedidos (historial-E04), con el texto del mock A13.
  sinPedidosEnLasFechas: 'No hay pedidos en esas fechas.',
  // Junto al campo, antes de mandar el filtro: la fecha a medias o que no existe, y "Hasta" antes de "Desde".
  fechaInvalida: 'Escribe la fecha como DD/MM/AAAA.',
  rangoAlReves: 'No puede ser anterior a la fecha de inicio.',
  // La calificación en la tarjeta (historial-E06), con los textos del mock A13.
  calificar: 'Calificar',
} as const;

// La pantalla de calificación (historial-E06 a E08), con los textos del mock A14 en su variante literal del contrato:
// una sola calificación, del servicio (DD-052). Del mock no entran "Ahora no", que hace lo mismo que "Regresar", ni la
// variante de la lavandería y el repartidor, que espera DD-052.
export const TEXTOS_DE_LA_CALIFICACION = {
  encabezado: 'Califica tu servicio',
  pregunta: '¿Cómo estuvo el servicio?',
  tocaUnaEstrella: 'Toca una estrella.',
  comentario: 'Comentario (opcional)',
  enviar: 'Enviar calificación',
  // El botón sin estrellas elegidas dice qué falta (historial-E08), como el mock A14.
  eligeUnaEstrella: 'Elige una estrella',
  // El pedido no existe o es de otra cuenta: el servidor responde igual (historial-E09).
  noEncontrado: 'No encontramos este pedido.',
} as const;

/** "Calificar el pedido 3HT8W2QZ": lo que lee el lector de pantalla en el enlace de cada tarjeta. */
export function calificarParaLeer(folio: string): string {
  return `${TEXTOS_DEL_HISTORIAL.calificar} el pedido ${folio}`;
}

/** "Calificaste 4 de 5", como el mock A13, en la tarjeta de un pedido ya calificado (historial-E06). */
export function calificasteALaVista(estrellas: number): string {
  return `Calificaste ${estrellas} de ${ESTRELLAS_MAXIMAS}`;
}

/** "Calificaste con 4 de 5 estrellas", para el lector de pantalla. */
export function calificasteParaLeer(estrellas: number): string {
  return `Calificaste con ${estrellas} de ${ESTRELLAS_MAXIMAS} estrellas`;
}

/** "Pedido 3HT8W2QZ · Lavandería Centro": qué pedido se califica, como el mock A14. */
export function pedidoQueSeCalifica(folio: string, lavanderia: string): string {
  return `${pedidoConFolio(folio)} · ${lavanderia}`;
}

/** Cada estrella para el lector de pantalla: "1 estrella", "4 estrellas". */
export function estrellaParaLeer(numero: number): string {
  return numero === 1 ? '1 estrella' : `${numero} estrellas`;
}

/** Las estrellas elegidas: "4 de 5", como el mock A14. */
export function estrellasElegidas(numero: number): string {
  return `${numero} de ${ESTRELLAS_MAXIMAS}`;
}

/**
 * El mensaje de una calificación que no se guardó y que no es de un campo: el del servidor cuando dice por qué no la
 * acepta (409); sin conexión o con el servidor caído, sin escenario propio, los mensajes generales de la app.
 */
export function mensajeAlCalificar(falla: FallaAlCalificar): string {
  switch (falla.motivo) {
    case 'no-encontrado':
      return TEXTOS_DE_LA_CALIFICACION.noEncontrado;
    case 'no-se-puede':
      return falla.mensaje;
    case 'invalido':
      return falla.mensaje || MENSAJES.servidor;
    case 'sin-conexion':
      return MENSAJES.sinConexion;
    default:
      return MENSAJES.servidor;
  }
}

/**
 * "Lunes 23 de noviembre": la fecha del pedido como la escribe la app en la agenda, sin "Hoy" ni "Mañana". Es un día de
 * la plaza, AAAA-MM-DD, y no se pasa a la zona del teléfono.
 */
export function fechaDelPedido(fecha: string): string {
  return fechaDelSelector(fecha, null);
}

/**
 * Lo que lee el lector de pantalla en cada pedido, todo junto: "Pedido 7KQ2M9XA, Lavandería Centro, Lunes 23 de
 * noviembre, En proceso". Sin el estado si el usuario no lo ve.
 */
export function pedidoParaLeer({ folio, lavanderia, fecha, estado }: PedidoDelHistorial): string {
  const partes = [pedidoConFolio(folio), lavanderia.nombre, fechaDelPedido(fecha)];
  if (estado.visible_usuario) {
    partes.push(estado.nombre);
  }
  return partes.join(', ');
}
