// La API de los pedidos de la lavandería, el Módulo B (openspec/changes/pedidos-de-la-lavanderia, "Contrato de la API",
// y openspec/changes/estados-del-pedido, "Los botones de la lavandería"; el mismo contrato del comentario de
// servidor/app/Http/Controllers/Api/PedidosDeLaLavanderiaController.php). Todas las rutas van con la sesión de una
// cuenta de socio lavandería, y el servidor solo le da los pedidos de sus lavanderías, de su plaza y con el pago
// acreditado (C-188). Como las demás consultas, nunca lanza: devuelve un Resultado.
//
// El pedido trae su folio, su estado con el nombre que le da la lavandería, si espera su respuesta, el único botón que
// puede tocar, su servicio y su ventana de recolección. No trae datos del usuario ni direcciones
// (pedidos-lavanderia-E04, DD-022 y DD-014), ni montos.
import type { Ventana } from '../agenda/api';
import { leerVentana } from '../agenda/api';
import { pedir, pedirConEstado, type Falla, type Resultado } from '../configuracion/api';
import { esObjeto } from '../configuracion/lectura';

/** El contrato con el servidor, en un solo lugar para ajustarlo. */
export const CONTRATO_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA = {
  /** GET: { "<lista>": PedidoDeLaLavanderia[] }, de la recolección más próxima a la más lejana. */
  ruta: 'lavanderia/pedidos',
  lista: 'pedidos',
  /** Lo que devuelve cada acción: { "<pedido>": PedidoDeLaLavanderia }, ya en su estado nuevo. */
  pedido: 'pedido',
  /** POST <ruta>/<id_publico>/<aceptar>, sin cuerpo (pedidos-lavanderia-E07). */
  aceptar: 'aceptar',
  /** POST <ruta>/<id_publico>/<rechazar>, con { "<motivo>": "…" }; 422 con errors.<motivo> (E12 y E13). */
  rechazar: 'rechazar',
  motivo: 'motivo',
  /** POST <ruta>/<id_publico>/<estado>, con { "<estado>": siguiente.estado }: el botón del paso siguiente (E06). */
  estado: 'estado',
} as const;

/** El estado operativo del pedido (ADR-0021 y su adenda), con el nombre que le da la app de la lavandería. */
export interface EstadoOperativo {
  codigo: string;
  nombre: string;
}

/** El único botón que la lavandería puede tocar (estados-E06): el estado al que lleva y su texto. */
export interface PasoSiguiente {
  estado: string;
  boton: string;
}

/** Un pedido como lo ve la lavandería que lo atiende. */
export interface PedidoDeLaLavanderia {
  id_publico: string;
  folio: string;
  estado: EstadoOperativo;
  /** true solo mientras espera su respuesta: la tarjeta ofrece Aceptar y Rechazar (pedidos-lavanderia-E03). */
  por_responder: boolean;
  /** null si espera respuesta, si ya está en En proceso de entrega o si salió del flujo. */
  siguiente: PasoSiguiente | null;
  /** Uno por ahora (DD-098), sin precio. */
  servicios: { codigo: string; nombre: string }[];
  /** La recolección, con la fecha y las horas ya en la hora de la plaza. */
  ventana: Ventana;
}

