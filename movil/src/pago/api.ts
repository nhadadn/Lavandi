// El cobro del pedido en la API (openspec/changes/pago): pedir el checkout de la pasarela para pagar el pedido con
// tarjeta (pago-E02) y consultar el cobro mientras se espera la confirmación (pago-E08). Todo va con el token de la
// sesión. El contrato exacto, con cada cuerpo y cada error, está en
// openspec/changes/pago/design.md, "Contrato de la API". Como las demás consultas, nunca lanza: devuelve un Resultado.
//
// Ninguna petición de la app da un cobro por pagado: lo hace solo el aviso de la pasarela al servidor (pago-E07). La app
// manda el pedido y el método, nunca un monto ni un dato de tarjeta: el monto es el del cobro que guarda el servidor, y
// la tarjeta se escribe en el checkout de la pasarela (pago-E02; ADR-0010 y su adenda del 2026-11-22).
import { pedirConEstado, type Falla } from '../configuracion/api';
import { esObjeto } from '../configuracion/lectura';

/** Las rutas y los nombres del cobro, en un solo lugar para ajustarlos. */
export const CONTRATO_DEL_PAGO = {
  checkout: 'pagos/checkout',
  // GET <cobro>/<id_publico del cobro>: solo lee, aunque lleve que ya pagó (pago-E07).
  cobro: 'pagos',
  clave: 'pago',
  // El único método que se ofrece: el efectivo espera DD-056 y la tarjeta guardada, DD-055 y DD-079.
  tarjeta: 'tarjeta',
} as const;

/** Los estados del cobro (pago-E04 y pago-E08). vencido y cancelado no los pone nada todavía (DD-056, DD-012, DD-016). */
export type EstadoDelCobro = 'pendiente' | 'pagado' | 'fallido' | 'vencido' | 'cancelado';
const ESTADOS: readonly EstadoDelCobro[] = ['pendiente', 'pagado', 'fallido', 'vencido', 'cancelado'];

/** El cobro como lo da el servidor, con lo que la app usa: sin plaza, sin pasarela y sin ningún dato de tarjeta. */
export interface Cobro {
  /** El que consulta la espera "Procesando pago". */
  id_publico: string;
  /** El id_publico del pedido que cobra. */
  pedido: string;
  estado: EstadoDelCobro;
}

/** El checkout de la pasarela: la dirección que se abre y la de regreso a la app, o null si el servidor no la tiene. */
export interface Checkout {
  url: string;
  regreso: string | null;
}

/**
 * Por qué no se dio el checkout. Además de las fallas generales: el pedido no existe o es de otra cuenta (404), o el
 * servidor no puede darlo y lo dice con un mensaje para la persona (409: el cobro no está listo, ya está pagado o ya no se
 * puede pagar; 503: el entorno no tiene pasarela).
 */
export type FallaDelCheckout =
  | Falla
  | { ok: false; motivo: 'no-encontrado' }
  | { ok: false; motivo: 'no-se-puede'; mensaje: string };

/** Los códigos con los que el servidor dice, con un mensaje para la persona, que no puede dar el checkout. */
const NO_SE_PUEDE = [409, 503];

/** POST del checkout del pedido, con la sesión: solo el pedido y el método (pago-E02). */
export async function pedirCheckout(
  token: string,
  pedido: string,
): Promise<{ ok: true; datos: { checkout: Checkout; cobro: Cobro } } | FallaDelCheckout> {
  const { checkout: ruta, clave, tarjeta } = CONTRATO_DEL_PAGO;
  const { resultado, estado } = await pedirConEstado<unknown>(ruta, {
    metodo: 'POST',
    token,
    cuerpo: { pedido, metodo: tarjeta },
  });
  if (!resultado.ok) {
    if (estado === 404) {
      return { ok: false, motivo: 'no-encontrado' };
    }
    if (resultado.motivo === 'servidor' && resultado.mensaje && estado !== null && NO_SE_PUEDE.includes(estado)) {
      return { ok: false, motivo: 'no-se-puede', mensaje: resultado.mensaje };
    }
    return resultado;
  }
  const datos = esObjeto(resultado.datos) ? resultado.datos : null;
  const checkout = datos ? leerCheckout(datos.checkout) : null;
  const cobro = datos ? leerCobro(datos[clave]) : null;
  // Una respuesta fuera del contrato no se usa a medias: cuenta como falla del servidor.
  return checkout && cobro ? { ok: true, datos: { checkout, cobro } } : { ok: false, motivo: 'servidor' };
}

/** El cobro no existe, no es un ULID o es de un pedido de otra cuenta: el servidor responde igual (404). */
export type FallaDelCobro = Falla | { ok: false; motivo: 'no-encontrado' };

/** GET del cobro por su id_publico, con la sesión: lo que consulta la espera "Procesando pago" (pago-E08). */
export async function consultarCobro(
  token: string,
  idPublico: string,
): Promise<{ ok: true; datos: Cobro } | FallaDelCobro> {
  const { cobro: ruta, clave } = CONTRATO_DEL_PAGO;
  const { resultado, estado } = await pedirConEstado<unknown>(`${ruta}/${encodeURIComponent(idPublico)}`, {
    metodo: 'GET',
    token,
  });
  if (!resultado.ok) {
    return estado === 404 ? { ok: false, motivo: 'no-encontrado' } : resultado;
  }
  const cobro = esObjeto(resultado.datos) ? leerCobro(resultado.datos[clave]) : null;
  return cobro ? { ok: true, datos: cobro } : { ok: false, motivo: 'servidor' };
}

function leerCheckout(fila: unknown): Checkout | null {
  if (!esObjeto(fila) || typeof fila.url !== 'string' || fila.url === '') {
    return null;
  }
  if (fila.regreso !== null && (typeof fila.regreso !== 'string' || fila.regreso === '')) {
    return null;
  }
  return { url: fila.url, regreso: fila.regreso };
}

/** El cobro leído campo por campo; null si no es del contrato. */
function leerCobro(fila: unknown): Cobro | null {
  if (
    !esObjeto(fila) ||
    typeof fila.id_publico !== 'string' ||
    typeof fila.pedido !== 'string' ||
    !ESTADOS.includes(fila.estado as EstadoDelCobro)
  ) {
    return null;
  }
  return { id_publico: fila.id_publico, pedido: fila.pedido, estado: fila.estado as EstadoDelCobro };
}
