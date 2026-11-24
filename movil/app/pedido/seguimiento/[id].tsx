// El seguimiento de un pedido en la app del usuario (openspec/changes/estados-del-pedido, estados-E02 y E24;
// NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #12). Se abre con el id_publico del pedido y pide el pedido al
// servidor con la sesión. Lo muestra con el componente 3 del design system: el folio, la lavandería y los cinco estados
// del contrato, el actual con su nombre escrito, los anteriores cumplidos y los siguientes pendientes, y la hora en que
// el pedido llegó a cada estado cumplido y al actual, en la hora de la plaza (estados-E24). Con el pedido que todavía
// espera su pago, ningún estado es el actual.
//
// La composición es la de la imagen a12-seguimiento de la Propuesta 04 y el mock A12: el encabezado "Tu pedido" con
// "Regresar" y el seguimiento en una tarjeta. Sin mapa (ADR-0009). Si el pedido no llega (sin conexión, o 404 porque
// no es de la cuenta, estados-E10), sin escenario propio: los mensajes generales de la app, como la agenda y el perfil.
import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { mensajeDeLaFalla } from '../../../src/cercanas/textos';
import { pasosALaVista } from '../../../src/estados/seguimiento';
import { TEXTOS_DEL_SEGUIMIENTO } from '../../../src/estados/textos';
import { useSeguimiento } from '../../../src/estados/useSeguimiento';
import { useRegresar } from '../../../src/navegacion/regresar';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { SeguimientoDelPedido } from '../../../src/piezas/SeguimientoDelPedido';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, tipografia } from '../../../src/tema/theme';

export default function Seguimiento() {
  const regresar = useRegresar('/inicio');
  const { id } = useLocalSearchParams<{ id: string }>();
  const { pedido, falla } = useSeguimiento(id);

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS_DEL_SEGUIMIENTO.encabezado, alRegresar: regresar }}>
      {pedido === null && falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS_DEL_SEGUIMIENTO.cargando}</Text>
        </View>
      ) : null}
      {pedido ? (
        <SeguimientoDelPedido folio={pedido.folio} lavanderia={pedido.lavanderia.nombre} pasos={pasosALaVista(pedido)} />
      ) : null}
      <MensajeDeError texto={falla ? mensajeDeLaFalla(falla) : undefined} />
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  cargando: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[5],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
});
