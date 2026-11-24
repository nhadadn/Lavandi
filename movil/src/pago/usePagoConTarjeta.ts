// "Pagar con tarjeta" (pago-E02): pide el checkout del pedido al servidor, lo abre dentro de la app y, al volver la
// persona, pasa a la espera de su cobro, que lo consulta (pago-E08). Volver no confirma nada (pago-E07). Mientras pide y
// mientras el checkout está abierto, no pide otro: un doble toque no abre dos.
//
// Si el servidor no da el checkout, o la vista no se pudo abrir, la pantalla de pago se queda con el mensaje y el botón
// para intentar de nuevo.
import { useRouter } from 'expo-router';
import { useRef, useState } from 'react';

import { MENSAJES } from '../acceso/mensajes';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { pedirCheckout } from './api';
import { abrirCheckout } from './checkout';
import { rutaDeLaEspera } from './rutas';
import { mensajeDelCheckout } from './textos';

/** mensajeInicial: lo que la pantalla dice al abrirse, como un intento rechazado; se va al volver a intentar. */
export function usePagoConTarjeta(pedido: string | undefined, mensajeInicial?: string) {
  const router = useRouter();
  const { token } = useSesion();
  const enCamino = useRef(false);
  const [ocupado, setOcupado] = useState(false);
  const [mensaje, setMensaje] = useState<string | undefined>(mensajeInicial);

  function terminar(conMensaje?: string) {
    enCamino.current = false;
    setOcupado(false);
    setMensaje(conMensaje);
  }

  async function pagar(): Promise<void> {
    if (enCamino.current || !token || !pedido) {
      return;
    }
    enCamino.current = true;
    setOcupado(true);
    setMensaje(undefined);
    const resultado = await pedirCheckout(token, pedido);
    if (!resultado.ok) {
      terminar(mensajeDelCheckout(resultado));
      return;
    }
    const { checkout, cobro } = resultado.datos;
    try {
      await abrirCheckout(checkout);
    } catch {
      // Sin escenario propio: la vista del sistema no se abrió. El cobro sigue pendiente y se puede volver a intentar.
      terminar(MENSAJES.servidor);
      return;
    }
    terminar();
    router.replace(rutaDeLaEspera(cobro.id_publico));
  }

  return { pagar, ocupado, mensaje };
}
