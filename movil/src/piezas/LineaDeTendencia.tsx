// La línea de tendencia (ADR-0022, alternativa 5): los usuarios activos por periodo (administracion-app-E47;
// indicadores-E06), con la forma de la maqueta de la Propuesta 06 de la administración (44).
//
// Sin librería de gráficas ni SVG, la línea se dibuja con vistas (ADR-0022, "Cómo se dibuja con vistas lo que no es
// rectángulo"): un punto por periodo, al centro de su columna y a la altura de su cifra frente a la mayor, y, entre cada
// par, un segmento: una vista delgada del largo de la distancia entre los dos puntos, puesta en su punto medio y girada
// con transform: rotate al ángulo entre ellos. React Native gira una vista sobre su centro, así cada segmento une sus dos
// puntos.
//
// - Cada punto lleva su cifra encima y su periodo debajo de la línea base, en texto: se lee sin el dibujo (design
//   system, reglas transversales). La cifra va en la letra de la maqueta (menor y semifuerte) y se apoya en su punto:
//   crece hacia arriba, así no se le encima aunque la letra del teléfono crezca, y el dibujo crece con la cifra que
//   mide el teléfono para que la del punto más alto quepa.
// - El ancho de las columnas sale del ancho que mide la línea al dibujarse, con un mínimo: con muchos periodos, como un
//   mes por día, la línea se desplaza de lado en lugar de encimar sus textos.
// - Se anuncia al lector de pantalla como una imagen con cada periodo y su cifra.
//
// Si con vistas no se lee bien después de 2 sesiones de trabajo, o Arthur la rechaza en el emulador, se pasa a
// react-native-svg con una adenda de ADR-0022 (criterio de reversión): cambia solo esta pieza y la de la dona.
//
// No trae cifras propias: todas llegan por propiedades. No es uno de los seis componentes del design system: se arma con
// las vistas de React Native y los tokens, sin librería (ADR-0013).
import { Fragment, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';

/** Un punto: su periodo, la cifra que da su altura y cómo se escribe. */
export interface PuntoDeLaLinea {
  clave: string;
  /** El periodo corto, bajo la línea base: "26/10". */
  etiqueta: string;
  /** La cifra que da la altura del punto. */
  valor: number;
  /** La cifra como se escribe encima, si no es el número tal cual. */
  texto?: string;
  /** El periodo completo, para el lector de pantalla: "Semana del 26/10/2026". */
  etiquetaCompleta?: string;
}

interface Propiedades {
  /** Lo que cuenta la línea: abre su anuncio. */
  titulo: string;
  puntos: readonly PuntoDeLaLinea[];
  testID?: string;
}

// Las medidas del dibujo, de la maqueta 44: arriba, al menos el lugar de la cifra del punto más alto; en medio, la altura
// en que se reparten las cifras; abajo, lo que separa el punto de cero de la línea base.
const ARRIBA_MINIMO = espaciado[5];
const ALTO_UTIL = espaciado[8];
const ABAJO = espaciado[3];
const ANCHO_MINIMO_DE_COLUMNA = espaciado[8];
const LADO_DEL_PUNTO = espaciado[3];
const GROSOR_DE_LA_LINEA = grosor.foco;

/** Dónde queda un punto dentro del dibujo. */
interface Lugar {
  x: number;
  y: number;
}

export function LineaDeTendencia({ titulo, puntos, testID = 'linea-de-tendencia' }: Propiedades) {
  const [anchoVisible, setAnchoVisible] = useState(0);
  // El alto de la cifra más alta, como la mide el teléfono: crece con la letra del sistema.
  const [altoDeLaCifra, setAltoDeLaCifra] = useState(0);
  const columna = Math.max(ANCHO_MINIMO_DE_COLUMNA, puntos.length > 0 ? anchoVisible / puntos.length : 0);
  // Arriba del punto más alto cabe su cifra, apoyada en él.
  const arriba = Math.max(ARRIBA_MINIMO, altoDeLaCifra + LADO_DEL_PUNTO / 2);
  const altoDelDibujo = arriba + ALTO_UTIL + ABAJO;
  const mayor = Math.max(0, ...puntos.map(({ valor }) => valor));
  const lugares: Lugar[] = puntos.map(({ valor }, indice) => ({
    x: columna * (indice + 0.5),
    y: arriba + ALTO_UTIL * (1 - (mayor > 0 ? Math.max(0, valor) / mayor : 0)),
  }));
  const anuncio = [titulo, ...puntos.map((punto) => `${punto.etiquetaCompleta ?? punto.etiqueta}: ${textoDe(punto)}`)];

  function anotarAltoDeLaCifra(alto: number) {
    setAltoDeLaCifra((anterior) => Math.max(anterior, alto));
  }

  return (
    <View accessible accessibilityRole="image" accessibilityLabel={anuncio.join('. ')} testID={testID}>
      <ScrollView
        horizontal
        testID="linea-desplazable"
        showsHorizontalScrollIndicator={false}
        onLayout={(evento) => setAnchoVisible(evento.nativeEvent.layout.width)}
      >
        <View>
          <View
            style={[estilos.dibujo, { width: columna * puntos.length, height: altoDelDibujo }]}
            testID="linea-dibujo"
          >
            {lugares.slice(1).map((hasta, indice) => (
              <View
                key={puntos[indice + 1].clave}
                testID={`segmento-${indice}`}
                style={[estilos.segmento, segmentoEntre(lugares[indice], hasta)]}
              />
            ))}
            {puntos.map((punto, indice) => (
              <Fragment key={punto.clave}>
                <Text
                  testID={`cifra-${punto.clave}`}
                  onLayout={(evento) => anotarAltoDeLaCifra(evento.nativeEvent.layout.height)}
                  style={[
                    estilos.cifra,
                    letra('cuerpo', 'semifuerte'),
                    {
                      left: lugares[indice].x - columna / 2,
                      // Su orilla de abajo, en la de arriba del punto; se cuenta desde la línea base, por dentro.
                      bottom: altoDelDibujo - grosor.control - (lugares[indice].y - LADO_DEL_PUNTO / 2),
                      width: columna,
                    },
                  ]}
                >
                  {textoDe(punto)}
                </Text>
                <View
                  testID={`punto-${punto.clave}`}
                  style={[
                    estilos.punto,
                    { left: lugares[indice].x - LADO_DEL_PUNTO / 2, top: lugares[indice].y - LADO_DEL_PUNTO / 2 },
                  ]}
                />
              </Fragment>
            ))}
          </View>
          <View style={estilos.etiquetas}>
            {puntos.map((punto) => (
              <Text key={punto.clave} style={[estilos.etiqueta, letra('cuerpo', 'regular'), { width: columna }]}>
                {punto.etiqueta}
              </Text>
            ))}
          </View>
        </View>
      </ScrollView>
    </View>
  );
}

function textoDe({ valor, texto }: PuntoDeLaLinea): string {
  return texto ?? String(valor);
}

/**
 * El segmento entre dos puntos: del largo de la distancia entre ellos, con su centro en el punto medio y girado al ángulo
 * entre ellos. Hacia abajo de la pantalla el ángulo es positivo, como el giro de React Native.
 */
function segmentoEntre(desde: Lugar, hasta: Lugar) {
  const largo = Math.hypot(hasta.x - desde.x, hasta.y - desde.y);
  const grados = (Math.atan2(hasta.y - desde.y, hasta.x - desde.x) * 180) / Math.PI;
  return {
    width: largo,
    left: (desde.x + hasta.x) / 2 - largo / 2,
    top: (desde.y + hasta.y) / 2 - GROSOR_DE_LA_LINEA / 2,
    transform: [{ rotate: `${grados}deg` }],
  };
}

const estilos = StyleSheet.create({
  dibujo: {
    borderBottomWidth: grosor.control,
    borderBottomColor: color.semantico.borde_fuerte,
  },
  segmento: {
    position: 'absolute',
    height: GROSOR_DE_LA_LINEA,
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.accion,
  },
  punto: {
    position: 'absolute',
    width: LADO_DEL_PUNTO,
    height: LADO_DEL_PUNTO,
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.accion,
  },
  // La cifra, centrada sobre su punto y apoyada en él.
  cifra: {
    position: 'absolute',
    textAlign: 'center',
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
    fontVariant: ['tabular-nums'],
  },
  etiquetas: {
    flexDirection: 'row',
  },
  etiqueta: {
    paddingTop: espaciado[2],
    textAlign: 'center',
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
