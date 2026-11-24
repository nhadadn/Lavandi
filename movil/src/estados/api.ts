// La consulta del pedido para su seguimiento (openspec/changes/estados-del-pedido, estados-E02, E15, E16 y E24): por su
// id_publico, con el token de la sesión. Lee solo lo que usa el seguimiento: el folio, la lavandería, el estado actual
// y los cinco estados que ve el usuario, con la hora en que el pedido llegó a cada uno. El contrato completo está en el
// comentario de servidor/app/Http/Controllers/Api/PedidosController.php y en el design.md del cambio ("Contrato de la
// API"). Como las demás consultas, nunca lanza: devuelve un Resultado.
import { pedir, type Resultado } from '../configuracion/api';
import { esObjeto } from '../configuracion/lectura';

/**
 * El contrato de la consulta con el servidor, en un solo lugar para ajustarlo: GET <ruta>/<id_publico>, con la sesión,
 * que contesta { "<clave>": Pedido }. Un pedido de otra cuenta responde 404 (estados-E10).
 */
export const CONTRATO_DEL_PEDIDO = {
  ruta: 'pedidos',
  clave: 'pedido',
} as const;

/** El estado actual del pedido. Con visible_usuario en false (por_pagar), la app no lo nombra. */
export interface EstadoDelPedido {
  codigo: string;
  nombre: string;
  visible_usuario: boolean;
}

/**
 * Uno de los cinco estados que ve el usuario, con la hora en que el pedido llegó a él: ISO 8601 con el desfase de la
 * plaza, o null si todavía no llega (estados-E24).
 */
export interface PasoDelSeguimiento {
  codigo: string;
  nombre: string;
  ocurrido_en: string | null;
}

/** El pedido, con lo que usa su seguimiento. */
export interface PedidoEnSeguimiento {
  id_publico: string;
  /** Vacío hasta Confirmado. */
  folio: string | null;
  estado: EstadoDelPedido;
  /** Los cinco del contrato, en su orden (estados-E01). */
  seguimiento: PasoDelSeguimiento[];
  lavanderia: { nombre: string };
}

// La fecha y la hora al principio de un instante ISO 8601: "2026-12-03T10:05".
const INSTANTE = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/;

/** GET del pedido por su id_publico, con la sesión. */
export async function consultarPedido(token: string, idPublico: string): Promise<Resultado<PedidoEnSeguimiento>> {
  const { ruta, clave } = CONTRATO_DEL_PEDIDO;
  const resultado = await pedir<unknown>(`${ruta}/${encodeURIComponent(idPublico)}`, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const pedido = esObjeto(resultado.datos) ? leerPedido(resultado.datos[clave]) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return pedido ? { ok: true, datos: pedido } : { ok: false, motivo: 'servidor' };
}

function leerPedido(datos: unknown): PedidoEnSeguimiento | null {
  if (
    !esObjeto(datos) ||
    typeof datos.id_publico !== 'string' ||
    !(datos.folio === null || typeof datos.folio === 'string') ||
    !esObjeto(datos.lavanderia) ||
    typeof datos.lavanderia.nombre !== 'string' ||
    !Array.isArray(datos.seguimiento)
  ) {
    return null;
  }
  const estado = leerEstado(datos.estado);
  const seguimiento = datos.seguimiento.map(leerPaso);
  if (!estado || !seguimiento.every((paso) => paso !== null)) {
    return null;
  }
  return {
    id_publico: datos.id_publico,
    folio: datos.folio,
    estado,
    seguimiento: seguimiento as PasoDelSeguimiento[],
    lavanderia: { nombre: datos.lavanderia.nombre },
  };
}

/** El estado de un pedido tal como llega, o null si no es del contrato. Lo lee también la lista de "Mis pedidos". */
export function leerEstado(datos: unknown): EstadoDelPedido | null {
  if (
    !esObjeto(datos) ||
    typeof datos.codigo !== 'string' ||
    typeof datos.nombre !== 'string' ||
    typeof datos.visible_usuario !== 'boolean'
  ) {
    return null;
  }
  return { codigo: datos.codigo, nombre: datos.nombre, visible_usuario: datos.visible_usuario };
}

function leerPaso(fila: unknown): PasoDelSeguimiento | null {
  if (!esObjeto(fila) || typeof fila.codigo !== 'string' || typeof fila.nombre !== 'string') {
    return null;
  }
  const ocurridoEn = fila.ocurrido_en;
  if (!(ocurridoEn === null || esInstante(ocurridoEn))) {
    return null;
  }
  return { codigo: fila.codigo, nombre: fila.nombre, ocurrido_en: ocurridoEn };
}

function esInstante(valor: unknown): valor is string {
  return typeof valor === 'string' && INSTANTE.test(valor) && !Number.isNaN(Date.parse(valor));
}
