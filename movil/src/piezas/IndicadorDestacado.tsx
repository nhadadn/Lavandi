// El indicador destacado con su tendencia (ADR-0022, alternativa 5): el monto promedio por pedido frente al periodo
// anterior de la misma duración (administracion-app-E47; indicadores-E09), con la forma de la maqueta de la Propuesta 06
// de la administración (45). Sin tendencia, la cifra con su nota.
//
// - La cifra grande y, debajo, la tendencia: una flecha del color de hacia dónde fue (subió, en el de éxito; bajó, en el
//   de error; igual, en el texto suave) y su texto, que lo dice con palabras. El color nunca va solo (design system,
//   reglas transversales). La flecha es un carácter, sin librería de íconos, y es adorno: el texto ya lo dice.
// - Se anuncia al lector de pantalla en un solo anuncio: lo que cuenta, la cifra y el texto de la tendencia.
//
// No trae cifras ni textos propios: la cifra y el texto de la tendencia llegan por propiedades, ya escritos. No es uno
// de los seis componentes del design system: se arma con los tokens (ADR-0013), como TarjetaDeCifras.
import { StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { color, espaciado, tipografia } from '../tema/theme';

/** Hacia dónde fue la cifra frente al periodo anterior, como App\Indicadores\Tendencia. */
export type SentidoDeLaTendencia = 'subio' | 'bajo' | 'igual';

interface Propiedades {
  /** Lo que cuenta la cifra: abre su anuncio. */
  titulo: string;
  /** La cifra, ya escrita: "$250.00". */
  valor: string;
  /** El texto de la tendencia, o la nota si no la hay. */
  detalle?: string;
  /** Hacia dónde fue; sin él, el detalle es una nota, sin flecha. */
  sentido?: SentidoDeLaTendencia;
  testID?: string;
}

// La flecha y su color, por sentido.
const FLECHAS: Record<SentidoDeLaTendencia, { flecha: string; tono: string }> = {
  subio: { flecha: '↑', tono: color.estado.exito },
  bajo: { flecha: '↓', tono: color.estado.error },
  igual: { flecha: '→', tono: color.semantico.texto_suave },
};

export function IndicadorDestacado({ titulo, valor, detalle, sentido, testID = 'indicador-destacado' }: Propiedades) {
  const anuncio = `${titulo}: ${valor}${detalle ? `. ${detalle}` : ''}`;
  const tendencia = sentido ? FLECHAS[sentido] : null;

  return (
    <View accessible accessibilityLabel={anuncio} testID={testID} style={estilos.indicador}>
      <Text style={[estilos.valor, letra('titulares', 'fuerte')]}>{valor}</Text>
      {detalle ? (
        <View style={estilos.tendencia}>
          {tendencia ? (
            <Text testID="tendencia-flecha" style={[estilos.flecha, letra('cuerpo', 'fuerte'), { color: tendencia.tono }]}>
              {tendencia.flecha}
            </Text>
          ) : null}
          <Text style={[estilos.detalle, letra('cuerpo', 'regular'), tendencia ? null : estilos.nota]}>{detalle}</Text>
        </View>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  indicador: {
    gap: espaciado[2],
  },
  // Del tamaño de la maqueta 45, en números tabulares, para que las cifras no bailen (design system, reglas
  // transversales).
  valor: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.titulo_mayor,
    fontVariant: ['tabular-nums'],
  },
  tendencia: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: espaciado[2],
  },
  flecha: {
    fontSize: tipografia.escala.cuerpo_grande,
  },
  detalle: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  nota: {
    color: color.semantico.texto_suave,
  },
});
