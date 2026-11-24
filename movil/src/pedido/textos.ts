// Los textos de los pasos del pedido nuevo, juntos para revisarlos en un solo lugar. El pedido va en cuatro pasos
// (decisión 16 del acta del 2026-11-20, C-233); el primero es el tipo de servicio (perfil-lavanderia-E11 a E13) y el
// último, el resumen (resumen-y-confirmacion). Después vienen el pago (sus textos, en src/pago/textos.ts) y la
// confirmación.
import { MENSAJES, mensajeGeneral } from '../acceso/mensajes';
import type { FallaAlCrear } from './api';

const PASOS_DEL_PEDIDO = 4;

/** El encabezado de cada paso: "Pedido nuevo · 1 de 4", como en el mock A06. */
export function pasoDelPedido(paso: number): string {
  return `Pedido nuevo · ${paso} de ${PASOS_DEL_PEDIDO}`;
}

export const TEXTOS_DEL_PEDIDO = {
  // La selección de servicio, con el título del mock A06.
  tituloDelServicio: '¿Qué servicio necesitas?',
  continuar: 'Continuar',
  // Sin tipo elegido no se continúa (perfil-lavanderia-E13).
  faltaElServicio: 'Elige un tipo de servicio para continuar.',
} as const;

// El resumen, con el título del mock A09 y el componente 6 del design system (vault/design-system/componentes.md).
export const TEXTOS_DEL_RESUMEN = {
  titulo: 'Revisa tu pedido',
  servicio: 'Servicio',
  recoleccion: 'Recolección',
  recogida: 'Recogida',
  entrega: 'Entrega',
  // La entrega en la misma dirección de la recogida (direcciones-E09).
  mismaDeLaRecogida: 'La misma de la recogida',
  // Los renglones reservados: el precio se presenta como estimado (confirmacion-E02, DD-012 y DD-010) y la política de
  // cancelación (confirmacion-E04, DD-016). Sin cifra ni texto inventado: solo el lugar, marcado. El envío no lleva
  // renglón: el Módulo A #9 no lo enumera, y si es un cargo aparte lo dicen DD-019 y DD-025 (confirmacion-E03).
  precioEstimado: 'Precio estimado',
  politicaDeCancelacion: 'Política de cancelación',
  porConfirmar: 'Por confirmar',
  // El botón lleva al pago (confirmacion-E07). Con C-188 y C-197 se cobra al estimar, antes de recolectar: es la
  // variante "al pedir" del componente 6 (DD-012, parcial).
  confirmarYPagar: 'Confirmar y pagar',
} as const;

// La confirmación, con los textos del mock A11 (confirmacion-E09).
export const TEXTOS_DE_LA_CONFIRMACION = {
  cargando: 'Cargando tu pedido…',
  confirmado: '¡Pedido confirmado!',
  tuFolioEs: 'Tu folio es',
  // Antes de su pago el pedido no tiene folio, y su estado no se nombra (DD-123, ADR-0021).
  tuPedido: 'Tu pedido',
  sinConfirmar: 'Todavía no está confirmado.',
  // No existe o es de otra cuenta: el servidor responde igual (confirmacion-E12).
  noEncontrado: 'No encontramos este pedido.',
  volverAlInicio: 'Volver al inicio',
} as const;

/**
 * El mensaje de un resumen que no se pudo aceptar y que no regresa a otro paso: el del servidor cuando dice por qué no
 * puede crear el pedido (404, 503) o rechaza un campo que este paso no muestra, como el servicio; sin conexión o con el
 * servidor caído, sin escenario propio, los mensajes generales de la app.
 */
export function mensajeAlCrear(falla: FallaAlCrear): string | undefined {
  switch (falla.motivo) {
    case 'no-se-puede':
    case 'ventana':
      return falla.mensaje;
    case 'invalido':
      return mensajeGeneral(falla);
    case 'sin-conexion':
      return MENSAJES.sinConexion;
    default:
      return MENSAJES.servidor;
  }
}
