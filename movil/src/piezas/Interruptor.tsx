// Un sí o no con su etiqueta a la izquierda, como "Entregar en la misma dirección" (direcciones-E09 y E10) y "Guardar
// como favorita" (direcciones-E06) en los tableros de la dirección de la Propuesta 5 corregida (C-233). Es el
// interruptor nativo de cada plataforma, con los colores del tema (ADR-0013); se anuncia como interruptor con su
// etiqueta y su estado, así el color no es la única señal (design system, reglas transversales). No es uno de los seis
// componentes del design system. La fila mide al menos el área táctil.
import { StyleSheet, Switch, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { accesibilidad, color, espaciado, tipografia } from '../tema/theme';

interface Propiedades {
  etiqueta: string;
  valor: boolean;
  alCambiar: (valor: boolean) => void;
}

export function Interruptor({ etiqueta, valor, alCambiar }: Propiedades) {
  return (
    <View style={estilos.fila}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      {/* En Android el interruptor nativo no lleva "value": su estado se anuncia también con accessibilityState. */}
      <Switch
        accessible
        accessibilityLabel={etiqueta}
        accessibilityState={{ checked: valor }}
        value={valor}
        onValueChange={alCambiar}
        trackColor={{ false: color.semantico.borde_fuerte, true: color.semantico.accion }}
        thumbColor={color.semantico.superficie}
        ios_backgroundColor={color.semantico.borde_fuerte}
      />
    </View>
  );
}

const estilos = StyleSheet.create({
  fila: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
  },
  etiqueta: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
});
