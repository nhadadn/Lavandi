// Los textos del pago del pedido (openspec/changes/pago, pago-E02), juntos para revisarlos en un solo lugar. El
// encabezado es el del mock A10. Ninguno nombra a la pasarela: si el cliente firma el cambio de pasarela (DD-079), los
// textos no cambian.
import { MENSAJES } from '../acceso/mensajes';
import type { FallaDelCheckout } from './api';

export const TEXTOS_DEL_PAGO = {
  encabezado: 'Pago',
  titulo: 'Paga tu pedido',
  // Dónde se escribe la tarjeta: en la página de la pasarela, dentro de la app (adenda del 2026-11-22 de ADR-0010).
  dondeSePaga:
    'Pagas con tarjeta de crédito o débito en la página segura de pago, que se abre aquí mismo, sin salir de la app.',
  sinDatosDeTarjeta: 'La app nunca ve ni guarda los datos de tu tarjeta.',
  // El único método mientras el efectivo espera DD-056.
  pagarConTarjeta: 'Pagar con tarjeta',
  // El pedido no existe o es de otra cuenta: el servidor responde igual.
  noEncontrado: 'No encontramos este pedido.',
  // El último intento se rechazó (pago-E04 y pago-E08), con el texto del mock A10, sin "o usa otra tarjeta": la
  // persona elige la tarjeta en la página de pago.
  rechazado: 'El pago no se completó. Intenta de nuevo.',
} as const;

// La espera del cobro después del checkout (pago-E08), con el texto del mock A10, sin nombrar a la pasarela.
export const TEXTOS_DE_LA_ESPERA = {
  procesando: 'Procesando pago',
  esperamos: 'Esperamos la confirmación de tu pago.',
  // A los 3 minutos sin confirmación (decisión 32): deja de esperar y ofrece volver a consultar.
  enRevision: 'Tu pago sigue en revisión',
  todaviaNo: 'Todavía no recibimos la confirmación de tu pago. Puedes volver a consultar en un momento.',
  volverAConsultar: 'Volver a consultar',
  // El cobro no existe o es de otra cuenta: el servidor responde igual.
  noEncontrado: 'No encontramos este pago.',
  // vencido o cancelado: hoy no los pone nada (DD-056, DD-012 y DD-016).
  yaNoSePuedePagar: 'Este cobro ya no se puede pagar.',
} as const;

/**
 * El mensaje de un checkout que no se dio: el del servidor cuando dice por qué no puede darlo (409, 503); sin conexión o
 * con el servidor caído, sin escenario propio, los mensajes generales de la app, como el resumen.
 */
export function mensajeDelCheckout(falla: FallaDelCheckout): string {
  switch (falla.motivo) {
    case 'no-encontrado':
      return TEXTOS_DEL_PAGO.noEncontrado;
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
