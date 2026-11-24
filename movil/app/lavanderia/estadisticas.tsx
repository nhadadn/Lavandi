// Las estadísticas de la lavandería, del Módulo B (openspec/changes/historial-de-la-lavanderia, historial-lavanderia-E05;
// NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #5). Se abren desde la pantalla de pedidos. Piden al servidor,
// con la sesión, cuántos pedidos completó la lavandería hoy y esta semana, contados en la hora de la plaza y con la
// semana de lunes a domingo (design.md, decisiones 2 y 3), y los muestran tal cual. Si no llegan, sin escenario propio,
// los mensajes generales de la app.
//
// La composición sigue la imagen b05-estadisticas de la Propuesta 04: una tarjeta con una fila por cifra, con los números
// tabulares (design system, reglas transversales), la de src/piezas/TarjetaDeCifras.tsx, que comparte con las
// estadísticas del repartidor. Del mock no entran el ingreso total (DD-013, DD-020 y DD-012), la
// calificación promedio (DD-052) ni la actividad por día de la semana (proposal.md, "Fuera del contrato").
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { mensajeDeLaFalla } from '../../src/cercanas/textos';
import { consultarEstadisticasDeLaLavanderia } from '../../src/historial-lavanderia/api';
import { TEXTOS_DEL_HISTORIAL_DE_LA_LAVANDERIA as TEXTOS } from '../../src/historial-lavanderia/textos';
import { useLecturaDeLaLavanderia } from '../../src/historial-lavanderia/useLecturaDeLaLavanderia';
import { useRegresar } from '../../src/navegacion/regresar';
import { RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA } from '../../src/pedidos-lavanderia/rutas';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { TarjetaDeCifras } from '../../src/piezas/TarjetaDeCifras';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

export default function EstadisticasDeLaLavanderia() {
  const regresar = useRegresar(RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA);
  const estado = useLecturaDeLaLavanderia(consultarEstadisticasDeLaLavanderia);

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.estadisticas, alRegresar: regresar }}>
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
              { testID: 'pedidos-del-dia', etiqueta: TEXTOS.pedidosDelDia, valor: estado.datos.pedidos_del_dia },
              {
                testID: 'pedidos-de-la-semana',
                etiqueta: TEXTOS.pedidosDeLaSemana,
                valor: estado.datos.pedidos_de_la_semana,
              },
            ]}
          />
          <Parrafo alineacion="inicio">{TEXTOS.laSemana}</Parrafo>
        </>
      ) : null}
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
