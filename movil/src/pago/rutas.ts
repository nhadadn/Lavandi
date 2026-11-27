// Las rutas del pago (ADR-0017): la pantalla de pago del pedido (/pedido/pago/[id], que abre el resumen) y la espera
// "Procesando pago" de su cobro (/pedido/pago/procesando/[cobro], pago-E08), y el enlace de regreso del checkout.
import type { Href } from 'expo-router';

/** El parámetro de la pantalla de pago que dice que el último intento se rechazó. */
export const INTENTO_RECHAZADO = 'rechazado';

/**
 * La pantalla de pago del pedido después de un intento rechazado (pago-E04 y pago-E08): dice que el pago no se completó
 * y su botón vuelve a pedir el checkout del mismo cobro.
 */
export function rutaDelPagoRechazado(pedido: string): Href {
  return { pathname: '/pedido/pago/[id]', params: { id: pedido, intento: INTENTO_RECHAZADO } };
}

/** La espera del cobro, por su id_publico: la que consulta el servidor hasta que el cobro queda pagado o rechazado. */
export function rutaDeLaEspera(cobro: string): Href {
  return { pathname: '/pedido/pago/procesando/[cobro]', params: { cobro } };
}

/** La misma espera como texto, para un enlace que abre la app (app/+native-intent.tsx). */
export function direccionDeLaEspera(cobro: string): string {
  return `/pedido/pago/procesando/${encodeURIComponent(cobro)}`;
}

/**
 * La ruta del regreso del checkout. El servidor la arma con PASARELA_REGRESO, el esquema de la app seguido de ://pago, y
 * le agrega ?resultado=exito|error&pago=<id_publico del cobro> (openspec/changes/pago/design.md, "El regreso desde el
 * checkout"). El esquema sale de la configuración (APP_IDENTIFIER, app.config.ts): aquí solo va la ruta.
 */
const RUTA_DEL_REGRESO = 'pago';

/**
 * Si la dirección es el regreso del checkout: con el id_publico del cobro que trae, o con null si no trae ninguno. Si es
 * cualquier otra dirección, undefined. Recibe la dirección completa, con su esquema, o solo la ruta.
 */
export function cobroDelRegreso(direccion: string): string | null | undefined {
  const sinEsquema = direccion.replace(/^[a-z][a-z0-9+.-]*:\/\//i, '');
  const separador = sinEsquema.indexOf('?');
  const ruta = separador === -1 ? sinEsquema : sinEsquema.slice(0, separador);
  if (ruta.replace(/^\/+|\/+$/g, '') !== RUTA_DEL_REGRESO) {
    return undefined;
  }
  const consulta = separador === -1 ? '' : sinEsquema.slice(separador + 1).split('#')[0];
  for (const parte of consulta.split('&')) {
    const [nombre, valor = ''] = parte.split('=');
    if (nombre === 'pago' && valor !== '') {
      return leerParametro(valor);
    }
  }
  return null;
}

// Un valor de la consulta, sin su codificación; si viene mal codificado, tal cual.
function leerParametro(valor: string): string {
  try {
    return decodeURIComponent(valor.replace(/\+/g, ' '));
  } catch {
    return valor;
  }
}
