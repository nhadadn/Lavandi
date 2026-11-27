// El pago del pedido (openspec/changes/pago, pago-E02; NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #10). El
// resumen abre esta pantalla con el id_publico del pedido que el servidor creó al aceptarlo, en el estado previo al pago
// (confirmacion-E07; C-188, ADR-0021).
//
// - Dice dónde se escribe la tarjeta: en la página segura de la pasarela, que se abre aquí mismo. Ninguna pantalla de la
//   app tiene campos de tarjeta (SEG-4; ADR-0010 y su adenda del 2026-11-22).
// - "Pagar con tarjeta" pide el checkout al servidor, solo con el pedido y el método, y lo abre en la vista de navegador
//   del sistema, sin salir de la app (src/pago/checkout.ts). Al volver, la app pasa a la espera "Procesando pago", que
//   consulta el cobro hasta que el servidor lo da por pagado o rechazado (pago-E08). Volver no confirma nada (pago-E07).
// - Si el servidor informa que el intento se rechazó, la espera vuelve aquí y la pantalla lo dice; "Pagar con tarjeta"
//   pide otra vez el checkout, del mismo cobro (pago-E04).
// - Si el servidor no da el checkout, su mensaje (409, 503) y el botón para intentar de nuevo; sin conexión o con el
//   servidor caído, sin escenario propio, los mensajes generales de la app.
//
// Del mock A10 no entran el total (DD-012), la tarjeta guardada (DD-055 y DD-079), el efectivo (DD-056) ni el nombre de
// la pasarela, que puede cambiar (DD-079).
import { useLocalSearchParams } from 'expo-router';

import { useRegresar } from '../../../src/navegacion/regresar';
import { INTENTO_RECHAZADO } from '../../../src/pago/rutas';
import { TEXTOS_DEL_PAGO } from '../../../src/pago/textos';
import { usePagoConTarjeta } from '../../../src/pago/usePagoConTarjeta';
import { RUTA_DEL_RESUMEN } from '../../../src/pedido/rutas';
import { Boton } from '../../../src/piezas/Boton';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { Titulo } from '../../../src/piezas/Titulo';

export default function Pago() {
  const { id, intento } = useLocalSearchParams<{ id: string; intento?: string }>();
  const regresar = useRegresar(RUTA_DEL_RESUMEN);
  // Después de un intento rechazado, la espera abre esta pantalla para decirlo (pago-E04 y pago-E08).
  const { pagar, ocupado, mensaje } = usePagoConTarjeta(
    id,
    intento === INTENTO_RECHAZADO ? TEXTOS_DEL_PAGO.rechazado : undefined,
  );

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS_DEL_PAGO.encabezado, alRegresar: regresar }}
      acciones={
        <>
          <MensajeDeError texto={mensaje} />
          <Boton ocupado={ocupado} onPress={() => void pagar()}>
            {TEXTOS_DEL_PAGO.pagarConTarjeta}
          </Boton>
        </>
      }
    >
      <Titulo alineacion="inicio">{TEXTOS_DEL_PAGO.titulo}</Titulo>
      <Parrafo alineacion="inicio">{TEXTOS_DEL_PAGO.dondeSePaga}</Parrafo>
      <Parrafo alineacion="inicio">{TEXTOS_DEL_PAGO.sinDatosDeTarjeta}</Parrafo>
    </Pantalla>
  );
}
