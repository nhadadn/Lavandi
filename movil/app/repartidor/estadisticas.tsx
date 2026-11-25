// Las estadísticas del repartidor, del Módulo C (openspec/changes/repartidor, repartidor-E28;
// NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #5). Se abren desde sus pedidos asignados. Piden al servidor, con
// la sesión, cuántas entregas completó hoy y esta semana, las suyas, contadas en la hora de su plaza y con la semana de
// lunes a domingo (design.md, lectura 5), y las muestran tal cual. Si no llegan, sin escenario propio, los mensajes
// generales de la app; si ya no opera (403), el módulo lo lleva a la espera.
//
// La composición sigue la imagen c05-estadisticas de la Propuesta 04, con la tarjeta de cifras de las estadísticas de la
// lavandería. Del mock no entran el ingreso acumulado (DD-019 y DD-013, repartidor-E29) ni el desglose por día
// (proposal.md, "Fuera del contrato").
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { useRegresar } from '../../src/navegacion/regresar';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { TarjetaDeCifras } from '../../src/piezas/TarjetaDeCifras';
import { consultarEstadisticasDelRepartidor } from '../../src/repartidor/api';
import { RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR } from '../../src/repartidor/rutas';
import { mensajeDelRepartidor, TEXTOS_DEL_REPARTIDOR as TEXTOS } from '../../src/repartidor/textos';
import { useLecturaDelRepartidor } from '../../src/repartidor/useLecturaDelRepartidor';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

export default function EstadisticasDelRepartidor() {
  const regresar = useRegresar(RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR);
  const estado = useLecturaDelRepartidor(consultarEstadisticasDelRepartidor);

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.misEstadisticas, alRegresar: regresar }}>
      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoLasEstadisticas}</Text>
        </View>
      ) : null}
      {estado.fase === 'lista' ? (
        <>
          <TarjetaDeCifras
            cifras={[
              { testID: 'entregas-del-dia', etiqueta: TEXTOS.entregasDeHoy, valor: estado.datos.entregas_del_dia },
              {
                testID: 'entregas-de-la-semana',
                etiqueta: TEXTOS.entregasDeLaSemana,
                valor: estado.datos.entregas_de_la_semana,
              },
            ]}
          />
          <Parrafo alineacion="inicio">{TEXTOS.laSemana}</Parrafo>
        </>
      ) : null}
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
