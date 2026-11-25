// El mensaje general de error de un formulario, arriba del botón que lo provocó. Los errores de cada campo van en
// CampoDeTexto. Colores y tamaños de theme.ts (ADR-0013).
import { StyleSheet, Text } from 'react-native';

import { letra } from '../tema/letra';
import { color, tipografia } from '../tema/theme';

/** Sin texto, no ocupa lugar. */
export function MensajeDeError({ texto }: { texto?: string }) {
  if (!texto) {
    return null;
  }
  return (
    <Text role="alert" accessibilityLiveRegion="polite" style={[estilos.error, letra('cuerpo', 'regular')]}>
      {texto}
    </Text>
  );
}

const estilos = StyleSheet.create({
  error: {
    color: color.estado.error,
    fontSize: tipografia.escala.menor,
  },
});
