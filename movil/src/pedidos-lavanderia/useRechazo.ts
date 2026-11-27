// El rechazo de un pedido con su motivo (pedidos-lavanderia-E12 y E13): manda el motivo al servidor y, si lo rechaza,
// quita el pedido de la lista compartida, para que al volver ya no se vea. Mientras manda, no manda otra vez: un doble
// toque no rechaza dos veces. Lo que el servidor no acepta del motivo (422) va junto al campo; el 409, el pedido ya fue
// respondido (pedidos-lavanderia-E08), y sin conexión, los mensajes generales de la app, arriba del botón.
import { useRef, useState } from 'react';

import { MENSAJES } from '../acceso/mensajes';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { CONTRATO_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA, rechazarPedido, type FallaDeLaAccion } from './api';
import { usePedidosCompartidos } from './ProveedorDePedidos';
import { mensajeDeLaAccion } from './useAccionesDeLaLavanderia';

const { motivo: CAMPO } = CONTRATO_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA;

/** Si el motivo dice algo: sin él, o con puros espacios, la app no deja confirmar el rechazo (E13). */
export function hayMotivo(motivo: string): boolean {
  return motivo.trim() !== '';
}

export function useRechazo(idPublico: string | undefined) {
  const { token } = useSesion();
  const { pedidos, quitar } = usePedidosCompartidos();
  const enCamino = useRef(false);
  const [enviando, setEnviando] = useState(false);
  const [falla, setFalla] = useState<FallaDeLaAccion | null>(null);

  /** Manda el rechazo; true si el servidor lo rechazó y el pedido ya salió de la lista. */
  async function rechazar(motivo: string): Promise<boolean> {
    if (!token || !idPublico || !hayMotivo(motivo) || enCamino.current) {
      return false;
    }
    enCamino.current = true;
    setEnviando(true);
    setFalla(null);
    const resultado = await rechazarPedido(token, idPublico, motivo);
    enCamino.current = false;
    if (resultado.ok) {
      quitar(idPublico);
      return true;
    }
    setEnviando(false);
    setFalla(resultado);
    return false;
  }

  const errorDelMotivo = falla?.motivo === 'invalido' ? falla.errores[CAMPO]?.[0] : undefined;

  return {
    /** El pedido que se rechaza, como está en la lista; null si la lista no lo tiene. */
    pedido: pedidos?.find((pedido) => pedido.id_publico === idPublico) ?? null,
    enviando,
    rechazar,
    errorDelMotivo,
    /** El mensaje que no es del campo: el 409, sin conexión, o un 422 de otro campo. */
    errorGeneral: falla && !errorDelMotivo ? mensajeGeneral(falla) : undefined,
  };
}

function mensajeGeneral(falla: FallaDeLaAccion): string {
  if (falla.motivo === 'invalido') {
    return falla.mensaje || MENSAJES.servidor;
  }
  return mensajeDeLaAccion(falla);
}
