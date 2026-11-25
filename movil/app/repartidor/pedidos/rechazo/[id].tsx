// El rechazo de un pedido asignado en la app del repartidor (openspec/changes/repartidor, repartidor-E16 y E17;
// NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #2: "Aceptar o rechazar con motivo"). Se abre con "Rechazar" en
// la tarjeta del pedido, por su id_publico. Muestra el pedido con la tarjeta del componente 5, sin sus botones, y el
// motivo, obligatorio: sin él, o con puros espacios, el botón no se puede tocar y dice qué falta. Lo que el servidor no
// acepta del motivo va junto al campo. Al rechazarlo, el pedido deja de estar asignado a él, sale de su lista y la app
// vuelve a ella.
//
// La composición es la de la imagen c02-rechazo de la Propuesta 04, como el rechazo de la lavandería: el título
// "Rechazar pedido", el pedido, el motivo y el botón. "Volver" es el "Regresar" del encabezado. Qué pasa después con el
// pedido rechazado espera DD-014 (repartidor-E18).
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { useRegresar } from '../../../../src/navegacion/regresar';
import { hayMotivo } from '../../../../src/pedidos-lavanderia/useRechazo';
import { Boton } from '../../../../src/piezas/Boton';
import { CampoDeTexto } from '../../../../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../../src/piezas/Pantalla';
import { TarjetaDePedidoDeRepartidor } from '../../../../src/piezas/TarjetaDePedidoDeRepartidor';
import { RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR } from '../../../../src/repartidor/rutas';
import { TEXTOS_DEL_REPARTIDOR as TEXTOS } from '../../../../src/repartidor/textos';
import { useRechazoDelRepartidor } from '../../../../src/repartidor/useRechazoDelRepartidor';

export default function RechazoDelPedidoDelRepartidor() {
  const regresar = useRegresar(RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR);
  const { id } = useLocalSearchParams<{ id: string }>();
  const { pedido, enviando, rechazar, errorDelMotivo, errorGeneral } = useRechazoDelRepartidor(id);
  const [motivo, setMotivo] = useState('');

  async function confirmar() {
    if (await rechazar(motivo)) {
      regresar();
    }
  }

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.tituloDelRechazo, alRegresar: regresar }}>
      {pedido ? <TarjetaDePedidoDeRepartidor pedido={pedido} /> : null}
      <CampoDeTexto
        nombre="motivo"
        etiqueta={TEXTOS.motivo}
        valor={motivo}
        alCambiar={setMotivo}
        error={errorDelMotivo}
        multilinea
      />
      <MensajeDeError texto={errorGeneral} />
      <Boton ocupado={enviando} deshabilitado={!hayMotivo(motivo)} onPress={() => void confirmar()}>
        {hayMotivo(motivo) ? TEXTOS.rechazarPedido : TEXTOS.escribeElMotivo}
      </Boton>
    </Pantalla>
  );
}
