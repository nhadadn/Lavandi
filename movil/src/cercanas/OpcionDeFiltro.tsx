// Un corte de un filtro de la búsqueda (cercanas-E21), como los chips del mock A04: se toca para elegirlo y se vuelve a
// tocar para quitarlo. El elegido va con el fondo de acción y se anuncia como seleccionado, así el color no es la única
// señal (design system, reglas transversales). No es uno de los seis componentes del design system: se arma con los
// tokens (ADR-0013). Área táctil mínima de 48.
import { Pressable, StyleSheet, Text } from 'react-native';

import { accesibilidad, color, espaciado, radio, tipografia } from '../tema/theme';

interface Propiedades {
  texto: string;
  elegida: boolean;
  alTocar: () => void;
}

export function OpcionDeFiltro({ texto, elegida, alTocar }: Propiedades) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: elegida }}
      onPress={alTocar}
      style={({ pressed }) => [
        estilos.opcion,
        elegida ? estilos.elegida : null,
        pressed ? (elegida ? estilos.elegidaPresionada : estilos.presionada) : null,
      ]}
    >
      <Text style={[estilos.texto, elegida ? estilos.textoElegido : null]}>{texto}</Text>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  opcion: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    justifyContent: 'center',
    paddingHorizontal: espaciado[4],
    borderWidth: 1,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.superficie,
  },
  presionada: {
    backgroundColor: color.semantico.fondo,
  },
  elegida: {
    borderColor: color.semantico.accion,
    backgroundColor: color.semantico.accion,
  },
  elegidaPresionada: {
    borderColor: color.semantico.accion_hover,
    backgroundColor: color.semantico.accion_hover,
  },
  texto: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    fontWeight: tipografia.peso.semifuerte,
  },
  textoElegido: {
    color: color.semantico.accion_texto,
  },
});
