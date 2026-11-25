// El historial de pedidos de la lavandería, del Módulo B (openspec/changes/historial-de-la-lavanderia,
// historial-lavanderia-E01; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #4). Se abre desde la pantalla de
// pedidos. Pide al servidor, con la sesión, los pedidos completados de la lavandería, los que llegaron a Entregado, y los
// muestra como llegan, de la entrega más reciente a la más antigua: cada uno con su folio y la fecha en que se entregó,
// en la fecha de la plaza. El que sigue en proceso no llega. Sin ninguno, un mensaje neutro; si no llega, sin escenario
// propio, los mensajes generales de la app, como "Mis pedidos".
//
// La composición sigue la imagen b04-historial de la Propuesta 04, con el encabezado y "Regresar" de las demás
// pantallas. Del mock no entran el selector de periodo (proposal.md, "Fuera del contrato"), el monto (DD-013, DD-020 y
// DD-012) ni la calificación recibida (DD-052).
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { mensajeDeLaFalla } from '../../src/cercanas/textos';
import { consultarHistorialDeLaLavanderia } from '../../src/historial-lavanderia/api';
import { TarjetaDePedidoEntregado } from '../../src/historial-lavanderia/TarjetaDePedidoEntregado';
import { TEXTOS_DEL_HISTORIAL_DE_LA_LAVANDERIA as TEXTOS } from '../../src/historial-lavanderia/textos';
import { useLecturaDeLaLavanderia } from '../../src/historial-lavanderia/useLecturaDeLaLavanderia';
import { useRegresar } from '../../src/navegacion/regresar';
import { RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA } from '../../src/pedidos-lavanderia/rutas';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

export default function HistorialDeLaLavanderia() {
  const regresar = useRegresar(RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA);
  const estado = useLecturaDeLaLavanderia(consultarHistorialDeLaLavanderia);

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.historial, alRegresar: regresar }}>
      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoElHistorial}</Text>
        </View>
      ) : null}
      {estado.fase === 'lista' && estado.datos.length === 0 ? (
        <Parrafo alineacion="inicio">{TEXTOS.sinEntregados}</Parrafo>
      ) : null}
      {estado.fase === 'lista'
        ? estado.datos.map((pedido) => <TarjetaDePedidoEntregado key={pedido.id_publico} pedido={pedido} />)
        : null}
      <MensajeDeError texto={estado.fase === 'error' ? mensajeDeLaFalla(estado.falla) : undefined} />
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
