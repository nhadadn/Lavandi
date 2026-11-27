// El encabezado de las pantallas de registro, inicio de sesión y recuperación, como el .encabezado-app de las imágenes de
// la Propuesta 04 (C-230): el botón "Regresar" y el título de la pantalla, sobre la superficie. Colores y medidas de
// theme.ts (ADR-0013); el botón tiene el área táctil mínima. Sin a dónde regresar (el inicio de sesión abierto como
// primera pantalla, C-237) no hay botón, y su lugar queda vacío para que el título no se mueva.
//
// La flecha es el carácter "‹" con la escala tipográfica: la app no tiene una librería de íconos y agregarla es una
// dependencia nueva. Su trazo depende de la letra del sistema y se revisa en un teléfono real.
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { accesibilidad, color, espaciado, radio, tipografia } from '../tema/theme';

interface Propiedades {
  titulo: string;
  /** Sin él, la pantalla no regresa a ningún lado y no lleva el botón. */
  alRegresar?: () => void;
}

export function Encabezado({ titulo, alRegresar }: Propiedades) {
  return (
    <View style={estilos.encabezado} testID="encabezado">
      {alRegresar ? (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Regresar"
          onPress={alRegresar}
          testID="regresar"
          style={({ pressed }) => [estilos.regreso, pressed && estilos.regresoPresionado]}
        >
          <Text style={estilos.flecha} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
            ‹
          </Text>
        </Pressable>
      ) : (
        <View style={estilos.lado} testID="sin-regreso" />
      )}
      <Text accessibilityRole="header" numberOfLines={1} style={[estilos.titulo, letra('titulares', 'fuerte')]}>
        {titulo}
      </Text>
      {/* El lado derecho, vacío, equilibra el botón de la izquierda: el título no llega a la orilla. */}
      <View style={estilos.lado} />
    </View>
  );
}

const estilos = StyleSheet.create({
  encabezado: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[1],
    paddingHorizontal: espaciado[2],
    paddingVertical: espaciado[1],
    backgroundColor: color.semantico.superficie,
  },
  regreso: {
    minWidth: accesibilidad.area_tactil_minima_dp,
    minHeight: accesibilidad.area_tactil_minima_dp,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radio.pildora,
  },
  regresoPresionado: {
    backgroundColor: color.semantico.superficie_hundida,
  },
  flecha: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.titulo_mayor,
    lineHeight: tipografia.escala.titulo_mayor * tipografia.interlineado.apretado,
  },
  titulo: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
    letterSpacing: tipografia.escala.cuerpo_grande * tipografia.espaciado_letra.titular,
  },
  lado: {
    minWidth: accesibilidad.area_tactil_minima_dp,
  },
});
