// Una tarjeta de cifras: una fila por cifra, con lo que cuenta a la izquierda y la cifra a la derecha, en números
// tabulares para que no bailen (design system, reglas transversales), y una línea entre filas. Cada fila se lee en un
// solo anuncio: "Pedidos completados hoy: 2". La usan las estadísticas de la lavandería (historial-lavanderia-E05; imagen
// b05-estadisticas de la Propuesta 04) y las del repartidor (repartidor-E28; imagen c05-estadisticas). No es uno de los
// seis componentes del design system: las estadísticas no tienen componente propio y se arman con los tokens
// (ADR-0013).
import { Fragment } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';

/** Una cifra de la tarjeta: lo que cuenta, su valor y su testID. */
export interface Cifra {
  testID: string;
  etiqueta: string;
  valor: number;
}

export function TarjetaDeCifras({ cifras }: { cifras: readonly Cifra[] }) {
  return (
    <View style={estilos.tarjeta} testID="tarjeta-de-cifras">
      {cifras.map((cifra, indice) => (
        <Fragment key={cifra.testID}>
          {indice > 0 ? <View style={estilos.separador} /> : null}
          <FilaDeCifra {...cifra} />
        </Fragment>
      ))}
    </View>
  );
}

function FilaDeCifra({ testID, etiqueta, valor }: Cifra) {
  return (
    <View accessible accessibilityLabel={`${etiqueta}: ${valor}`} testID={testID} style={estilos.fila}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <Text style={[estilos.valor, letra('titulares', 'fuerte')]}>{String(valor)}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[2],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
    paddingVertical: espaciado[3],
  },
  separador: {
    height: grosor.control,
    backgroundColor: color.semantico.borde,
  },
  etiqueta: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  // Los números tabulares, para que las cifras no bailen (design system, reglas transversales).
  valor: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.titulo_menor,
    fontVariant: ['tabular-nums'],
  },
});
