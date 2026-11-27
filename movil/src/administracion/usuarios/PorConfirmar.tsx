// Lo que espera una decisión en la gestión de usuarios, en ámbar con su número y sin botón (design.md del cambio,
// sección 5): lo que falta a la izquierda y, a la derecha, la marca "Por confirmar" del resumen del pedido
// (src/pedido/ResumenDelPedido.tsx, RenglonReservado) seguida de su DD: "Por confirmar · DD-111". RenglonReservado no
// lleva el número de la decisión, por eso la marca se arma aquí con sus mismos tokens; la marca lleva su borde además
// del color (design system, reglas transversales).
//
// En tarjeta, como el filtro de usuarios activos de la lista (DD-111, imagen 21 de la Propuesta 06); sin ella, como lo
// que pasa con un pedido en curso al bloquear, sobre el botón de la ficha (DD-016, imágenes 22 y 23).
import { StyleSheet, Text, View } from 'react-native';

import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import { porConfirmar } from './textos';

interface Propiedades {
  /** Lo que espera la decisión: "Solo los usuarios activos de un periodo". */
  etiqueta: string;
  /** El número de la decisión: "DD-111". */
  dd: string;
  /** En una tarjeta, como un renglón de la pantalla; sin ella, sobre los botones. */
  enTarjeta?: boolean;
  testID?: string;
}

export function PorConfirmar({ etiqueta, dd, enTarjeta = false, testID }: Propiedades) {
  return (
    <View style={[estilos.renglon, enTarjeta && estilos.tarjeta]} testID={testID}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <View style={estilos.marca}>
        <Text style={[estilos.textoDeLaMarca, letra('cuerpo', 'semifuerte')]}>{porConfirmar(dd)}</Text>
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  renglon: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  tarjeta: {
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  etiqueta: {
    flex: 1,
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  marca: {
    paddingHorizontal: espaciado[2],
    paddingVertical: espaciado[1],
    borderWidth: grosor.control,
    borderColor: color.estado.atencion,
    borderRadius: radio.chico,
  },
  textoDeLaMarca: {
    color: color.estado.atencion,
    fontSize: tipografia.escala.menor,
  },
});
