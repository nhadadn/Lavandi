// El separador entre Google y el correo ("o con tu correo", "o"), como el de las imágenes de la Propuesta 04 (C-230):
// el texto entre dos líneas del color de borde, que es decorativo y no tiene umbral de contraste (tokens.json). Como en
// el mock, el lector de pantalla no lo anuncia: los botones y los campos ya dicen qué opciones hay.
import { StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { color, espaciado, tipografia } from '../tema/theme';

export function Separador({ texto }: { texto: string }) {
  return (
    <View
      style={estilos.fila}
      testID="separador"
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
    >
      <View style={estilos.linea} testID="separador-linea" />
      <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{texto}</Text>
      <View style={estilos.linea} testID="separador-linea" />
    </View>
  );
}

const estilos = StyleSheet.create({
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
  },
  linea: {
    flex: 1,
    height: StyleSheet.hairlineWidth,
    backgroundColor: color.semantico.borde,
  },
  texto: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
