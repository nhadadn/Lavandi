// El mensaje de Firebase del cambio de estado de un pedido, con la app al frente (avisos-E15; ADR-0007 y ADR-0008). La
// api lo manda en cada cambio, y trae en data el pedido y su estado nuevo (design.md de avisos, "Lo que recibe la app por
// FCM"): { tipo: "cambio_de_estado", pedido: <id_publico>, estado: <código> }. El de un momento del pedido trae además
// el aviso visible, con los mismos datos.
//
// La mensajería lo entrega aquí (src/avisos/telefono.ts) y aquí lo escucha el seguimiento abierto de ese pedido, que
// consulta de inmediato (src/estados/useSeguimiento.ts). El mensaje no trae el pedido completo: la app lo pide a la api,
// que dice la verdad del estado.
type Escucha = (pedido: string) => void;

const escuchas = new Set<Escucha>();

/** Escucha los cambios de estado que avisa Firebase, por el id_publico del pedido. Devuelve la función que deja de escuchar. */
export function alCambiarElEstado(escucha: Escucha): () => void {
  escuchas.add(escucha);
  return () => {
    escuchas.delete(escucha);
  };
}

/** El pedido de un mensaje de cambio de estado; null si el mensaje es otra cosa. */
function pedidoDelMensaje(datos: unknown): string | null {
  if (!datos || typeof datos !== 'object') {
    return null;
  }
  const { tipo, pedido } = datos as Record<string, unknown>;
  return tipo === 'cambio_de_estado' && typeof pedido === 'string' && pedido !== '' ? pedido : null;
}

/** Entrega a quien escucha el cambio de estado que trae un mensaje de Firebase. Otro mensaje no hace nada. */
export function avisarElCambioDeEstado(datos: unknown): void {
  const pedido = pedidoDelMensaje(datos);
  if (pedido === null) {
    return;
  }
  for (const escucha of [...escuchas]) {
    escucha(pedido);
  }
}

/** Si dos id_publico son el mismo pedido: el ULID no distingue mayúsculas de minúsculas. */
export function mismoPedido(uno: string, otro: string): boolean {
  return uno.toLowerCase() === otro.toLowerCase();
}
