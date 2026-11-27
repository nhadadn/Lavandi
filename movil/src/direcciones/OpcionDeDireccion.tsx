// Una dirección para elegir como recogida o como entrega (direcciones-E06 a E10), como las opciones de radio del
// tablero "Dirección (3 de 4)" de la Propuesta 5 corregida (C-233) y la imagen a08-direccion de la Propuesta 04: el
// nombre de la favorita, o sus señas si no tiene, y debajo las señas, el municipio y las referencias. Se elige tocando
// toda la tarjeta; es de opción única. La elegida lleva el borde de foco y el punto lleno, y su estado se anuncia, así
// el color no es la única señal (design system, reglas transversales). No es uno de los seis componentes del design
// system: se arma con los tokens (ADR-0013), como la opción de servicio. Área táctil mínima de 48. Del mock no entra la
// estrella: las favoritas van bajo su propio título.
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../tema/theme';
import type { Direccion } from './api';
import { complementoEnTexto, senasEnTexto } from './textos';

interface Propiedades {
  direccion: Direccion;
  elegida: boolean;
  alElegir: () => void;
}

export function OpcionDeDireccion({ direccion, elegida, alElegir }: Propiedades) {
  const senas = senasEnTexto(direccion);
  const titulo = direccion.alias ?? senas;
  const lineas = [direccion.alias ? senas : '', complementoEnTexto(direccion)].filter(Boolean);
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ checked: elegida }}
      accessibilityLabel={[titulo, ...lineas].join(', ')}
      onPress={alElegir}
      style={({ pressed }) => [estilos.opcion, elegida && estilos.elegida, pressed && estilos.presionada]}
    >
      <View style={[estilos.circulo, elegida && estilos.circuloElegido]}>
        {elegida ? <View style={estilos.punto} /> : null}
      </View>
      <View style={estilos.textos}>
        <Text style={[estilos.titulo, letra('cuerpo', 'semifuerte')]}>{titulo}</Text>
        {lineas.map((linea) => (
          <Text key={linea} style={[estilos.linea, letra('cuerpo', 'regular')]}>
            {linea}
          </Text>
        ))}
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
    borderWidth: grosor.control,
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
    borderWidth: grosor.control,
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
  titulo: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  linea: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
