// El mapa del monitoreo de pedidos (administracion-app-E28 a E30; imagen 25 de la Propuesta 06): los marcadores que da
// el servidor, uno en la recolección de cada pedido y otro en la entrega cuando es en otra dirección, como el mapa del
// panel (App\Pedidos\MarcadoresDelMonitoreo). Ninguno es de un repartidor: su posición está fuera del contrato (DD-077).
//
// - Es react-native-maps sin provider (ADR-0009, decisión 1; ADR-0022, decisión 13): sin provider, la librería usa
//   MapKit en iPhone y Google Maps en Android. No se le pasa provider en ningún caso. Quien lo usa revisa antes si la
//   compilación puede montarlo (src/cercanas/mapa.ts).
// - Cada marcador lleva su letra, R en la recolección y E en la entrega, y al tocarlo dice, arriba del mapa, el folio, el
//   estado, si es la recolección o la entrega, y la plaza, como el panel. El marcador tocado sigue dicho con sus datos
//   nuevos mientras siga en el mapa; si sale, deja de decirse. Tocar el mapa fuera de los marcadores lo deja de decir,
//   como la ventana del marcador del panel, que se cierra. Lo que dice no recibe los toques: los marcadores que quedan
//   debajo se siguen tocando y esa parte del mapa se sigue arrastrando.
// - El mapa se crea una vez: cada consulta cambia sus marcadores, cada uno con su pedido y su tipo como llave, y no el
//   mapa (presupuesto-de-mapas.md). Se encuadra solo la primera vez que hay marcadores; después, la consulta no mueve lo
//   que la administración ve. Con uno solo, se centra en él con unas cuadras alrededor, porque encuadrar un solo punto
//   lleva el mapa a su acercamiento máximo; con varios, los encuadra a todos, como el panel.
// - El encuadre espera a que el mapa esté listo (onMapReady): en Android, la librería descarta la orden si el mapa de
//   Google todavía no existe (node_modules/react-native-maps/android/src/main/java/com/rnmaps/maps/MapView.java,
//   fitToCoordinates, "if (map == null) return;").
//
// Colores y medidas de theme.ts (ADR-0013), por la capa semántica (vault/design-system/design-system.md, decisión 3).
import { useEffect, useRef, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import MapView, { Marker, type LatLng } from 'react-native-maps';

import { pedidoConFolio } from '../../estados/textos';
import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import type { MarcadorDelMonitoreo } from './api';
import { estadoYEtiqueta, marcadorParaLeer, TEXTOS_DEL_MONITOREO as TEXTOS } from './textos';

// El alto del mapa, el de la imagen 25: deja ver la lista debajo.
const ALTO_DEL_MAPA = 228;

// Con un solo marcador, cuánto abarca el mapa alrededor de él, en grados: unas cuadras, como el acercamiento del panel.
const ABARCA_CON_UNO_EN_GRADOS = 0.01;

// El margen entre los marcadores de las orillas y el borde del mapa al encuadrarlos.
const MARGEN_DEL_ENCUADRE = { top: espaciado[6], right: espaciado[6], bottom: espaciado[6], left: espaciado[6] };

// La medida del marcador, un círculo con su letra.
const MEDIDA_DEL_MARCADOR = espaciado[6];

/** Un marcador por pedido y tipo: la recolección y la entrega de un mismo pedido son dos. */
function llaveDelMarcador({ pedido, tipo }: Pick<MarcadorDelMonitoreo, 'pedido' | 'tipo'>): string {
  return `${pedido}:${tipo}`;
}

function puntoDe({ latitud, longitud }: MarcadorDelMonitoreo): LatLng {
  return { latitude: latitud, longitude: longitud };
}

export function MapaDelMonitoreo({ marcadores }: { marcadores: MarcadorDelMonitoreo[] }) {
  const mapa = useRef<MapView>(null);
  const [listo, setListo] = useState(false);
  const encuadrado = useRef(false);
  const [elegido, setElegido] = useState<string | null>(null);

  useEffect(() => {
    if (!listo || encuadrado.current || marcadores.length === 0) {
      return;
    }
    encuadrado.current = true;
    const puntos = marcadores.map(puntoDe);
    if (puntos.length === 1) {
      mapa.current?.animateToRegion({
        ...puntos[0],
        latitudeDelta: ABARCA_CON_UNO_EN_GRADOS,
        longitudeDelta: ABARCA_CON_UNO_EN_GRADOS,
      });
    } else {
      mapa.current?.fitToCoordinates(puntos, { edgePadding: MARGEN_DEL_ENCUADRE, animated: true });
    }
  }, [listo, marcadores]);

  const delElegido = marcadores.find((marcador) => llaveDelMarcador(marcador) === elegido);

  return (
    <View style={estilos.marco}>
      <MapView
        ref={mapa}
        testID="mapa"
        style={StyleSheet.absoluteFill}
        accessibilityLabel={TEXTOS.elMapa}
        onMapReady={() => setListo(true)}
        // El toque de un marcador es de los que suben al mapa, con la acción "marker-press"
        // (node_modules/react-native-maps/src/specs/NativeComponentMarker.ts, MarkerPressEventHandler): cada marcador
        // lo detiene (stopPropagation, src/MapMarker.tsx) y, si aun así llega, no cierra lo que dice el marcador que se
        // acaba de tocar.
        onPress={({ nativeEvent }) => {
          if (nativeEvent.action !== 'marker-press') {
            setElegido(null);
          }
        }}
      >
        {marcadores.map((marcador) => (
          <Marker
            key={llaveDelMarcador(marcador)}
            testID={`marcador-${marcador.pedido}-${marcador.tipo}`}
            coordinate={puntoDe(marcador)}
            accessibilityLabel={marcadorParaLeer(marcador)}
            stopPropagation
            onPress={() => setElegido(llaveDelMarcador(marcador))}
          >
            <View style={[estilos.marcador, marcador.tipo === 'entrega' ? estilos.deEntrega : estilos.deRecoleccion]}>
              <Text
                style={[
                  estilos.letra,
                  letra('cuerpo', 'fuerte'),
                  marcador.tipo === 'entrega' ? estilos.letraDeEntrega : estilos.letraDeRecoleccion,
                ]}
              >
                {TEXTOS.letras[marcador.tipo]}
              </Text>
            </View>
          </Marker>
        ))}
      </MapView>
      {delElegido ? <LoQueDiceElMarcador marcador={delElegido} /> : null}
    </View>
  );
}

// Lo que dice el marcador tocado, arriba del mapa (imagen 25): "Pedido 7K2M9QXA", "En proceso · Recolección" y "Plaza de
// prueba Durango". El lector de pantalla lo anuncia al aparecer y al cambiar. Va encima del mapa sin recibir los toques
// (pointerEvents="none"): si los recibiera, en el teléfono taparía los marcadores que quedan debajo y esa franja del mapa
// no se podría arrastrar.
function LoQueDiceElMarcador({ marcador }: { marcador: MarcadorDelMonitoreo }) {
  return (
    <View style={estilos.dicho} testID="marcador-elegido" pointerEvents="none" accessibilityLiveRegion="polite">
      <Text style={[estilos.folio, letra('titulares', 'fuerte')]}>{pedidoConFolio(marcador.folio)}</Text>
      <Text style={[estilos.renglon, letra('cuerpo', 'regular')]}>{estadoYEtiqueta(marcador)}</Text>
      <Text style={[estilos.renglon, estilos.suave, letra('cuerpo', 'regular')]}>{marcador.plaza}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  // El mapa ocupa todo el ancho, con la orilla redonda de las tarjetas, como en la imagen 25.
  marco: {
    height: ALTO_DEL_MAPA,
    borderRadius: radio.grande,
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    overflow: 'hidden',
    backgroundColor: color.semantico.superficie_hundida,
  },
  marcador: {
    width: MEDIDA_DEL_MARCADOR,
    height: MEDIDA_DEL_MARCADOR,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radio.pildora,
    borderWidth: 2 * grosor.control,
  },
  // La recolección, llena con el color de acción; la entrega, sobre la superficie con el borde de acción, para que las
  // dos se distingan además por su letra. La imagen 25 llena la E con el limón de la marca (--marca-acento de
  // mocks/comun/tokens.css), que no tiene token en la capa semántica: un componente no usa color.marca directo
  // (vault/design-system/design-system.md, decisión 3).
  deRecoleccion: {
    backgroundColor: color.semantico.accion,
    borderColor: color.semantico.superficie,
  },
  deEntrega: {
    backgroundColor: color.semantico.superficie,
    borderColor: color.semantico.accion,
  },
  letra: {
    fontSize: tipografia.escala.menor,
  },
  letraDeRecoleccion: {
    color: color.semantico.accion_texto,
  },
  letraDeEntrega: {
    color: color.semantico.accion,
  },
  dicho: {
    position: 'absolute',
    top: espaciado[3],
    left: espaciado[3],
    right: espaciado[3],
    gap: espaciado[1],
    padding: espaciado[3],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.medio,
    backgroundColor: color.semantico.superficie_elevada,
  },
  folio: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  renglon: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
    lineHeight: tipografia.escala.menor * tipografia.interlineado.normal,
  },
  suave: {
    color: color.semantico.texto_suave,
  },
});
