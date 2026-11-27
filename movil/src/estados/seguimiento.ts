// Cómo se ve cada uno de los cinco estados en el seguimiento (estados-E02 y E24), según el pedido que dio el servidor.
// La app ubica el estado actual en la lista de los cinco por estado.codigo: los anteriores están cumplidos y los
// siguientes, pendientes (design.md de estados-del-pedido, "Contrato de la API"). Con estado.visible_usuario en false
// (por_pagar), el pedido todavía no entra al flujo y ningún paso es el actual.
//
// Un estado visible que no es ninguno de los cinco tampoco tiene paso actual: la app no nombra un estado fuera de los
// cinco (estados-E02). Qué ve el usuario con "En proceso de entrega" lo decide estados-E12, bloqueado (DD-022, DD-011).
import type { PedidoEnSeguimiento } from './api';

/** cumplido: el pedido ya pasó por él; actual: está en él; pendiente: todavía no llega. */
export type Situacion = 'cumplido' | 'actual' | 'pendiente';

/** Un estado del seguimiento como se pinta. */
export interface PasoALaVista {
  codigo: string;
  /** Uno de los cinco nombres, como lo da el servidor en seguimiento. */
  nombre: string;
  situacion: Situacion;
  /** "10:05", la hora de la plaza en que el pedido llegó a él; null en los pendientes (estados-E24). */
  hora: string | null;
}

/** Los cinco estados del pedido, en el orden en que llegan, cada uno con su situación y su hora. */
export function pasosALaVista({ estado, seguimiento }: Pick<PedidoEnSeguimiento, 'estado' | 'seguimiento'>): PasoALaVista[] {
  const actual = estado.visible_usuario ? seguimiento.findIndex((paso) => paso.codigo === estado.codigo) : -1;
  return seguimiento.map(({ codigo, nombre, ocurrido_en: ocurridoEn }, indice) => {
    const situacion = situacionDelPaso(indice, actual);
    const hora = situacion === 'pendiente' || ocurridoEn === null ? null : horaDeLaPlaza(ocurridoEn);
    return { codigo, nombre, situacion, hora };
  });
}

function situacionDelPaso(indice: number, actual: number): Situacion {
  if (actual === -1 || indice > actual) {
    return 'pendiente';
  }
  return indice < actual ? 'cumplido' : 'actual';
}

/**
 * La hora de un instante ISO 8601 tal como viene en el texto, "10:05" de "2026-12-03T10:05:00-06:00": el servidor ya la
 * da en la hora de la plaza, y la app no la pasa a la del teléfono (estados-E24).
 */
export function horaDeLaPlaza(instante: string): string {
  return instante.slice('AAAA-MM-DDT'.length, 'AAAA-MM-DDTHH:MM'.length);
}
