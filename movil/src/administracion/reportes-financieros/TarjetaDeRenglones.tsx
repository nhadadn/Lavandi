// Una tarjeta de renglones de los reportes financieros: lo que cuenta a la izquierda y su cifra a la derecha, o, si su
// regla todavía espera una DD, la marca "Por confirmar" con su número, en ámbar y con borde, sin cifra (design.md,
// sección 5; administracion-app-E37). La usan las cifras del periodo de los ingresos (imagen 31 de la Propuesta 06) y
// el resumen de la conciliación (imagen 33). Es la tarjeta de TarjetaDeCifras (src/piezas/), que solo lleva números:
// aquí la cifra puede ser un monto en texto, como lo da el servidor ("$3,250.00"), y la marca la del módulo
// (src/administracion/MarcaPorConfirmar.tsx). Cada renglón se lee en un solo anuncio: "Pagos confirmados: 13".
import { Fragment } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import { MarcaPorConfirmar, porConfirmar } from '../MarcaPorConfirmar';

/** Un renglón con su cifra, o uno cuya regla espera esas DD. La clave nombra su testID: `renglon-<clave>`. */
export type Renglon =
  | { clave: string; etiqueta: string; valor: string; destacado?: boolean }
  | { clave: string; etiqueta: string; esperaA: readonly string[] };

export function TarjetaDeRenglones({ renglones, testID }: { renglones: readonly Renglon[]; testID: string }) {
  return (
    <View style={estilos.tarjeta} testID={testID}>
      {renglones.map((renglon, indice) => (
        <Fragment key={renglon.clave}>
          {indice > 0 ? <View style={estilos.separador} /> : null}
          {'esperaA' in renglon ? <RenglonQueEspera {...renglon} /> : <RenglonConCifra {...renglon} />}
        </Fragment>
      ))}
    </View>
  );
}

function RenglonConCifra({ clave, etiqueta, valor, destacado = false }: Extract<Renglon, { valor: string }>) {
  return (
    <View accessible accessibilityLabel={`${etiqueta}: ${valor}`} testID={`renglon-${clave}`} style={estilos.renglon}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <Text style={[estilos.valor, destacado ? estilos.destacado : null, letra('titulares', 'fuerte')]}>{valor}</Text>
    </View>
  );
}

function RenglonQueEspera({ clave, etiqueta, esperaA }: Extract<Renglon, { esperaA: readonly string[] }>) {
  return (
    <View
      accessible
      accessibilityLabel={`${etiqueta}: ${porConfirmar(esperaA)}`}
      testID={`renglon-${clave}`}
      style={estilos.renglon}
    >
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <MarcaPorConfirmar dds={esperaA} />
    </View>
  );
}

const estilos = StyleSheet.create({
  // La tarjeta, la fila y el separador de TarjetaDeCifras.
  tarjeta: {
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[2],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  renglon: {
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
  // El total del periodo, la cifra principal de la pantalla (imagen 31).
  destacado: {
    fontSize: tipografia.escala.titulo,
  },
});
