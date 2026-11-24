// Los puntos que dicen en cuál de las informativas va la persona, como los .puntos-carrusel de las imágenes de la
// Propuesta 04 (C-230): el de la pantalla actual es más ancho y va en el color de acción; los demás, en el de borde de
// control. Son adorno, como en el mock: el lector de pantalla no los anuncia y no se tocan; se avanza con el botón.
import { StyleSheet, View } from 'react-native';

import { color, espaciado, radio } from '../tema/theme';

interface Propiedades {
  total: number;
  /** La posición de la pantalla actual, desde 1. */
  actual: number;
}

export function PuntosDeAvance({ total, actual }: Propiedades) {
  return (
    <View
      style={estilos.fila}
      testID="puntos-de-avance"
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
    >
      {Array.from({ length: total }, (_, indice) => indice + 1).map((posicion) => (
        <View
          key={posicion}
          testID={`punto-${posicion}`}
          style={[estilos.punto, posicion === actual ? estilos.actual : null]}
        />
      ))}
    </View>
  );
}

const estilos = StyleSheet.create({
  fila: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: espaciado[2],
  },
  punto: {
    width: espaciado[2],
    height: espaciado[2],
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.borde_fuerte,
  },
  actual: {
    width: espaciado[5],
    backgroundColor: color.semantico.accion,
  },
});
