// El mapa nativo con las lavanderías cercanas (cercanas-E01, E02, E04 y E12). Es react-native-maps sin proveedor
// (ADR-0009): sin provider, la librería usa MapKit en iPhone y Google Maps en Android. No se le pasa provider en ningún
// caso, ni se fija Google en iPhone. Lleva un pin por lavandería y se abre centrado en el punto de la consulta.
// Si la compilación de Android no trae la llave de Google Maps, no se monta y lo dice (src/cercanas/mapa.ts).
import MapView, { Marker, type Region } from 'react-native-maps';
import { StyleSheet, Text, View } from 'react-native';

import { color, espaciado, tipografia } from '../tema/theme';
import type { LavanderiaCercana, Punto } from './api';
import { hayMapaNativo } from './mapa';
import { TEXTOS } from './textos';

// Cuánto abarca el mapa al abrirse, en grados de latitud y de longitud: unos 5 km alrededor del punto. Lo que se
// muestra no cambia la consulta, que hace el servidor.
const ABARCA_EN_GRADOS = 0.05;

interface Propiedades {
  centro: Punto;
  lavanderias: LavanderiaCercana[];
  /** Al tocar el pin de una lavandería, con su id_publico. */
  alElegir: (idPublico: string) => void;
}

function regionAlrededorDe({ latitud, longitud }: Punto): Region {
  return { latitude: latitud, longitude: longitud, latitudeDelta: ABARCA_EN_GRADOS, longitudeDelta: ABARCA_EN_GRADOS };
}

export function MapaDeLavanderias({ centro, lavanderias, alElegir }: Propiedades) {
  if (!hayMapaNativo()) {
    return (
      <View style={[estilos.mapa, estilos.sinMapa]} testID="mapa-no-disponible">
        <Text style={estilos.aviso}>{TEXTOS.mapaNoDisponible}</Text>
      </View>
    );
  }

  return (
    <MapView
      testID="mapa"
      style={estilos.mapa}
      initialRegion={regionAlrededorDe(centro)}
      showsUserLocation
      accessibilityLabel="Mapa de lavanderías cercanas"
    >
      {lavanderias.map((lavanderia) => (
        <Marker
          key={lavanderia.id_publico}
          testID={`pin-${lavanderia.id_publico}`}
          coordinate={{ latitude: lavanderia.latitud, longitude: lavanderia.longitud }}
          pinColor={color.semantico.accion}
          accessibilityLabel={lavanderia.nombre}
          onPress={() => alElegir(lavanderia.id_publico)}
        />
      ))}
    </MapView>
  );
}

const estilos = StyleSheet.create({
  mapa: {
    flex: 1,
  },
  sinMapa: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: espaciado[4],
    backgroundColor: color.semantico.superficie_hundida,
  },
  aviso: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    textAlign: 'center',
  },
});
