// Una lavandería de la lista de la administración (administracion-app-E13; imagen 06 de la Propuesta 06): su nombre, y
// debajo su estado y su plaza; se toca toda la fila, que abre su ficha. La flecha es el carácter "›", como la del
// encabezado (src/piezas/Encabezado.tsx): la app no tiene una librería de íconos. Colores y medidas de theme.ts
// (ADR-0013); la fila tiene el área táctil mínima.
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { letra } from '../../tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import type { LavanderiaEnLaLista } from './api';
import { EstadoYPlaza } from './EstadoYPlaza';
import { NOMBRES_DE_LOS_ESTADOS } from './textos';

export function FilaDeLavanderia({ lavanderia, alTocar }: { lavanderia: LavanderiaEnLaLista; alTocar: () => void }) {
  const { id_publico: idPublico, nombre, estado, plaza } = lavanderia;
  return (
    <Pressable
      accessibilityRole="button"
      // El lector de pantalla dice la fila completa: "Lavandería de prueba, Activa, Plaza de prueba".
      accessibilityLabel={[nombre, NOMBRES_DE_LOS_ESTADOS[estado], plaza.nombre].join(', ')}
      onPress={alTocar}
      testID={`lavanderia-${idPublico}`}
      style={({ pressed }) => [estilos.fila, pressed && estilos.presionada]}
    >
      <View style={estilos.datos}>
        <Text style={[estilos.nombre, letra('cuerpo', 'semifuerte')]}>{nombre}</Text>
        <EstadoYPlaza estado={estado} plaza={plaza} />
      </View>
      <Text style={estilos.flecha} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
        ›
      </Text>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
    minHeight: accesibilidad.area_tactil_minima_dp,
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  presionada: {
    backgroundColor: color.semantico.superficie_hundida,
  },
  datos: {
    flex: 1,
    gap: espaciado[2],
  },
  nombre: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  flecha: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.titulo,
  },
});
