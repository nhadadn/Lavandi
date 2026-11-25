// El rechazo de un pedido asignado con su motivo (repartidor-E16 y E17): manda el motivo al servidor y, si lo registra,
// quita el pedido de la lista compartida, porque deja de estar asignado a él. Mientras manda, no manda otra vez: un
// doble toque no rechaza dos veces. Lo que el servidor no acepta del motivo (422) va junto al campo; el 409, la oferta ya
// fue respondida, y sin conexión, los mensajes generales de la app, arriba del botón. Es la misma forma del rechazo de la
// lavandería (src/pedidos-lavanderia/useRechazo.ts), con su misma regla del motivo.
import { useRef, useState } from 'react';

import { MENSAJES } from '../acceso/mensajes';
import { hayMotivo } from '../pedidos-lavanderia/useRechazo';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { CONTRATO_DEL_REPARTIDOR, rechazarPedidoDelRepartidor, type FallaDelRepartidor } from './api';
import { usePedidosDelRepartidorCompartidos } from './ProveedorDePedidos';
import { useRepartidorDeLaCuenta } from './ProveedorDelRepartidor';
import { mensajeDelRepartidor } from './textos';

const { motivo: CAMPO } = CONTRATO_DEL_REPARTIDOR;

export function useRechazoDelRepartidor(idPublico: string | undefined) {
  const { token } = useSesion();
  const { leerDeNuevo } = useRepartidorDeLaCuenta();
  const { pedidos, quitar } = usePedidosDelRepartidorCompartidos();
  const enCamino = useRef(false);
  const [enviando, setEnviando] = useState(false);
  const [falla, setFalla] = useState<FallaDelRepartidor | null>(null);

  /** Manda el rechazo; true si el servidor lo registró y el pedido ya salió de la lista. */
  async function rechazar(motivo: string): Promise<boolean> {
    if (!token || !idPublico || !hayMotivo(motivo) || enCamino.current) {
      return false;
    }
    enCamino.current = true;
    setEnviando(true);
    setFalla(null);
    const resultado = await rechazarPedidoDelRepartidor(token, idPublico, motivo);
    enCamino.current = false;
    if (resultado.ok) {
      quitar(idPublico);
      return true;
    }
    setEnviando(false);
    setFalla(resultado);
    if (resultado.motivo === 'sin-acceso') {
      leerDeNuevo();
    }
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

function mensajeGeneral(falla: FallaDelRepartidor): string {
  if (falla.motivo === 'invalido') {
    return falla.mensaje || MENSAJES.servidor;
  }
  return mensajeDelRepartidor(falla);
}