/** GET de los pedidos activos de la lavandería, con la sesión (pedidos-lavanderia-E01 y E03). */
export async function consultarPedidosDeLaLavanderia(token: string): Promise<Resultado<PedidoDeLaLavanderia[]>> {
  const { ruta, lista } = CONTRATO_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA;
  const resultado = await pedir<unknown>(ruta, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const filas = esObjeto(resultado.datos) ? resultado.datos[lista] : null;
  const pedidos = Array.isArray(filas) ? filas.map(leerPedidoDeLaLavanderia) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  if (pedidos === null || !pedidos.every((pedido) => pedido !== null)) {
    return { ok: false, motivo: 'servidor' };
  }
  return { ok: true, datos: pedidos as PedidoDeLaLavanderia[] };
}

/**
 * Por qué no salió una acción sobre un pedido. Además de las fallas generales: el 409, que el servidor da cuando el
 * pedido ya fue respondido (pedidos-lavanderia-E08) o cuando el botón no es el del paso siguiente (estados-E05 y E09),
 * con su mensaje para la persona. El pedido no cambió.
 */
export type FallaDeLaAccion = Falla | { ok: false; motivo: 'conflicto'; mensaje?: string };

export type ResultadoDeLaAccion = { ok: true; datos: PedidoDeLaLavanderia } | FallaDeLaAccion;

/** POST de aceptar el pedido que espera respuesta, con la sesión (pedidos-lavanderia-E07). */
export function aceptarPedido(token: string, idPublico: string): Promise<ResultadoDeLaAccion> {
  return mandarAccion(token, idPublico, CONTRATO_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA.aceptar);
}

/**
 * POST de rechazar el pedido que espera respuesta, con su motivo, sin los espacios de las orillas
 * (pedidos-lavanderia-E12). El servidor exige el motivo y su largo máximo vive en su configuración: lo que no acepta lo
 * dice con 422 en el campo (pedidos-lavanderia-E13).
 */
export function rechazarPedido(token: string, idPublico: string, motivo: string): Promise<ResultadoDeLaAccion> {
  const { rechazar, motivo: campo } = CONTRATO_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA;
  return mandarAccion(token, idPublico, rechazar, { [campo]: motivo.trim() });
}

/**
 * POST del botón del paso siguiente, con el estado al que lleva, tal como lo dio el servidor en "siguiente"
 * (estados-E06). Si ese ya no es el paso siguiente, el servidor responde 409 y el pedido no cambia (estados-E05 y E09).
 */
export function marcarPasoSiguiente(
  token: string,
  idPublico: string,
  paso: PasoSiguiente,
): Promise<ResultadoDeLaAccion> {
  const { estado } = CONTRATO_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA;
  return mandarAccion(token, idPublico, estado, { [estado]: paso.estado });
}

async function mandarAccion(
  token: string,
  idPublico: string,
  accion: string,
  cuerpo?: unknown,
): Promise<ResultadoDeLaAccion> {
  const { ruta, pedido } = CONTRATO_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA;
  const { resultado, estado } = await pedirConEstado<unknown>(`${ruta}/${encodeURIComponent(idPublico)}/${accion}`, {
    metodo: 'POST',
    token,
    cuerpo,
  });
  if (!resultado.ok) {
    if (estado === 409) {
      return { ok: false, motivo: 'conflicto', mensaje: 'mensaje' in resultado ? resultado.mensaje : undefined };
    }
    return resultado;
  }
  const respondido = esObjeto(resultado.datos) ? leerPedidoDeLaLavanderia(resultado.datos[pedido]) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return respondido ? { ok: true, datos: respondido } : { ok: false, motivo: 'servidor' };
}

/** Un pedido leído campo por campo, como lo dan todas las respuestas de la lavandería; null si no es del contrato. */
export function leerPedidoDeLaLavanderia(datos: unknown): PedidoDeLaLavanderia | null {
  if (
    !esObjeto(datos) ||
    typeof datos.id_publico !== 'string' ||
    typeof datos.folio !== 'string' ||
    typeof datos.por_responder !== 'boolean' ||
    !Array.isArray(datos.servicios)
  ) {
    return null;
  }
  const estado = leerEstado(datos.estado);
  const siguiente = datos.siguiente === null ? null : leerPaso(datos.siguiente);
  const servicios = datos.servicios.map(leerServicio);
  const ventana = leerVentana(datos.ventana);
  if (!estado || siguiente === undefined || !ventana || !servicios.every((servicio) => servicio !== null)) {
    return null;
  }
  return {
    id_publico: datos.id_publico,
    folio: datos.folio,
    estado,
    por_responder: datos.por_responder,
    siguiente,
    servicios: servicios as PedidoDeLaLavanderia['servicios'],
    ventana,
  };
}

function leerEstado(datos: unknown): EstadoOperativo | null {
  if (!esObjeto(datos) || typeof datos.codigo !== 'string' || typeof datos.nombre !== 'string') {
    return null;
  }
  return { codigo: datos.codigo, nombre: datos.nombre };
}

// El paso siguiente, o undefined si no es del contrato (null ya se leyó antes: no hay botón).
function leerPaso(datos: unknown): PasoSiguiente | undefined {
  if (!esObjeto(datos) || typeof datos.estado !== 'string' || typeof datos.boton !== 'string') {
    return undefined;
  }
  return { estado: datos.estado, boton: datos.boton };
}

function leerServicio(datos: unknown): { codigo: string; nombre: string } | null {
  if (!esObjeto(datos) || typeof datos.codigo !== 'string' || typeof datos.nombre !== 'string') {
    return null;
  }
  return { codigo: datos.codigo, nombre: datos.nombre };
}
