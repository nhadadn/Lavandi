// El mapa para marcar una dirección (direcciones-E04 y E05): la persona mueve el mapa bajo un pin fijo en el centro, y
// al soltarlo el punto del pin es el centro del mapa. Es react-native-maps sin proveedor (ADR-0009, decisión 1): sin
// provider, la librería usa MapKit en iPhone y Google Maps en Android. No se le pasa provider en ningún caso.
//
// Mover el mapa no consulta nada: sin geocodificación inversa, la calle no se sugiere desde el pin (ADR-0009;
// presupuesto-de-mapas.md, decisiones 3 y 4). Si la compilación de Android no trae la llave de Google Maps, no se monta
// y lo dice, como el mapa de las lavanderías cercanas (src/cercanas/mapa.ts).
//
// El pin se dibuja encima del mapa con los tokens (ADR-0013): no es un marcador del mapa, porque no se arrastra él, sino
// el mapa debajo. Es adorno: el lector de pantalla anuncia el mapa, y quien no lo usa escribe la dirección.
import MapView, { type Region } from 'react-native-maps';
import { StyleSheet, Text, View } from 'react-native';

import type { Punto } from '../cercanas/api';
import { hayMapaNativo } from '../cercanas/mapa';
import { TEXTOS } from '../cercanas/textos';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';

// Cuánto abarca el mapa al abrirse, en grados de latitud y de longitud: unas cuadras alrededor del punto, para dejar el
// pin sobre una puerta.
const ABARCA_EN_GRADOS = 0.005;

// El pin: una cabeza redonda con un punto y una punta debajo. La punta toca el centro del mapa.
const CABEZA_DEL_PIN = espaciado[5];
const PUNTA_DEL_PIN = espaciado[3];
const ALTO_DEL_PIN = CABEZA_DEL_PIN + PUNTA_DEL_PIN;

interface Propiedades {
  /** Dónde se abre el mapa, con el pin. */
  centro: Punto;
  /** Al soltar el mapa, con el punto que quedó bajo el pin. */
  alMover: (punto: Punto) => void;
}

function regionAlrededorDe({ latitud, longitud }: Punto): Region {
  return { latitude: latitud, longitude: longitud, latitudeDelta: ABARCA_EN_GRADOS, longitudeDelta: ABARCA_EN_GRADOS };
}

export function MapaParaMarcar({ centro, alMover }: Propiedades) {
  if (!hayMapaNativo()) {
    return (
      <View style={[estilos.marco, estilos.sinMapa]} testID="mapa-no-disponible">
        <Text style={estilos.aviso}>{TEXTOS.mapaNoDisponible}</Text>
      </View>
    );
  }

  return (
    <View style={estilos.marco}>
      <MapView
        testID="mapa-para-marcar"
        style={StyleSheet.absoluteFill}
        initialRegion={regionAlrededorDe(centro)}
        showsUserLocation
        accessibilityLabel="Mapa para marcar tu dirección"
        onRegionChangeComplete={({ latitude, longitude }: Region) => alMover({ latitud: latitude, longitud: longitude })}
      />
      <View
        pointerEvents="none"
        style={estilos.capaDelPin}
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
      >
        <View testID="pin-de-la-direccion" style={estilos.pin}>
          <View style={estilos.cabeza}>
            <View style={estilos.punto} />
          </View>
          <View style={estilos.punta} />
        </View>
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  // El mapa ocupa un cuadro, con la orilla redonda de las tarjetas.
  marco: {
    aspectRatio: 1,
    borderRadius: radio.grande,
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    overflow: 'hidden',
    backgroundColor: color.semantico.superficie_hundida,
  },
  sinMapa: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: espaciado[4],
  },
  aviso: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    textAlign: 'center',
  },
  capaDelPin: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  // Centrado, el pin sube la mitad de este margen: así su punta queda en el centro del mapa.
  pin: {
    alignItems: 'center',
    marginBottom: ALTO_DEL_PIN,
  },
  cabeza: {
    width: CABEZA_DEL_PIN,
    height: CABEZA_DEL_PIN,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.accion,
  },
  punto: {
    width: espaciado[2],
    height: espaciado[2],
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.superficie,
  },
  punta: {
    width: grosor.foco,
    height: PUNTA_DEL_PIN,
    backgroundColor: color.semantico.accion,
  },
});
