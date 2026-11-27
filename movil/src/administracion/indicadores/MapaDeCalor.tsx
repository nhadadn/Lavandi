// El mapa de calor de los pedidos del rango (F-I11; administracion-app-E50; imágenes 49 y 69 de la Propuesta 06), con
// los agregados que calcula el servidor (PedidosPorColonia y PedidosPorZona, con el color de App\Indicadores\Intensidad),
// sin la capa de calor del proveedor (ADR-0009, decisión 3, aplicada a la app por ADR-0022, decisión 13):
//
// - por colonia, un círculo por colonia en el centro de sus recolecciones, más grande y más oscuro cuantos más pedidos
//   (indicadores-E19): el radio crece con la intensidad que da el servidor, con las medidas del mapa del panel (de 150 a
//   800 m; mapa-de-calor.blade.php), y el color es el de la respuesta. Encima, su letra, la de su fila en la tabla
//   (imagen 49);
// - por zona de servicio, cada zona pintada con el color de la respuesta, con su geometría en GeoJSON, como al panel
//   (indicadores-E20), y encima su nombre y su número de pedidos (imagen 69);
// - cada figura deja ver el mapa debajo, como el panel (0.8 en los círculos y 0.65 en las zonas), con la orilla en el
//   color de la superficie; el color nunca va solo: la letra, el número y la tabla lo dicen.
//
// Es react-native-maps sin provider (ADR-0009, decisión 1): MapKit en iPhone y Google Maps en Android. No se le pasa
// provider en ningún caso. Quien lo usa revisa antes si la compilación puede montarlo (src/cercanas/mapa.ts). El mapa
// no se vuelve a crear con cada consulta: cambian sus figuras. Se encuadra cuando cambian los lugares de las figuras
// (otro rango, otra agrupación) y no cuando solo cambian sus cifras, así la consulta periódica no mueve lo que se ve; el
// encuadre espera a que el mapa esté listo (onMapReady), como el del monitoreo (src/administracion/monitoreo/). Mientras
// tanto, el mapa ya abre sobre las figuras que trae la respuesta (initialRegion) y no en la región de la plataforma. Sin
// figuras (por colonia, sin pedidos en el rango) no hay lugar que mostrar y abre donde lo deje la plataforma, como el
// panel, que abre en el mundo entero; la tabla dice que no hay pedidos.
//
// El letrero de cada figura es una vista propia dentro del Marker. En Android, mientras el Marker sigue los cambios de
// esa vista (tracksViewChanges, true por omisión), la librería la vuelve a dibujar sin parar; aquí no los sigue y el
// letrero se dibuja al montarse. Lo que dice cambia poco: la letra de una colonia va con su lugar en la lista, que es su
// llave, y el número de pedidos de una zona es parte de la llave de su letrero, así que con otra cifra el letrero se
// monta otra vez y se dibuja con ella.
//
// Colores y medidas de theme.ts (ADR-0013), por la capa semántica; los de cada figura, los del servidor.
import { useEffect, useRef, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import MapView, { Circle, Marker, Polygon, type LatLng, type Region } from 'react-native-maps';

import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import type { ColoniaDelMapa, MapaDeCalor as ElMapaDeCalor, Punto, ZonaDelMapa } from './api';
import { coloniaEnTexto, letraDeLaColonia, pedidosEnTexto, TEXTOS_DE_LOS_INDICADORES as TEXTOS } from './textos';

// El alto del mapa, el de las imágenes 49 y 69: la tabla empieza arriba del pliegue.
const ALTO_DEL_MAPA = 150;

// El radio de un círculo en metros, como el mapa del panel: 150 la de menos pedidos y 800 la de más.
const RADIO_MINIMO_EN_METROS = 150;
const RADIO_POR_INTENSIDAD_EN_METROS = 650;

// Cuánto deja ver del mapa cada figura, como el panel: los círculos, 0.8; las zonas, 0.65.
const OPACIDAD_DEL_CIRCULO = 0.8;
const OPACIDAD_DE_LA_ZONA = 0.65;

// Con un solo lugar, cuánto abarca el mapa alrededor de él, en grados: unas cuadras, como el monitoreo.
const ABARCA_CON_UNO_EN_GRADOS = 0.02;

// El margen entre las figuras de las orillas y el borde del mapa al encuadrarlas.
const MARGEN_DEL_ENCUADRE = { top: espaciado[5], right: espaciado[5], bottom: espaciado[5], left: espaciado[5] };

function enElMapa({ latitud, longitud }: Punto): LatLng {
  return { latitude: latitud, longitude: longitud };
}

/** Un color del servidor (#RRGGBB) con su transparencia (#RRGGBBAA), que React Native entiende. */
function conTransparencia(hexadecimal: string, opacidad: number): string {
  const alfa = Math.round(opacidad * 255)
    .toString(16)
    .padStart(2, '0');
  return `${hexadecimal}${alfa}`;
}

/** El centro de la caja que encierra los contornos de una zona: donde va su nombre. */
function centroDeLaZona({ poligonos }: ZonaDelMapa): Punto {
  const puntos = poligonos.flatMap(({ contorno }) => contorno);
  const latitudes = puntos.map(({ latitud }) => latitud);
  const longitudes = puntos.map(({ longitud }) => longitud);
  return {
    latitud: (Math.min(...latitudes) + Math.max(...latitudes)) / 2,
    longitud: (Math.min(...longitudes) + Math.max(...longitudes)) / 2,
  };
}

/** Los lugares que se encuadran: el centro de cada colonia, o los contornos de cada zona. */
function lugaresDelMapa(mapa: ElMapaDeCalor): LatLng[] {
  if (mapa.agrupacion === 'colonia') {
    return mapa.colonias.map(enElMapa);
  }
  return mapa.zonas.flatMap(({ poligonos }) => poligonos.flatMap(({ contorno }) => contorno.map(enElMapa)));
}

/**
 * La región con la que abre el mapa, la de la caja que encierra los lugares: con uno solo, unas cuadras alrededor de él.
 * Sin lugares, ninguna: no hay de dónde sacarla.
 */
function regionDeLosLugares(lugares: LatLng[]): Region | undefined {
  if (lugares.length === 0) {
    return undefined;
  }
  const latitudes = lugares.map(({ latitude }) => latitude);
  const longitudes = lugares.map(({ longitude }) => longitude);
  const [sur, norte] = [Math.min(...latitudes), Math.max(...latitudes)];
  const [oeste, este] = [Math.min(...longitudes), Math.max(...longitudes)];
  return {
    latitude: (sur + norte) / 2,
    longitude: (oeste + este) / 2,
    latitudeDelta: Math.max(norte - sur, ABARCA_CON_UNO_EN_GRADOS),
    longitudeDelta: Math.max(este - oeste, ABARCA_CON_UNO_EN_GRADOS),
  };
}

export function MapaDeCalor({ mapa }: { mapa: ElMapaDeCalor }) {
  const referencia = useRef<MapView>(null);
  const [listo, setListo] = useState(false);
  const lugares = lugaresDelMapa(mapa);
  // La librería lee la región inicial solo al crear el mapa: la de las figuras con que abre.
  const [regionInicial] = useState(() => regionDeLosLugares(lugares));
  // Los lugares de las figuras, para encuadrar solo cuando cambian.
  const firma = `${mapa.agrupacion}:${lugares.map(({ latitude, longitude }) => `${latitude},${longitude}`).join(';')}`;
  const encuadrada = useRef<string | null>(null);

  useEffect(() => {
    if (!listo || lugares.length === 0 || encuadrada.current === firma) {
      return;
    }
    encuadrada.current = firma;
    if (lugares.length === 1) {
      referencia.current?.animateToRegion({
        ...lugares[0],
        latitudeDelta: ABARCA_CON_UNO_EN_GRADOS,
        longitudeDelta: ABARCA_CON_UNO_EN_GRADOS,
      });
    } else {
      referencia.current?.fitToCoordinates(lugares, { edgePadding: MARGEN_DEL_ENCUADRE, animated: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- la firma resume los lugares: se encuadra solo si cambia
  }, [listo, firma]);

  return (
    <View style={estilos.marco}>
      <MapView
        ref={referencia}
        testID="mapa"
        style={StyleSheet.absoluteFill}
        accessibilityLabel={TEXTOS.elMapa[mapa.agrupacion]}
        initialRegion={regionInicial}
        onMapReady={() => setListo(true)}
      >
        {mapa.agrupacion === 'colonia'
          ? mapa.colonias.map((colonia, indice) => (
              <CirculoDeLaColonia key={`${indice}:${colonia.colonia}`} colonia={colonia} indice={indice} />
            ))
          : mapa.zonas.map((zona, indice) => <ZonaPintada key={`${indice}:${zona.zona}`} zona={zona} indice={indice} />)}
      </MapView>
    </View>
  );
}

function CirculoDeLaColonia({ colonia, indice }: { colonia: ColoniaDelMapa; indice: number }) {
  const centro = enElMapa(colonia);
  const letraDeLaFila = letraDeLaColonia(indice);
  return (
    <>
      <Circle
        testID={`circulo-${indice}`}
        center={centro}
        radius={RADIO_MINIMO_EN_METROS + RADIO_POR_INTENSIDAD_EN_METROS * colonia.intensidad}
        fillColor={conTransparencia(colonia.color, OPACIDAD_DEL_CIRCULO)}
        strokeColor={color.semantico.superficie}
        strokeWidth={2 * grosor.control}
      />
      <Marker
        testID={`colonia-${indice}`}
        coordinate={centro}
        anchor={{ x: 0.5, y: 0.5 }}
        tracksViewChanges={false}
        title={coloniaEnTexto(colonia)}
        description={pedidosEnTexto(colonia.pedidos)}
        accessibilityLabel={`${letraDeLaFila} · ${coloniaEnTexto(colonia)}: ${pedidosEnTexto(colonia.pedidos)}`}
      >
        <View style={estilos.letrero}>
          <Text style={[estilos.textoDelLetrero, letra('cuerpo', 'fuerte')]}>{letraDeLaFila}</Text>
        </View>
      </Marker>
    </>
  );
}

function ZonaPintada({ zona, indice }: { zona: ZonaDelMapa; indice: number }) {
  return (
    <>
      {zona.poligonos.map((poligono, parte) => (
        <Polygon
          key={parte}
          testID={`zona-${indice}-${parte}`}
          coordinates={poligono.contorno.map(enElMapa)}
          holes={poligono.huecos.map((hueco) => hueco.map(enElMapa))}
          fillColor={conTransparencia(zona.color, OPACIDAD_DE_LA_ZONA)}
          strokeColor={color.semantico.superficie}
          strokeWidth={2 * grosor.control}
        />
      ))}
      {zona.poligonos.length > 0 ? (
        <Marker
          // Con otro número de pedidos, el letrero se monta otra vez: sin seguir los cambios, así se dibuja con él.
          key={zona.pedidos}
          testID={`zona-${indice}`}
          coordinate={enElMapa(centroDeLaZona(zona))}
          anchor={{ x: 0.5, y: 0.5 }}
          tracksViewChanges={false}
          title={zona.zona}
          description={pedidosEnTexto(zona.pedidos)}
          accessibilityLabel={`${zona.zona}: ${pedidosEnTexto(zona.pedidos)}`}
        >
          <View style={estilos.letrero}>
            <Text style={[estilos.textoDelLetrero, letra('cuerpo', 'semifuerte')]}>{zona.zona}</Text>
            <Text style={[estilos.cifraDelLetrero, letra('titulares', 'fuerte')]}>{zona.pedidos}</Text>
          </View>
        </Marker>
      ) : null}
    </>
  );
}

const estilos = StyleSheet.create({
  // El mapa ocupa el ancho de su tarjeta, con la orilla redonda de las tarjetas (imágenes 49 y 69).
  marco: {
    height: ALTO_DEL_MAPA,
    borderRadius: radio.grande,
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    overflow: 'hidden',
    backgroundColor: color.semantico.superficie_hundida,
  },
  // La letra de la colonia o el nombre de la zona, sobre la superficie para leerse sobre cualquier color de la rampa.
  letrero: {
    alignItems: 'center',
    minWidth: espaciado[5],
    paddingHorizontal: espaciado[1],
    borderRadius: radio.medio,
    borderWidth: grosor.control,
    borderColor: color.semantico.borde_fuerte,
    backgroundColor: color.semantico.superficie,
  },
  textoDelLetrero: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  cifraDelLetrero: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    fontVariant: ['tabular-nums'],
  },
});
