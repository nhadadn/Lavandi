// Dos o más formas de hacer lo mismo, de las que se elige una, como "Escribirla" y "En el mapa" de la dirección nueva
// (direcciones-E01 y E04) en el tablero "Dirección nueva" de la Propuesta 5 corregida (C-233). Cada pestaña se anuncia
// como pestaña y la elegida como seleccionada, así el color no es la única señal (design system, reglas transversales).
// No es uno de los seis componentes del design system: se arma con los tokens (ADR-0013). Área táctil mínima de 48.
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../tema/theme';

interface Propiedades<Clave extends string> {
  pestanas: readonly { clave: Clave; texto: string }[];
  elegida: Clave;
  alElegir: (clave: Clave) => void;
}

export function Pestanas<Clave extends string>({ pestanas, elegida, alElegir }: Propiedades<Clave>) {
  return (
    <View accessibilityRole="tablist" style={estilos.fila}>
      {pestanas.map(({ clave, texto }) => {
        const esLaElegida = clave === elegida;
        return (
          <Pressable
            key={clave}
            accessibilityRole="tab"
            accessibilityState={{ selected: esLaElegida }}
            onPress={() => alElegir(clave)}
            style={({ pressed }) => [
              estilos.pestana,
              esLaElegida ? estilos.elegida : null,
              pressed && !esLaElegida ? estilos.presionada : null,
            ]}
          >
            <Text
              style={[
                estilos.texto,
                letra('cuerpo', esLaElegida ? 'fuerte' : 'regular'),
                esLaElegida ? estilos.textoElegido : null,
              ]}
            >
              {texto}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const estilos = StyleSheet.create({
  fila: {
    flexDirection: 'row',
    borderWidth: grosor.control,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.medio,
    overflow: 'hidden',
    backgroundColor: color.semantico.superficie,
  },
  pestana: {
    flex: 1,
    minHeight: accesibilidad.area_tactil_minima_dp,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: espaciado[3],
  },
  elegida: {
    backgroundColor: color.semantico.accion,
  },
  presionada: {
    backgroundColor: color.semantico.fondo,
  },
  texto: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  textoElegido: {
    color: color.semantico.accion_texto,
  },
});
