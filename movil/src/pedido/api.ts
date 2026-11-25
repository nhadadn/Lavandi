// El pedido en la API (openspec/changes/resumen-y-confirmacion): crearlo al aceptar el resumen, antes del pago
// (confirmacion-E05 y E07), y consultarlo por su id_publico para la confirmación (confirmacion-E09). Todo va con el
// token de la sesión. El contrato exacto, con cada cuerpo y cada error, está en el comentario de
// servidor/app/Http/Controllers/Api/PedidosController.php. Como las demás consultas, nunca lanza: devuelve un
// Resultado.
//
// Ninguna petición de la app confirma un pedido: lo confirma el cobro cuando la pasarela confirma el pago (C-188;
// confirmacion-E07). La app manda solo lo que lee servidor/app/Http/Requests/CrearPedidoRequest.php.
import { leerVentana, type Ventana } from '../agenda/api';
import { pedirConEstado, type Falla } from '../configuracion/api';
import { esObjeto } from '../configuracion/lectura';
import { rechazosDe, type Rechazo } from '../direcciones/api';
import type { ParaQueEsLaDireccion } from './pedidoEnCurso';

/** Las rutas y los nombres del pedido, en un solo lugar para ajustarlos. */
export const CONTRATO_DEL_PEDIDO = {
  ruta: 'pedidos',
  clave: 'pedido',
  // El código del estado Confirmado en estados_pedido (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #12).
  confirmado: 'confirmado',
} as const;

/** Lo que la app manda al aceptar el resumen: la lavandería, el servicio, la ventana y las direcciones. */
export interface SolicitudDePedido {
  /** El id_publico de la lavandería. */
  lavanderia: string;
  /** El código del servicio, de los que da su perfil. */
  servicio: string;
  /** Tal como la dio la agenda, sin cambiarla. */
  ventana: Pick<Ventana, 'inicio' | 'fin'>;
  /** El id_publico de la dirección de recogida. */
  recogida: string;
  /** El de la entrega; null si se entrega en la recogida. */
  entrega: string | null;
}

/** La copia de una dirección que guardó el pedido, no la dirección guardada (confirmacion-E14). */
export interface DireccionDelPedido {
  alias: string | null;
  calle: string | null;
  numero_exterior: string | null;
  numero_interior: string | null;
  colonia: string;
  codigo_postal: string | null;
  municipio: string | null;
  referencias: string | null;
}

/**
 * El pedido como lo devuelve el servidor, con lo que la app muestra. El precio, la cantidad, el envío y la zona horaria
 * no se leen: los montos esperan DD-012, DD-010, DD-019 y DD-025, y la ventana ya viene en la hora de la plaza.
 */
export interface Pedido {
  id_publico: string;
  /** Vacío hasta Confirmado. Su formato lo fija ADR-0020. */
  folio: string | null;
  /** El instante en que pasó a Confirmado, en UTC; vacío hasta entonces. */
  confirmado_en: string | null;
  /** En el estado previo al pago, visible_usuario es false y la app no lo nombra (DD-123, ADR-0021). */
  estado: { codigo: string; nombre: string; visible_usuario: boolean };
  lavanderia: { id_publico: string; nombre: string };
  /** Uno por ahora (DD-098). */
  servicios: { codigo: string; nombre: string }[];
  ventana: Ventana;
  recogida: DireccionDelPedido;
  entrega: DireccionDelPedido;
}

/**
 * Por qué no se creó el pedido. Además de las fallas generales: la ventana ya no se ofrece (confirmacion-E08), la
 * recogida o la entrega ya no sirven, cada una con su motivo (confirmacion-E08 y E15), o el servidor no lo puede crear
 * y lo dice con un mensaje para la persona (404 de la lavandería o de la dirección, 503 de la agenda o del estado previo
 * al pago).
 */
export type FallaAlCrear =
  | Falla
  | { ok: false; motivo: 'ventana'; mensaje: string }
  | { ok: false; motivo: 'rechazadas'; rechazos: Partial<Record<ParaQueEsLaDireccion, Rechazo>> }
  | { ok: false; motivo: 'no-se-puede'; mensaje: string };

/** Los códigos con los que el servidor dice, con un mensaje para la persona, que no puede crear el pedido. */
const NO_SE_PUEDE = [404, 503];

/** POST del resumen aceptado, con la sesión. Devuelve el pedido creado en el estado previo al pago. */
export async function crearPedido(
  token: string,
  solicitud: SolicitudDePedido,
): Promise<{ ok: true; datos: Pedido } | FallaAlCrear> {
  const { ruta, clave } = CONTRATO_DEL_PEDIDO;
  const { resultado, estado } = await pedirConEstado<unknown>(ruta, { metodo: 'POST', token, cuerpo: solicitud });
  if (resultado.ok) {
    const pedido = esObjeto(resultado.datos) ? leerPedido(resultado.datos[clave]) : null;
    // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
    return pedido ? { ok: true, datos: pedido } : { ok: false, motivo: 'servidor' };
  }
  if (resultado.motivo === 'invalido') {
    // La ventana va en "ventana", o en "ventana.inicio" y "ventana.fin" si faltan (CrearPedidoRequest).
    const campoDeLaVentana = Object.keys(resultado.errores).find((campo) => campo.split('.')[0] === 'ventana');
    if (campoDeLaVentana) {
      return { ok: false, motivo: 'ventana', mensaje: resultado.errores[campoDeLaVentana][0] ?? resultado.mensaje };
    }
    const rechazos = rechazosDe(resultado.motivosPorCampo, resultado.errores);
    if (rechazos) {
      return { ok: false, motivo: 'rechazadas', rechazos };
    }
  }
  if (resultado.motivo === 'servidor' && resultado.mensaje && estado !== null && NO_SE_PUEDE.includes(estado)) {
    return { ok: false, motivo: 'no-se-puede', mensaje: resultado.mensaje };
  }
  return resultado;
}

