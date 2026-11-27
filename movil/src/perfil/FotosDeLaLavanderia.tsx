// Las fotos de la lavandería, arriba de su perfil (perfil-lavanderia-E09), como la franja de la imagen a05-lavanderia de
// la Propuesta 04: se deslizan de lado, una a la vez, y abajo a la derecha el contador dice cuál se ve ("2 / 3"). Van en
// el orden que da el servidor; la primera, la principal, es la que se ve al abrir. Cada una se pinta desde su URL
// firmada (perfil-lavanderia-E03); si vence con la pantalla abierta, la que ya cargó sigue a la vista, y al volver a
// entrar el perfil se pide otra vez con URLs nuevas (design.md del cambio, Risks). La app no las renueva por su cuenta.
//
// - Sin fotos no se dibuja nada: el perfil empieza con el nombre, sin un hueco.
// - Con una sola foto no hay contador.
// - Para el lector de pantalla, cada foto dice cuál es ("Foto 2 de 3 de la lavandería") y el contador se salta.
//
// No es uno de los seis componentes del design system: el cambio lo deja a la tarea (design.md, "Pregunta de diseño"), y
// se arma con los tokens (ADR-0013). Mientras una foto carga, o si no carga, se ve el fondo hundido del marco.
import { useState } from 'react';
import {
  Image,
  ScrollView,
  StyleSheet,
  Text,
  View,
  type LayoutChangeEvent,
  type NativeScrollEvent,
  type NativeSyntheticEvent,
} from 'react-native';

import type { FotoFirmada } from '../configuracion/lectura';
import { letra } from '../tema/letra';
import { color, espaciado, radio, tipografia } from '../tema/theme';
import { contadorDeFotos, fotoParaLeer } from './textos';

// La franja guarda la proporción de la del mock a05-lavanderia.
const PROPORCION_DE_LAS_FOTOS = 16 / 9;

export function FotosDeLaLavanderia({ fotos }: { fotos: FotoFirmada[] }) {
  // Cada foto mide lo ancho de la franja, que se sabe al dibujarla.
  const [ancho, setAncho] = useState(0);
  // La que se ve, desde 0.
  const [vista, setVista] = useState(0);

  if (fotos.length === 0) {
    return null;
  }

  function alMedir(evento: LayoutChangeEvent) {
    setAncho(evento.nativeEvent.layout.width);
  }

  // La que ocupa más de la mitad de la franja es la que se ve.
  function alDeslizar(evento: NativeSyntheticEvent<NativeScrollEvent>) {
    const { contentOffset, layoutMeasurement } = evento.nativeEvent;
    if (layoutMeasurement.width <= 0) {
      return;
    }
    const indice = Math.round(contentOffset.x / layoutMeasurement.width);
    setVista(Math.min(Math.max(indice, 0), fotos.length - 1));
  }

  return (
    <View testID="fotos" style={estilos.marco}>
      <ScrollView
        testID="carrete-de-fotos"
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        scrollEventThrottle={16}
        onLayout={alMedir}
        onScroll={alDeslizar}
        style={estilos.carrete}
      >
        {fotos.map((foto, indice) => (
          <Image
            key={`${indice}-${foto.url}`}
            accessible
            accessibilityRole="image"
            accessibilityLabel={fotoParaLeer(indice + 1, fotos.length)}
            source={{ uri: foto.url }}
            resizeMode="cover"
            style={[estilos.foto, { width: ancho }]}
          />
        ))}
      </ScrollView>
      {fotos.length > 1 ? (
        <View style={estilos.contador} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          <Text style={[estilos.textoDelContador, letra('cuerpo', 'semifuerte')]}>
            {contadorDeFotos(vista + 1, fotos.length)}
          </Text>
        </View>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  marco: {
    aspectRatio: PROPORCION_DE_LAS_FOTOS,
    borderRadius: radio.grande,
    overflow: 'hidden',
    backgroundColor: color.semantico.superficie_hundida,
  },
  carrete: {
    flex: 1,
  },
  foto: {
    aspectRatio: PROPORCION_DE_LAS_FOTOS,
  },
  contador: {
    position: 'absolute',
    right: espaciado[2],
    bottom: espaciado[2],
    paddingHorizontal: espaciado[2],
    paddingVertical: espaciado[1],
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.texto,
  },
  textoDelContador: {
    color: color.semantico.texto_inverso,
    fontSize: tipografia.escala.menor,
    // Números tabulares, para que el contador no baile al cambiar (componentes.md, reglas transversales).
    fontVariant: ['tabular-nums'],
  },
});
