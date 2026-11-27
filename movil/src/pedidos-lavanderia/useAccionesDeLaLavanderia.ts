// Lo que la lavandería manda desde la tarjeta de un pedido: aceptarlo (pedidos-lavanderia-E07) o tocar el botón del
// paso siguiente (estados-E06). Mientras se manda, la tarjeta de ese pedido se bloquea: un doble toque no manda dos
// veces. Con la respuesta, la tarjeta se pone al día con el pedido que devolvió el servidor y la lista se vuelve a
// consultar. Si el servidor responde 409, el pedido ya fue respondido (pedidos-lavanderia-E08) o ese ya no es su paso
// siguiente (estados-E05 y E09): la pantalla muestra su mensaje y la lista se vuelve a consultar, para verlo como está.
// Si no se llega al servidor, sin escenario propio: los mensajes generales de la app, y el pedido sigue igual.
//
// El mensaje queda a la vista hasta que la lavandería manda otra acción.
import { useRef, useState } from 'react';

import { mensajeDeLaFalla } from '../cercanas/textos';
import { useSesion } from '../sesion/ProveedorDeSesion';
import {
  aceptarPedido,
  marcarPasoSiguiente,
  type FallaDeLaAccion,
  type PedidoDeLaLavanderia,
  type ResultadoDeLaAccion,
} from './api';
import { TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA as TEXTOS } from './textos';
import type { ListaDeLaLavanderia } from './usePedidosDeLaLavanderia';

/** El mensaje de una acción que no salió: el del servidor con el 409, o el general de la app. */
export function mensajeDeLaAccion(falla: FallaDeLaAccion): string {
  if (falla.motivo === 'conflicto') {
    return falla.mensaje || TEXTOS.yaCambio;
  }
  return mensajeDeLaFalla(falla);
}

export function useAccionesDeLaLavanderia({
  reemplazar,
  consultarDeNuevo,
}: Pick<ListaDeLaLavanderia, 'reemplazar' | 'consultarDeNuevo'>) {
  const { token } = useSesion();
  // Los pedidos cuya acción está en camino. La referencia ataja el segundo toque antes de que la tarjeta se bloquee.
  const enCamino = useRef(new Set<string>());
  const [enviando, setEnviando] = useState<ReadonlySet<string>>(new Set());
  const [aviso, setAviso] = useState<string | null>(null);

  async function mandar(pedido: PedidoDeLaLavanderia, accion: (sesion: string) => Promise<ResultadoDeLaAccion>) {
    const id = pedido.id_publico;
    if (!token || enCamino.current.has(id)) {
      return;
    }
    enCamino.current.add(id);
    setEnviando(new Set(enCamino.current));
    setAviso(null);

    const resultado = await accion(token);

    enCamino.current.delete(id);
    setEnviando(new Set(enCamino.current));
    if (resultado.ok) {
      reemplazar(resultado.datos);
    } else {
      setAviso(mensajeDeLaAccion(resultado));
    }
    if (resultado.ok || resultado.motivo === 'conflicto') {
      consultarDeNuevo();
    }
  }

  return {
    /** Si la acción de ese pedido está en camino: su tarjeta se bloquea. */
    enviandoEl: (pedido: PedidoDeLaLavanderia) => enviando.has(pedido.id_publico),
    /** El mensaje de la última acción que no salió. */
    aviso,
    aceptar: (pedido: PedidoDeLaLavanderia) => mandar(pedido, (sesion) => aceptarPedido(sesion, pedido.id_publico)),
    /** El botón del paso siguiente, el que dio el servidor; sin él, no hay nada que mandar. */
    avanzar: (pedido: PedidoDeLaLavanderia) => {
      const { siguiente } = pedido;
      return siguiente ? mandar(pedido, (sesion) => marcarPasoSiguiente(sesion, pedido.id_publico, siguiente)) : null;
    },
  };
}
