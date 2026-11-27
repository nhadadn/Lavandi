// El estado de una lavandería en la lista y en su ficha: la insignia cuadrada de los estados operativos
// (src/piezas/InsigniaDeEstado.tsx), con su nombre escrito, como el panel (LavanderiasTable::ESTADOS), y su plaza a un
// lado (administracion-app-E13; imágenes 06, 07, 09, 10 y 11 de la Propuesta 06). El color sigue al del panel: activa,
// de éxito; pendiente, de atención; suspendida, de error; dada de baja, gris. El color nunca va solo: el nombre va
// escrito (design system, reglas transversales).
import { StyleSheet, Text, View } from 'react-native';

import { InsigniaDeEstado } from '../../piezas/InsigniaDeEstado';
import { letra } from '../../tema/letra';
import { color, espaciado, tipografia } from '../../tema/theme';
import type { EstadoDeLaLavanderia, PlazaDeLaLavanderia } from './api';
import { NOMBRES_DE_LOS_ESTADOS } from './textos';

const TONO_DEL_ESTADO: Record<EstadoDeLaLavanderia, string> = {
  activa: color.estado.exito,
  pendiente: color.estado.atencion,
  suspendida: color.estado.error,
  baja: color.semantico.texto_suave,
};

export function EstadoYPlaza({ estado, plaza }: { estado: EstadoDeLaLavanderia; plaza: PlazaDeLaLavanderia }) {
  return (
    <View style={estilos.fila}>
      <InsigniaDeEstado
        codigo={estado}
        nombre={NOMBRES_DE_LOS_ESTADOS[estado]}
        forma="cuadrada"
        tono={TONO_DEL_ESTADO[estado]}
        testID="estado-de-la-lavanderia"
      />
      <Text style={[estilos.plaza, letra('cuerpo', 'regular')]}>{plaza.nombre}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: espaciado[3],
  },
  plaza: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
