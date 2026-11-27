// La marca "Por confirmar · DD-…" de la administración: lo que espera una DD, en ámbar y con borde, sin cifra (design.md,
// sección 5; administracion-app-E37 y E53). Es la marca del resumen del pedido (src/pedido/ResumenDelPedido.tsx)
// seguida de las DD que espera. Una sola para el módulo: la usan los reportes financieros (TarjetaDeRenglones) y los
// indicadores (TarjetaDelIndicador), así un cambio en su forma o en su texto se hace aquí. El renglón que la lleva es
// de cada pantalla, porque las maquetas no lo dibujan igual: en los ingresos es la fila de una tarjeta de cifras
// (.cifra, imagen 31) y en los indicadores un renglón bajo la gráfica, en el tono suave (.renglon, imágenes 43 a 48).
//
// No es de los seis componentes del design system: se arma con los tokens (ADR-0013), con las medidas de la .marca de
// las maquetas de la Propuesta 06, la misma en todas: su borde además del color (design system, reglas transversales).
import { StyleSheet, Text, View } from 'react-native';

import { TEXTOS_DEL_RESUMEN } from '../pedido/textos';
import { letra } from '../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';

/** "Por confirmar · DD-012, DD-016": la marca del resumen del pedido con las DD que espera. */
export function porConfirmar(dds: readonly string[]): string {
  return `${TEXTOS_DEL_RESUMEN.porConfirmar} · ${dds.join(', ')}`;
}

/** La marca con las DD que espera. Quien la pone dice en su anuncio qué es lo que espera. */
export function MarcaPorConfirmar({ dds }: { dds: readonly string[] }) {
  return (
    <View style={estilos.marca}>
      <Text style={[estilos.texto, letra('cuerpo', 'semifuerte')]}>{porConfirmar(dds)}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  marca: {
    flexShrink: 1,
    paddingHorizontal: espaciado[2],
    paddingVertical: espaciado[1],
    borderWidth: grosor.control,
    borderColor: color.estado.atencion,
    borderRadius: radio.chico,
    backgroundColor: color.semantico.superficie,
  },
  texto: {
    color: color.estado.atencion,
    fontSize: tipografia.escala.menor,
  },
});
