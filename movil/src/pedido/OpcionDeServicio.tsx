// Una opción de la selección de servicio (perfil-lavanderia-E11): el tipo con su precio en su unidad, que se elige
// tocando toda la tarjeta. Es de opción única, como un botón de radio, porque un pedido lleva un solo tipo mientras
// DD-098 siga abierta; el mock A06 dibuja casillas porque deja elegir varios. No es uno de los seis componentes del
// design system: se arma con los tokens (ADR-0013). Área táctil mínima de 48; la elegida lleva el borde de foco y el
// punto lleno, y su estado se anuncia, así el color no es la única señal (design system, reglas transversales). Del mock
// no entra la descripción de cada servicio: el servidor entrega el nombre y el precio.
import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { ServicioDeLavanderia } from '../perfil/api';
import { precioEnTexto } from '../perfil/textos';
import { accesibilidad, color, espaciado, radio, tipografia } from '../tema/theme';

interface Propiedades {
  servicio: ServicioDeLavanderia;
  elegida: boolean;
  alElegir: () => void;
}

export function OpcionDeServicio({ servicio, elegida, alElegir }: Propiedades) {
  const precio = precioEnTexto(servicio);
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ checked: elegida }}
      accessibilityLabel={`${servicio.nombre}, ${precio}`}
      onPress={alElegir}
      testID={`opcion-${servicio.codigo}`}
      style={({ pressed }) => [estilos.opcion, elegida && estilos.elegida, pressed && estilos.presionada]}
    >
      <View style={[estilos.circulo, elegida && estilos.circuloElegido]}>
        {elegida ? <View style={estilos.punto} /> : null}
      </View>
      <View style={estilos.textos}>
        <Text style={estilos.nombre}>{servicio.nombre}</Text>
        <Text style={estilos.precio}>{precio}</Text>
      </View>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  opcion: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[3],
    borderWidth: 1,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  elegida: {
    borderColor: color.semantico.foco,
  },
  presionada: {
    backgroundColor: color.semantico.fondo,
  },
  circulo: {
    width: espaciado[5],
    height: espaciado[5],
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.superficie,
  },
  circuloElegido: {
    borderColor: color.semantico.accion,
  },
  punto: {
    width: espaciado[3],
    height: espaciado[3],
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.accion,
  },
  textos: {
    flex: 1,
    gap: espaciado[1],
  },
  nombre: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    fontWeight: tipografia.peso.semifuerte,
  },
  precio: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    fontVariant: ['tabular-nums'],
  },
});
