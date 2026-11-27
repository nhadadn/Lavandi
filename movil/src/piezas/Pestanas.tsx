// Dos o más formas de hacer lo mismo, de las que se elige una, como "Escribirla" y "En el mapa" de la dirección nueva
// (direcciones-E01 y E04) en el tablero "Dirección nueva" de la Propuesta 5 corregida (C-233), o las cinco categorías de
// los indicadores (administracion-app-E45; ADR-0022, decisión 14). Cada pestaña se anuncia como pestaña y la elegida
// como seleccionada, así el color no es la única señal (design system, reglas transversales).
//
// Cuando las pestañas no caben en el ancho, la fila se desplaza de lado (tarea 18.3 de administracion-en-la-app); cuando
// caben, ocupan el ancho como antes. Como en la maqueta de la Propuesta 06 de la administración (41, 46 y 48):
// - si cada pestaña cabe en una parte igual del ancho, todas miden esa parte, como antes;
// - si caben juntas pero no en partes iguales, cada una mide lo que su texto y crecen por igual hasta llenar la fila;
// - si no caben, cada una mide lo que su texto y la fila se desplaza de lado;
// - ninguna se encoge, así su texto nunca se parte en dos renglones y lo que mide es siempre el de un renglón: si la
//   elegida, en negritas, ya no cabe en su parte, la fila lo nota al medirla;
// - un desvanecido del ancho de espaciado[8] marca la orilla por donde sigue la fila, del fondo transparente al color
//   del fondo, con el degradado de fondo de React Native, sin librería;
// - la fila se queda al principio o al final, lo que deje a la elegida entera y fuera del desvanecido; nunca se centra
//   en ella, así no corta palabras en las dos orillas a la vez. Solo si la elegida no queda entera en ninguno de los
//   dos, la fila la deja junto al desvanecido izquierdo: la elegida siempre queda entera;
// - al abrir, la fila ya está en su lugar: la primera vez se coloca sin deslizarse, y después se desliza al elegir;
// - al desplazarla, solo se vuelve a dibujar cuando llega a una orilla o sale de ella, que es cuando cambian los
//   desvanecidos.
//
// La pantalla de indicadores la pone como en las maquetas 41, 44, 46, 48, 49 y 69 con dos propiedades que la dirección
// nueva no usa: el sangrado, para que la fila se deslice hasta la orilla de la pantalla y el desvanecido quede en ella
// (.desliza, con margen de -16), y los separadores, una línea del color del borde entre pestañas.
//
// No es uno de los seis componentes del design system: se arma con los tokens (ADR-0013). Área táctil mínima de 48.
import { useEffect, useRef, useState } from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  type NativeScrollEvent,
  type NativeSyntheticEvent,
  type ScrollViewInstance,
} from 'react-native';

import { letra } from '../tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../tema/theme';

interface Propiedades<Clave extends string> {
  pestanas: readonly { clave: Clave; texto: string }[];
  elegida: Clave;
  alElegir: (clave: Clave) => void;
  /**
   * El relleno de la pantalla a cada lado: la fila se sale ese tanto, llega hasta la orilla al deslizarse y deja el
   * desvanecido en la orilla de la pantalla. Cuando no se desliza, queda donde quedaría sin él. Sin él, la fila se
   * queda dentro del relleno.
   */
  sangrado?: number;
  /** Una línea del color del borde entre cada pestaña y la anterior. */
  conSeparadores?: boolean;
}

/** Dónde queda una pestaña dentro de la fila y cuánto mide. */
interface Lugar {
  x: number;
  ancho: number;
}

/** Si la fila está al principio o al final de su desplazamiento. */
interface Orillas {
  alPrincipio: boolean;
  alFinal: boolean;
}

/** El ancho del desvanecido de cada orilla (maqueta 41). */
const DESVANECIDO = espaciado[8];

// Lo que sobra al medir: medio punto no es una pestaña que no cabe.
const TOLERANCIA = 0.5;

/** El relleno de cada lado del texto de una pestaña. */
const RELLENO = espaciado[3];

