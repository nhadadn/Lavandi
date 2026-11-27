// El rechazo de un pedido en la app de la lavandería (openspec/changes/pedidos-de-la-lavanderia, pedidos-lavanderia-E12
// y E13; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2: "Aceptar o rechazar con motivo"). Se abre con
// "Rechazar" en la tarjeta del pedido, por su id_publico. Muestra el pedido con la tarjeta del componente 4, sin sus
// botones, y el motivo, obligatorio: sin él, o con puros espacios, el botón no se puede tocar y dice qué falta. Lo que
// el servidor no acepta del motivo va junto al campo. Al rechazarlo, el pedido sale de la lista y la app vuelve a ella.
//
// La composición es la de la imagen b02-rechazo de la Propuesta 04: el título "Rechazar pedido", el pedido, el motivo y
// el botón. "Volver" es el "Regresar" del encabezado, como en las demás pantallas. Qué pasa después con el pedido, el
// cobro y lo que ve el usuario espera DD-016, DD-012 y DD-103 (pedidos-lavanderia-E14).
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { useRegresar } from '../../../../src/navegacion/regresar';
import { RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA } from '../../../../src/pedidos-lavanderia/rutas';
import { TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA as TEXTOS } from '../../../../src/pedidos-lavanderia/textos';
import { hayMotivo, useRechazo } from '../../../../src/pedidos-lavanderia/useRechazo';
import { Boton } from '../../../../src/piezas/Boton';
import { CampoDeTexto } from '../../../../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../../src/piezas/Pantalla';
import { TarjetaDePedidoDeLavanderia } from '../../../../src/piezas/TarjetaDePedidoDeLavanderia';

export default function RechazoDelPedido() {
  const regresar = useRegresar(RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA);
  const { id } = useLocalSearchParams<{ id: string }>();
  const { pedido, enviando, rechazar, errorDelMotivo, errorGeneral } = useRechazo(id);
  const [motivo, setMotivo] = useState('');

  async function confirmar() {
    if (await rechazar(motivo)) {
      regresar();
    }
  }

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.tituloDelRechazo, alRegresar: regresar }}>
      {pedido ? <TarjetaDePedidoDeLavanderia pedido={pedido} /> : null}
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
