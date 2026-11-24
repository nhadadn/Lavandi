// El historial de entregas del repartidor, del Módulo C (openspec/changes/repartidor, repartidor-E25;
// NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #4). Se abre desde sus pedidos asignados. Pide al servidor, con
// la sesión, sus entregas completadas, las que él marcó Entregado, y las muestra como llegan, de la más reciente a la más
// antigua: cada una con su folio y la fecha en que la entregó, en la fecha de la plaza, con la tarjeta del historial de
// la lavandería. La que sigue en curso y la de otro repartidor no llegan. Sin ninguna, un mensaje neutro; si no llega,
// sin escenario propio, los mensajes generales de la app; si ya no opera (403), el módulo lo lleva a la espera.
//
// La composición sigue la imagen c04-historial de la Propuesta 04, con el encabezado y "Regresar" de las demás
// pantallas. Del mock no entran el selector de periodo (proposal.md, "Fuera del contrato"), el monto (DD-019 y DD-013,
// repartidor-E26) ni la calificación recibida (DD-052, repartidor-E27).
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { TarjetaDePedidoEntregado } from '../../src/historial-lavanderia/TarjetaDePedidoEntregado';
import { useRegresar } from '../../src/navegacion/regresar';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { consultarHistorialDelRepartidor } from '../../src/repartidor/api';
import { RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR } from '../../src/repartidor/rutas';
import { mensajeDelRepartidor, TEXTOS_DEL_REPARTIDOR as TEXTOS } from '../../src/repartidor/textos';
import { useLecturaDelRepartidor } from '../../src/repartidor/useLecturaDelRepartidor';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

export default function HistorialDelRepartidor() {
  const regresar = useRegresar(RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR);
  const estado = useLecturaDelRepartidor(consultarHistorialDelRepartidor);

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.historialDeEntregas, alRegresar: regresar }}>
      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoElHistorial}</Text>
        </View>
      ) : null}
      {estado.fase === 'lista' && estado.datos.length === 0 ? (
        <Parrafo alineacion="inicio">{TEXTOS.sinEntregas}</Parrafo>
      ) : null}
      {estado.fase === 'lista'
        ? estado.datos.map((pedido) => <TarjetaDePedidoEntregado key={pedido.id_publico} pedido={pedido} />)
        : null}
      <MensajeDeError texto={estado.fase === 'error' ? mensajeDelRepartidor(estado.falla) : undefined} />
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
