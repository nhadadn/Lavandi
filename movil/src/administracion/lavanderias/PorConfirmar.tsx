// Lo que espera una decisión, en ámbar con su número y sin cifra ni botón (design.md del cambio, sección 5): su nombre y,
// a un lado, la marca "Por confirmar" del resumen del pedido (src/pedido/ResumenDelPedido.tsx, RenglonReservado),
// seguida de su DD: "Por confirmar · DD-104". Con el texto, lo que eso significa para quien lo ve, debajo.
//
// En tarjeta, como el alta de la lista (DD-053, imagen 06 de la Propuesta 06) y el lavado en seco de la edición (DD-010,
// imagen 13); sin ella, como los pedidos en curso sobre los botones de la ficha (DD-104, imágenes 07 y 10). Ninguna
// pieza de src/piezas/ lleva el número de la decisión: RenglonReservado solo dice "Por confirmar". Colores y medidas de
// theme.ts (ADR-0013); la marca lleva su borde además del color (design system, reglas transversales).
//
// Deuda: la marca copia los estilos de la de RenglonReservado (src/pedido/ResumenDelPedido.tsx), y las fichas de
// repartidores y usuarios esperan otras DD con la misma marca. Va a una pieza común, junto a RenglonReservado y con el
// número de la DD por propiedad, en un cambio aparte que la use en todas.
import { StyleSheet, Text, View } from 'react-native';

import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import { porConfirmar } from './textos';

interface Propiedades {
  /** Lo que espera la decisión: "Dar de alta una lavandería". */
  etiqueta: string;
  /** El número de la decisión: "DD-053". */
  dd: string;
  /** Lo que significa la espera, debajo: "La lista y las fichas no ofrecen el alta.". */
  texto?: string;
  /** En una tarjeta, como un renglón de la pantalla; sin ella, sobre los botones. */
  enTarjeta?: boolean;
  testID?: string;
}

export function PorConfirmar({ etiqueta, dd, texto, enTarjeta = false, testID }: Propiedades) {
  return (
    <View style={[estilos.contenedor, enTarjeta && estilos.tarjeta]} testID={testID}>
      <View style={estilos.renglon}>
        <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
        <View style={estilos.marca}>
          <Text style={[estilos.textoDeLaMarca, letra('cuerpo', 'semifuerte')]}>{porConfirmar(dd)}</Text>
        </View>
      </View>
      {texto ? <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{texto}</Text> : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  contenedor: {
    gap: espaciado[1],
  },
  tarjeta: {
    padding: espaciado[4],
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
  texto: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