export function Pestanas<Clave extends string>({
  pestanas,
  elegida,
  alElegir,
  sangrado = 0,
  conSeparadores = false,
}: Propiedades<Clave>) {
  const desplazable = useRef<ScrollViewInstance>(null);
  // Si la fila ya se colocó una vez: la primera, al abrir, va sin deslizarse.
  const yaSeColoco = useRef(false);
  const [anchoVisible, setAnchoVisible] = useState(0);
  const [anchoDelContenido, setAnchoDelContenido] = useState(0);
  const [orillas, setOrillas] = useState<Orillas>({ alPrincipio: true, alFinal: false });
  const [lugares, setLugares] = useState<Partial<Record<Clave, Lugar>>>({});
  const [anchosDeLosTextos, setAnchosDeLosTextos] = useState<Partial<Record<Clave, number>>>({});

  // El ancho de la fila adentro de su borde y del sangrado de los dos lados.
  const adentro = anchoVisible - 2 * sangrado - 2 * grosor.control;
  const parejas = cabenParejas(
    pestanas.map(({ clave }) => anchosDeLosTextos[clave]),
    anchoVisible > 0 ? adentro : 0,
    conSeparadores ? grosor.control : 0,
  );
  const parteIgual = parejas ? adentro / pestanas.length : null;
  // Repartidas por igual, la fila mide lo que se ve: no hay nada que desplazar, aunque el tamaño del contenido que se
  // midió antes de repartirlas todavía no se ponga al día.
  const fin = parejas ? 0 : Math.max(0, anchoDelContenido - anchoVisible);
  const seDesplaza = fin > TOLERANCIA;
  const lugarDeLaElegida = lugares[elegida];

  // Al elegir, o si cambian las medidas, la fila va adonde la elegida queda entera. La pestaña se mide dentro de la
  // fila, y la fila empieza después del sangrado.
  useEffect(() => {
    if (!seDesplaza || lugarDeLaElegida === undefined) {
      return;
    }
    const enElContenido = { x: lugarDeLaElegida.x + sangrado, ancho: lugarDeLaElegida.ancho };
    desplazable.current?.scrollTo({
      x: desplazamientoParaVer(enElContenido, anchoVisible, fin),
      animated: yaSeColoco.current,
    });
    yaSeColoco.current = true;
  }, [seDesplaza, lugarDeLaElegida, anchoVisible, fin, sangrado]);

  // Al desplazarla, si está en una orilla. El estado se toca solo cuando llega a una o sale de ella: así la fila no se
  // vuelve a dibujar en cada cuadro mientras se desliza.
  function anotarOrillas({ nativeEvent }: NativeSyntheticEvent<NativeScrollEvent>) {
    const x = nativeEvent.contentOffset.x;
    const alPrincipio = x <= TOLERANCIA;
    const alFinal = x >= nativeEvent.contentSize.width - nativeEvent.layoutMeasurement.width - TOLERANCIA;
    if (alPrincipio !== orillas.alPrincipio || alFinal !== orillas.alFinal) {
      setOrillas({ alPrincipio, alFinal });
    }
  }

  function anotarLugar(clave: Clave, lugar: Lugar) {
    setLugares((anteriores) => {
      const anterior = anteriores[clave];
      return anterior?.x === lugar.x && anterior.ancho === lugar.ancho ? anteriores : { ...anteriores, [clave]: lugar };
    });
  }

  function anotarAnchoDelTexto(clave: Clave, ancho: number) {
    setAnchosDeLosTextos((anteriores) => (anteriores[clave] === ancho ? anteriores : { ...anteriores, [clave]: ancho }));
  }

  return (
    <View style={[estilos.marco, sangrado > 0 ? { marginHorizontal: -sangrado } : null]} testID="pestanas-marco">
      <ScrollView
        ref={desplazable}
        testID="pestanas-desplazable"
        horizontal
        scrollEnabled={seDesplaza}
        showsHorizontalScrollIndicator={false}
        scrollEventThrottle={16}
        onLayout={(evento) => setAnchoVisible(evento.nativeEvent.layout.width)}
        onContentSizeChange={(ancho) => setAnchoDelContenido(ancho)}
        onScroll={anotarOrillas}
        contentContainerStyle={sangrado > 0 ? [estilos.contenido, { paddingHorizontal: sangrado }] : estilos.contenido}
      >
        <View accessibilityRole="tablist" testID="pestanas" style={estilos.fila}>
          {pestanas.map(({ clave, texto }, indice) => {
            const esLaElegida = clave === elegida;
            return (
              <Pressable
                key={clave}
                accessibilityRole="tab"
                accessibilityState={{ selected: esLaElegida }}
                onPress={() => alElegir(clave)}
                onLayout={(evento) =>
                  anotarLugar(clave, { x: evento.nativeEvent.layout.x, ancho: evento.nativeEvent.layout.width })
                }
                style={({ pressed }) => [
                  estilos.pestana,
                  parteIgual === null ? null : { minWidth: parteIgual },
                  conSeparadores && indice > 0 ? estilos.separada : null,
                  esLaElegida ? estilos.elegida : null,
                  pressed && !esLaElegida ? estilos.presionada : null,
                ]}
              >
                <Text
                  onLayout={(evento) => anotarAnchoDelTexto(clave, evento.nativeEvent.layout.width)}
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
      </ScrollView>
      {seDesplaza && !orillas.alPrincipio ? <Desvanecido orilla="izquierda" /> : null}
      {seDesplaza && !orillas.alFinal ? <Desvanecido orilla="derecha" /> : null}
    </View>
  );
}

/**
 * Si cada pestaña cabe en una parte igual del ancho de la fila, sin partir su texto: la más ancha, con su relleno y su
 * separador, por el número de pestañas, contra el ancho de adentro del borde y del sangrado. Hasta medir todos los
 * textos y la fila, no.
 */
function cabenParejas(anchosDeLosTextos: readonly (number | undefined)[], adentro: number, separador: number): boolean {
  if (adentro <= 0 || anchosDeLosTextos.length === 0 || anchosDeLosTextos.some((ancho) => ancho === undefined)) {
    return false;
  }
  const masAncha = Math.max(...(anchosDeLosTextos as number[])) + 2 * RELLENO + separador;
  return masAncha * anchosDeLosTextos.length <= adentro + TOLERANCIA;
}

/**
 * Cuánto desplazar la fila para que la elegida quede entera y fuera del desvanecido: al principio, si ahí termina antes
 * del desvanecido derecho; si no, al final, si ahí empieza después del izquierdo. Si no cabe entera en ninguno de los
 * dos, se deja junto al desvanecido izquierdo.
 */
function desplazamientoParaVer(lugar: Lugar, anchoVisible: number, fin: number): number {
  if (lugar.x + lugar.ancho <= anchoVisible - DESVANECIDO) {
    return 0;
  }
  if (lugar.x - fin >= DESVANECIDO) {
    return fin;
  }
  return Math.min(fin, Math.max(0, lugar.x - DESVANECIDO));
}

// El desvanecido de una orilla: adorno, sin anunciarse y sin recibir toques.
function Desvanecido({ orilla }: { orilla: 'izquierda' | 'derecha' }) {
  const esLaDerecha = orilla === 'derecha';
  return (
    <View
      testID={esLaDerecha ? 'desvanecido-derecho' : 'desvanecido-izquierdo'}
      pointerEvents="none"
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
      style={[estilos.desvanecido, esLaDerecha ? estilos.derecha : estilos.izquierda]}
    />
  );
}

/**
 * Un color de tokens.json (#RRGGBB) con su transparencia (#RRGGBBAA), para el degradado. tokens.json no trae el fondo
 * transparente: se deriva del fondo, y solo sirve si el fondo es #RRGGBB, lo que comprueba la prueba de la pieza.
 */
function conTransparencia(hexadecimal: string, opacidad: number): string {
  // El mismo color del tema con su transparencia en los dos últimos dígitos (#RRGGBBAA), que React Native entiende: así
  // ningún color se escribe a mano fuera de theme.ts (ADR-0013).
  const alfa = Math.round(opacidad * 255)
    .toString(16)
    .padStart(2, '0');
  return `${hexadecimal}${alfa}`;
}

// Del fondo transparente al color del fondo, con los pasos de la maqueta 41: casi entero a la mitad y entero a tres
// cuartos del ancho.
function degradadoHacia(direccion: 'left' | 'right'): string {
  const fondo = color.semantico.fondo;
  const pasos = [conTransparencia(fondo, 0), `${conTransparencia(fondo, 0.92)} 55%`, `${fondo} 75%`];
  return `linear-gradient(to ${direccion}, ${pasos.join(', ')})`;
}

const estilos = StyleSheet.create({
  marco: {
    position: 'relative',
  },
  // El contenido crece al menos hasta el ancho que se ve: así, cuando las pestañas caben, ocupan el ancho.
  contenido: {
    flexGrow: 1,
  },
  fila: {
    flexGrow: 1,
    flexDirection: 'row',
    borderWidth: grosor.control,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.medio,
    overflow: 'hidden',
    backgroundColor: color.semantico.superficie,
  },
  // Cada una mide lo que su texto, o la parte igual si cabe en ella, y crece por igual hasta llenar la fila; nunca se
  // encoge, así el texto no se parte.
  pestana: {
    flexGrow: 1,
    flexShrink: 0,
    minHeight: accesibilidad.area_tactil_minima_dp,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: RELLENO,
  },
  elegida: {
    backgroundColor: color.semantico.accion,
  },
  presionada: {
    backgroundColor: color.semantico.fondo,
  },
  separada: {
    borderLeftWidth: grosor.control,
    borderLeftColor: color.semantico.borde,
  },
  texto: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  textoElegido: {
    color: color.semantico.accion_texto,
  },
  desvanecido: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    width: DESVANECIDO,
  },
  izquierda: {
    left: 0,
    backgroundImage: degradadoHacia('left'),
  },
  derecha: {
    right: 0,
    backgroundImage: degradadoHacia('right'),
  },
});
