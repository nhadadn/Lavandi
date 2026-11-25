// La API del historial y de las estadísticas de la lavandería, el Módulo B (openspec/changes/historial-de-la-lavanderia,
// "Contrato de la API"; el mismo contrato del comentario de
// servidor/app/Http/Controllers/Api/HistorialDeLaLavanderiaController.php). Las dos rutas van con la sesión de una cuenta
// de socio lavandería, y el servidor solo cuenta los pedidos de sus lavanderías y de su plaza (historial-lavanderia-E02 y
// E06). Como las demás consultas, nunca lanza: devuelve un Resultado.
//
// Todavía sin monto ni calificación (historial-lavanderia-E03, E04, E07 y E08): esperan DD-013, DD-020, DD-012 y DD-052.
// Cuando se respondan, el servidor los agrega como campos nuevos de los mismos objetos.
import { pedir, type Resultado } from '../configuracion/api';
import { esObjeto } from '../configuracion/lectura';

/** El contrato con el servidor, en un solo lugar para ajustarlo. */
export const CONTRATO_DEL_HISTORIAL_DE_LA_LAVANDERIA = {
  /** GET: { "<lista>": PedidoEntregado[] }, de la entrega más reciente a la más antigua, sin paginación. */
  historial: 'lavanderia/historial',
  lista: 'pedidos',
  /** GET: { "pedidos_del_dia": n, "pedidos_de_la_semana": n }. */
  estadisticas: 'lavanderia/estadisticas',
} as const;

/** Un pedido completado de la lavandería: el que llegó a Entregado (design.md del cambio, decisión 1). */
export interface PedidoEntregado {
  id_publico: string;
  folio: string;
  /** El instante del paso a Entregado, en UTC. */
  entregado_en: string;
  /** AAAA-MM-DD: ese instante en la fecha de la plaza, que se muestra tal cual, sin pasarla a la zona del teléfono. */
  fecha: string;
}

/** Los pedidos completados del día y de la semana, de lunes a domingo, en la zona horaria de la plaza. */
export interface EstadisticasDeLaLavanderia {
  pedidos_del_dia: number;
  pedidos_de_la_semana: number;
}

const FECHA = /^\d{4}-\d{2}-\d{2}$/;

/** GET del historial de la lavandería, con la sesión (historial-lavanderia-E01). */
export async function consultarHistorialDeLaLavanderia(token: string): Promise<Resultado<PedidoEntregado[]>> {
  const { historial, lista } = CONTRATO_DEL_HISTORIAL_DE_LA_LAVANDERIA;
  const resultado = await pedir<unknown>(historial, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const filas = esObjeto(resultado.datos) ? resultado.datos[lista] : null;
  const pedidos = Array.isArray(filas) ? filas.map(leerPedidoEntregado) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  if (pedidos === null || !pedidos.every((pedido) => pedido !== null)) {
    return { ok: false, motivo: 'servidor' };
  }
  return { ok: true, datos: pedidos as PedidoEntregado[] };
}

/** GET de las estadísticas de la lavandería, con la sesión (historial-lavanderia-E05). */
export async function consultarEstadisticasDeLaLavanderia(
  token: string,
): Promise<Resultado<EstadisticasDeLaLavanderia>> {
  const resultado = await pedir<unknown>(CONTRATO_DEL_HISTORIAL_DE_LA_LAVANDERIA.estadisticas, {
    metodo: 'GET',
    token,
  });
  if (!resultado.ok) {
    return resultado;
  }
  const datos = resultado.datos;
  if (!esObjeto(datos) || !esConteo(datos.pedidos_del_dia) || !esConteo(datos.pedidos_de_la_semana)) {
    return { ok: false, motivo: 'servidor' };
  }
  return { ok: true, datos: { pedidos_del_dia: datos.pedidos_del_dia, pedidos_de_la_semana: datos.pedidos_de_la_semana } };
}

/**
 * Un pedido entregado leído campo por campo; null si no es del contrato. Lo leen también las entregas del repartidor,
 * con la misma forma (GET /api/repartidor/historial, src/repartidor/api.ts).
 */
export function leerPedidoEntregado(datos: unknown): PedidoEntregado | null {
  if (
    !esObjeto(datos) ||
    typeof datos.id_publico !== 'string' ||
    typeof datos.folio !== 'string' ||
    typeof datos.entregado_en !== 'string' ||
    typeof datos.fecha !== 'string' ||
    !FECHA.test(datos.fecha)
  ) {
    return null;
  }
  return { id_publico: datos.id_publico, folio: datos.folio, entregado_en: datos.entregado_en, fecha: datos.fecha };
}

// Un conteo: un número entero, cero o más.
function esConteo(valor: unknown): valor is number {
  return typeof valor === 'number' && Number.isInteger(valor) && valor >= 0;
}
