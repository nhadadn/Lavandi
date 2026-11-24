// La selección del tipo de servicio, primer paso del pedido nuevo (openspec/changes/perfil-de-lavanderia,
// perfil-lavanderia-E11, E12 y E13). La abre "Pedir aquí" en el perfil de la lavandería, que deja la lavandería en el
// pedido en curso. Solo se eligen los tipos que esa lavandería ofrece, que son los que entrega el servidor; uno solo
// mientras DD-098 siga abierta. "Continuar" guarda el tipo en el pedido en curso y pasa al paso de fecha y hora; sin
// tipo no avanza y lo dice.
//
// La composición es la de la imagen a06-servicio de la Propuesta 04 y el tablero "Servicio" de la Propuesta 5
// corregida (C-233). Del mock no entran elegir varios tipos (DD-098), los tipos que no ofrece, la descripción de cada
// tipo ni la nota del precio por kilo al pesar, que es del resumen (resumen-y-confirmacion, DD-012).
//
// Sin lavandería en el pedido en curso, por ejemplo con un enlace directo, no hay qué elegir: lleva a la pantalla
// principal, donde se elige la lavandería (cercanas-E15).
import { Redirect, useRouter } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { useRegresar } from '../../src/navegacion/regresar';
import { OpcionDeServicio } from '../../src/pedido/OpcionDeServicio';
import { elegirServicio, usePedidoEnCurso } from '../../src/pedido/pedidoEnCurso';
import { RUTA_DE_FECHA_Y_HORA } from '../../src/pedido/rutas';
import { pasoDelPedido, TEXTOS_DEL_PEDIDO } from '../../src/pedido/textos';
import type { CodigoDeServicio } from '../../src/perfil/api';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Presentacion } from '../../src/piezas/Presentacion';
import { espaciado } from '../../src/tema/theme';

export default function SeleccionDeServicio() {
  const router = useRouter();
  const regresar = useRegresar('/inicio');
  const pedido = usePedidoEnCurso();
  const [elegido, setElegido] = useState<CodigoDeServicio | null>(pedido?.servicio ?? null);
  // Si la persona intentó continuar sin elegir (perfil-lavanderia-E13).
  const [intento, setIntento] = useState(false);

  if (!pedido) {
    return <Redirect href="/inicio" />;
  }

  function continuar() {
    if (elegido === null) {
      setIntento(true);
      return;
    }
    elegirServicio(elegido);
    router.push(RUTA_DE_FECHA_Y_HORA);
  }

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: pasoDelPedido(1), alRegresar: regresar }}
      acciones={<Boton onPress={continuar}>{TEXTOS_DEL_PEDIDO.continuar}</Boton>}
    >
      <Presentacion titulo={TEXTOS_DEL_PEDIDO.tituloDelServicio} texto={pedido.lavanderia.nombre} alineacion="inicio" />
      <View style={estilos.opciones} testID="opciones-de-servicio">
        {pedido.lavanderia.servicios.map((servicio) => (
          <OpcionDeServicio
            key={servicio.codigo}
            servicio={servicio}
            elegida={servicio.codigo === elegido}
            alElegir={() => setElegido(servicio.codigo)}
          />
        ))}
      </View>
      <MensajeDeError texto={intento && elegido === null ? TEXTOS_DEL_PEDIDO.faltaElServicio : undefined} />
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  opciones: {
    gap: espaciado[3],
  },
});
