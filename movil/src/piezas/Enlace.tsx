// Enlace de texto: lleva a otra pantalla o abre un documento (registro-E09 y registro-E28). Va subrayado para que el
// color no sea la única señal (design system, reglas transversales). Colores y medidas de theme.ts (ADR-0013).
// Con las variantes de las imágenes de la Propuesta 04 (C-230): a la derecha ("¿Olvidaste tu contraseña?"), al centro,
// y con una pregunta antes en el color del texto ("¿Ya tienes cuenta? Inicia sesión"), que se toca y se anuncia como
// un solo enlace.
import { Pressable, StyleSheet, Text, type ViewStyle } from 'react-native';

import { letra } from '../tema/letra';
import { tema } from '../tema/theme';

interface Propiedades {
  texto: string;
  alTocar: () => void;
  /** La pregunta antes del enlace, en el color del texto y en el tamaño menor. */
  pregunta?: string;
  /** inicio: a la izquierda; centro; fin: a la derecha. */
  alinear?: 'inicio' | 'centro' | 'fin';
  /** El tamaño menor, el de los enlaces legales del registro. */
  menor?: boolean;
  /**
   * Lo que lee el lector de pantalla en lugar del texto, cuando el texto solo no dice a qué lleva: "Calificar el pedido
   * 3HT8W2QZ" en una lista de pedidos que dicen "Calificar" (historial-E06).
   */
  paraLeer?: string;
}

const ALINEACION: Record<NonNullable<Propiedades['alinear']>, ViewStyle['alignSelf']> = {
  inicio: undefined,
  centro: 'center',
  fin: 'flex-end',
};

export function Enlace({ texto, alTocar, pregunta, alinear = 'inicio', menor = false, paraLeer }: Propiedades) {
  const estiloDelEnlace = [estilos.texto, letra('cuerpo', 'semifuerte'), pregunta || menor ? estilos.menor : null];
  return (
    <Pressable
      role="link"
      accessibilityLabel={paraLeer ?? (pregunta ? `${pregunta} ${texto}` : undefined)}
      onPress={alTocar}
      style={[estilos.enlace, { alignSelf: ALINEACION[alinear] }]}
    >
      {pregunta ? (
        <Text style={[estilos.pregunta, letra('cuerpo', 'regular'), estilos.menor]}>
          {pregunta}
          {' '}
          <Text style={estiloDelEnlace}>{texto}</Text>
        </Text>
      ) : (
        <Text style={estiloDelEnlace}>{texto}</Text>
      )}
    </Pressable>
  );
}

const { color, tipografia, accesibilidad } = tema;

const estilos = StyleSheet.create({
  enlace: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    justifyContent: 'center',
  },
  texto: {
    color: color.semantico.accion,
    fontSize: tipografia.escala.cuerpo,
    textDecorationLine: 'underline',
  },
  pregunta: {
    color: color.semantico.texto,
  },
  menor: {
    fontSize: tipografia.escala.menor,
  },
});