/** El pedido no existe, es de otra cuenta o el valor no es su id_publico: el servidor responde igual (confirmacion-E12). */
export type FallaAlConsultar = Falla | { ok: false; motivo: 'no-encontrado' };

/** GET de un pedido de la cuenta por su id_publico, con la sesión (confirmacion-E09). */
export async function consultarPedido(
  token: string,
  idPublico: string,
): Promise<{ ok: true; datos: Pedido } | FallaAlConsultar> {
  const { ruta, clave } = CONTRATO_DEL_PEDIDO;
  const { resultado, estado } = await pedirConEstado<unknown>(`${ruta}/${encodeURIComponent(idPublico)}`, {
    metodo: 'GET',
    token,
  });
  if (!resultado.ok) {
    return estado === 404 ? { ok: false, motivo: 'no-encontrado' } : resultado;
  }
  const pedido = esObjeto(resultado.datos) ? leerPedido(resultado.datos[clave]) : null;
  return pedido ? { ok: true, datos: pedido } : { ok: false, motivo: 'servidor' };
}

/**
 * Si el servidor ya pasó el pedido a Confirmado, después de su pago: el estado confirmado, con folio y confirmado_en
 * (confirmacion-E09 y E13). Sin alguno de los tres, no se da por confirmado.
 */
export function estaConfirmado(pedido: Pedido): boolean {
  return (
    pedido.estado.codigo === CONTRATO_DEL_PEDIDO.confirmado && pedido.folio !== null && pedido.confirmado_en !== null
  );
}

/** Un pedido leído campo por campo; null si no es del contrato. */
function leerPedido(fila: unknown): Pedido | null {
  if (!esObjeto(fila) || typeof fila.id_publico !== 'string') {
    return null;
  }
  const folio = textoOVacio(fila.folio);
  const confirmadoEn = textoOVacio(fila.confirmado_en);
  const estado = leerEstado(fila.estado);
  const lavanderia = esObjeto(fila.lavanderia) ? fila.lavanderia : null;
  const servicios = Array.isArray(fila.servicios) ? fila.servicios.map(leerServicio) : null;
  const ventana = leerVentana(fila.ventana);
  const recogida = leerDireccionDelPedido(fila.recogida);
  const entrega = leerDireccionDelPedido(fila.entrega);
  if (
    folio === undefined ||
    confirmadoEn === undefined ||
    !estado ||
    !lavanderia ||
    typeof lavanderia.id_publico !== 'string' ||
    typeof lavanderia.nombre !== 'string' ||
    !servicios ||
    !servicios.every((servicio) => servicio !== null) ||
    !ventana ||
    !recogida ||
    !entrega
  ) {
    return null;
  }
  return {
    id_publico: fila.id_publico,
    folio,
    confirmado_en: confirmadoEn,
    estado,
    lavanderia: { id_publico: lavanderia.id_publico, nombre: lavanderia.nombre },
    servicios: servicios as Pedido['servicios'],
    ventana,
    recogida,
    entrega,
  };
}

function leerEstado(fila: unknown): Pedido['estado'] | null {
  if (
    !esObjeto(fila) ||
    typeof fila.codigo !== 'string' ||
    typeof fila.nombre !== 'string' ||
    typeof fila.visible_usuario !== 'boolean'
  ) {
    return null;
  }
  return { codigo: fila.codigo, nombre: fila.nombre, visible_usuario: fila.visible_usuario };
}

function leerServicio(fila: unknown): Pedido['servicios'][number] | null {
  if (!esObjeto(fila) || typeof fila.codigo !== 'string' || typeof fila.nombre !== 'string') {
    return null;
  }
  return { codigo: fila.codigo, nombre: fila.nombre };
}

// Las señas de la copia que pueden venir vacías; la colonia siempre está (direcciones-E02).
const SENAS_OPCIONALES = [
  'alias',
  'calle',
  'numero_exterior',
  'numero_interior',
  'codigo_postal',
  'municipio',
  'referencias',
] as const;

/**
 * La copia de una dirección que guardó el pedido, leída campo por campo; null si no es del contrato. La leen también los
 * pedidos del repartidor, que traen la recogida y la entrega con la misma forma (src/repartidor/api.ts).
 */
export function leerDireccionDelPedido(fila: unknown): DireccionDelPedido | null {
  if (!esObjeto(fila) || typeof fila.colonia !== 'string') {
    return null;
  }
  const senas = SENAS_OPCIONALES.map((campo) => textoOVacio(fila[campo]));
  if (senas.some((sena) => sena === undefined)) {
    return null;
  }
  const [alias, calle, numero_exterior, numero_interior, codigo_postal, municipio, referencias] = senas as (
    | string
    | null
  )[];
  return {
    alias,
    calle,
    numero_exterior,
    numero_interior,
    colonia: fila.colonia,
    codigo_postal,
    municipio,
    referencias,
  };
}

// Texto o null; cualquier otra cosa no es del contrato (undefined).
function textoOVacio(valor: unknown): string | null | undefined {
  return valor === null || typeof valor === 'string' ? valor : undefined;
}
