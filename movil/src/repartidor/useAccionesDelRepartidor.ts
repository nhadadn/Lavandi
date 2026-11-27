// Lo que el repartidor manda desde la tarjeta de un pedido: aceptar la oferta (repartidor-E15) o tocar el botón de su
// paso siguiente, Recolectado o En tránsito (repartidor-E19). Mientras se manda, la tarjeta de ese pedido se bloquea: un
// doble toque no manda dos veces. Con la respuesta, la tarjeta se pone al día con el pedido que devolvió el servidor y la
// lista se vuelve a consultar. Si el servidor responde 409, la oferta ya fue respondida o ese ya no es su paso siguiente
// (repartidor-E20): la pantalla muestra su mensaje y la lista se vuelve a consultar, para verlo como está. Si responde
// 403, el repartidor ya no opera: el módulo vuelve a pedir su perfil y lo lleva a la espera (repartidor-E03 y E06). Sin
// conexión, sin escenario propio: los mensajes generales de la app, y el pedido sigue igual.
//
// Es la misma forma de las acciones de la lavandería (src/pedidos-lavanderia/useAccionesDeLaLavanderia.ts). El mensaje
// queda a la vista hasta que el repartidor manda otra acción.
import { useRef, useState } from 'react';

import { useSesion } from '../sesion/ProveedorDeSesion';
import {
  aceptarPedidoDelRepartidor,
  marcarPasoDelRepartidor,
  type PedidoDelRepartidor,
  type ResultadoDelRepartidor,
} from './api';
import { useRepartidorDeLaCuenta } from './ProveedorDelRepartidor';
import { mensajeDelRepartidor } from './textos';
import type { ListaDelRepartidor } from './usePedidosDelRepartidor';

export function useAccionesDelRepartidor({
  reemplazar,
  consultarDeNuevo,
}: Pick<ListaDelRepartidor, 'reemplazar' | 'consultarDeNuevo'>) {
  const { token } = useSesion();
  const { leerDeNuevo } = useRepartidorDeLaCuenta();
  // Los pedidos cuya acción está en camino. La referencia ataja el segundo toque antes de que la tarjeta se bloquee.
  const enCamino = useRef(new Set<string>());
  const [enviando, setEnviando] = useState<ReadonlySet<string>>(new Set());
  const [aviso, setAviso] = useState<string | null>(null);

  async function mandar(
    pedido: PedidoDelRepartidor,
    accion: (sesion: string) => Promise<ResultadoDelRepartidor<PedidoDelRepartidor>>,
  ) {
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
      setAviso(mensajeDelRepartidor(resultado));
    }
    if (!resultado.ok && resultado.motivo === 'sin-acceso') {
      leerDeNuevo();
    } else if (resultado.ok || resultado.motivo === 'conflicto') {
      consultarDeNuevo();
    }
  }

  return {
    /** Si la acción de ese pedido está en camino: su tarjeta se bloquea. */
    enviandoEl: (pedido: PedidoDelRepartidor) => enviando.has(pedido.id_publico),
    /** El mensaje de la última acción que no salió. */
    aviso,
    aceptar: (pedido: PedidoDelRepartidor) =>
      mandar(pedido, (sesion) => aceptarPedidoDelRepartidor(sesion, pedido.id_publico)),
    /** El botón del paso siguiente, el que dio el servidor; sin él, no hay nada que mandar. */
    avanzar: (pedido: PedidoDelRepartidor) => {
      const { siguiente } = pedido;
      return siguiente
        ? mandar(pedido, (sesion) => marcarPasoDelRepartidor(sesion, pedido.id_publico, siguiente))
        : null;
    },
  };
}
