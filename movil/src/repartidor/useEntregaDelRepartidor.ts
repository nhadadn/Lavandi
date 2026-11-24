// La entrega de un pedido con su foto de evidencia (repartidor-E23): manda Entregado y, si la hay, la foto, juntos; si el
// servidor lo marca, quita el pedido de la lista compartida, porque ya no está en curso (va a su historial,
// repartidor-E25). Mientras manda, no manda otra vez: un doble toque no entrega dos veces. Lo que el servidor no acepta
// de la foto (422) va junto a ella; el 409, Entregado ya no es su paso siguiente, y sin conexión, los mensajes
// generales de la app, arriba del botón. El 403 del repartidor que ya no opera lo lleva a la espera (repartidor-E03).
//
// Si la evidencia es obligatoria espera BR-29 y DD-110 (repartidor-E24): como el servidor, la app la manda si la hay.
import { useRef, useState } from 'react';

import { MENSAJES } from '../acceso/mensajes';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { CONTRATO_DEL_REPARTIDOR, entregarPedidoDelRepartidor, type FallaDelRepartidor } from './api';
import { usePedidosDelRepartidorCompartidos } from './ProveedorDePedidos';
import { useRepartidorDeLaCuenta } from './ProveedorDelRepartidor';
import { mensajeDelRepartidor } from './textos';

const { foto: CAMPO_DE_LA_FOTO } = CONTRATO_DEL_REPARTIDOR;

export function useEntregaDelRepartidor(idPublico: string | undefined) {
  const { token } = useSesion();
  const { leerDeNuevo } = useRepartidorDeLaCuenta();
  const { pedidos, quitar } = usePedidosDelRepartidorCompartidos();
  const enCamino = useRef(false);
  const [enviando, setEnviando] = useState(false);
  const [falla, setFalla] = useState<FallaDelRepartidor | null>(null);

  /** Manda Entregado con la foto, si la hay; true si el servidor lo marcó y el pedido ya salió de la lista. */
  async function entregar(foto: string | null): Promise<boolean> {
    if (!token || !idPublico || enCamino.current) {
      return false;
    }
    enCamino.current = true;
    setEnviando(true);
    setFalla(null);
    const resultado = await entregarPedidoDelRepartidor(token, idPublico, foto ? { uri: foto } : null);
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

  const errorDeLaFoto = falla?.motivo === 'invalido' ? falla.errores[CAMPO_DE_LA_FOTO]?.[0] : undefined;

  return {
    /** El pedido que se entrega, como está en la lista; null si la lista no lo tiene. */
    pedido: pedidos?.find((pedido) => pedido.id_publico === idPublico) ?? null,
    enviando,
    entregar,
    errorDeLaFoto,
    /** El mensaje que no es de la foto: el 409, sin conexión, o un 422 de otro campo. */
    errorGeneral: falla && !errorDeLaFoto ? mensajeGeneral(falla) : undefined,
  };
}

function mensajeGeneral(falla: FallaDelRepartidor): string {
  if (falla.motivo === 'invalido') {
    return falla.mensaje || MENSAJES.servidor;
  }
  return mensajeDelRepartidor(falla);
}
