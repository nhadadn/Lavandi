// La dona (ADR-0022, alternativa 5): los pedidos por estado (administracion-app-E46; indicadores-E04), con la forma de
// la maqueta de la Propuesta 06 de la administración (43). La porción de los cancelados espera DD-016 (tarea 22.10).
//
// Sin librería de gráficas ni SVG, la dona se dibuja con vistas (ADR-0022, "Cómo se dibuja con vistas lo que no es
// rectángulo"): un anillo de marcas cortas alrededor de un centro, juntas para que se lea como un anillo liso, cada una
// girada a su ángulo (desde las doce, en el sentido del reloj) y del color de la porción en que cae. Las porciones van
// en el orden en que llegan. Sin ninguna cifra, el anillo queda entero en el color del borde.
//
// - Al centro, el total con lo que cuenta, si la pantalla lo da; al lado, cada porción con su color, su nombre y su
//   cifra, en texto: se lee sin el dibujo y sin depender del color (design system, reglas transversales).
// - Se anuncia al lector de pantalla como una imagen con el total y cada porción con su cifra.
//
// Si con vistas no se lee bien después de 2 sesiones de trabajo, o Arthur la rechaza en el emulador, se pasa a
// react-native-svg con una adenda de ADR-0022 (criterio de reversión): cambia solo esta pieza y la de la línea.
//
// No trae cifras ni colores propios: llegan por propiedades, del tema. No es uno de los seis componentes del design
// system: se arma con los tokens (ADR-0013).
import { StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';

/** Una porción: su nombre, su cifra, cómo se escribe y su color, que da la pantalla desde el tema. */
export interface PorcionDeLaDona {
  clave: string;
  etiqueta: string;
  valor: number;
  /** La cifra como se escribe, si no es el número tal cual. */
  texto?: string;
  color: string;
}

interface Propiedades {
  /** Lo que cuenta la dona: abre su anuncio. */
  titulo: string;
  porciones: readonly PorcionDeLaDona[];
  /** Lo que va al centro: el total, ya escrito, y lo que cuenta ("20" y "pedidos"). */
  centro?: { cifra: string; texto: string };
  testID?: string;
}

// La medida de la maqueta 43: 128 de lado, 144 marcas de 16 de largo junto a la orilla. Con este número, las marcas se
// tocan y el anillo se lee liso.
const LADO = espaciado[8] * 2;
const MARCAS = 144;
const LARGO_DE_LA_MARCA = espaciado[4];
const ANCHO_DE_LA_MARCA = grosor.foco;
// El radio del centro de las marcas: la orilla de afuera queda a espaciado[1] del borde.
const RADIO_DE_LAS_MARCAS = LADO / 2 - espaciado[1] - LARGO_DE_LA_MARCA / 2;

export function Dona({ titulo, porciones, centro, testID = 'dona' }: Propiedades) {
  const colores = coloresDeLasMarcas(porciones);
  const anuncio = [
    titulo,
    ...(centro ? [`${centro.cifra} ${centro.texto}`] : []),
    ...porciones.map((porcion) => `${porcion.etiqueta}: ${textoDe(porcion)}`),
  ];

  return (
    <View
      accessible
      accessibilityRole="image"
      accessibilityLabel={anuncio.join('. ')}
      testID={testID}
      style={estilos.dona}
    >
      <View style={estilos.anillo} testID="dona-anillo">
        {colores.map((tono, indice) => (
          <View
            key={indice}
            testID={`marca-${indice}`}
            style={[estilos.marca, LUGARES_DE_LAS_MARCAS[indice], { backgroundColor: tono }]}
          />
        ))}
        {centro ? (
          <View style={estilos.centro}>
            <Text style={[estilos.total, letra('titulares', 'fuerte')]}>{centro.cifra}</Text>
            <Text style={[estilos.loQueCuenta, letra('cuerpo', 'regular')]}>{centro.texto}</Text>
          </View>
        ) : null}
      </View>
      <View style={estilos.leyenda}>
        {porciones.map((porcion) => (
          <View key={porcion.clave} style={estilos.porcion}>
            <View style={[estilos.muestra, { backgroundColor: porcion.color }]} testID={`muestra-${porcion.clave}`} />
            <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{porcion.etiqueta}</Text>
            <Text style={[estilos.cifra, letra('titulares', 'fuerte')]}>{textoDe(porcion)}</Text>
          </View>
        ))}
      </View>
    </View>
  );
}

function textoDe({ valor, texto }: PorcionDeLaDona): string {
  return texto ?? String(valor);
}

/**
 * El color de cada marca: el de la porción en que cae su ángulo. La marca i está en i/MARCAS de la vuelta; cae en la
 * primera porción cuya suma, contada desde las doce, pasa de ahí. Sin ninguna cifra, todas en el color del borde.
 */
function coloresDeLasMarcas(porciones: readonly PorcionDeLaDona[]): string[] {
  const total = porciones.reduce((suma, { valor }) => suma + Math.max(0, valor), 0);
  return Array.from({ length: MARCAS }, (_, indice) => {
    if (total === 0) {
      return color.semantico.borde;
    }
    let acumulado = 0;
    for (const porcion of porciones) {
      acumulado += Math.max(0, porcion.valor);
      if (indice / MARCAS < acumulado / total) {
        return porcion.color;
      }
    }
    return porciones[porciones.length - 1].color;
  });
}

/**
 * Dónde va la marca i: su centro sobre el radio que sale a su ángulo, desde las doce en el sentido del reloj, y girada
 * a ese ángulo sobre su centro, así queda a lo largo del radio.
 */
function lugarDeLaMarca(indice: number) {
  const grados = (indice * 360) / MARCAS;
  const radianes = (grados * Math.PI) / 180;
  const x = LADO / 2 + RADIO_DE_LAS_MARCAS * Math.sin(radianes);
  const y = LADO / 2 - RADIO_DE_LAS_MARCAS * Math.cos(radianes);
  return {
    left: x - ANCHO_DE_LA_MARCA / 2,
    top: y - LARGO_DE_LA_MARCA / 2,
    transform: [{ rotate: `${grados}deg` }],
  };
}

// Los lugares no dependen de las cifras: se calculan una vez, no en cada dibujo ni en cada refresco de la pantalla.
const LUGARES_DE_LAS_MARCAS = Array.from({ length: MARCAS }, (_, indice) => lugarDeLaMarca(indice));

const estilos = StyleSheet.create({
  dona: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[4],
  },
  anillo: {
    width: LADO,
    height: LADO,
  },
  marca: {
    position: 'absolute',
    width: ANCHO_DE_LA_MARCA,
    height: LARGO_DE_LA_MARCA,
  },
  centro: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  total: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.titulo,
    fontVariant: ['tabular-nums'],
  },
  loQueCuenta: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  leyenda: {
    flex: 1,
    gap: espaciado[3],
  },
  porcion: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[2],
  },
  muestra: {
    width: espaciado[3],
    height: espaciado[3],
    borderRadius: radio.chico,
  },
  etiqueta: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  cifra: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.titulo_menor,
    fontVariant: ['tabular-nums'],
  },
});
