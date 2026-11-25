// Un pedido en el historial de la lavandería (historial-lavanderia-E01): su folio y, debajo, la fecha en que se entregó.
// El lector de pantalla la lee completa, en un solo anuncio. No se toca: ningún escenario abre el detalle desde el
// historial. No es uno de los seis componentes del design system: el historial no tiene componente propio (design.md de
// historial-de-la-lavanderia, Open Questions) y se arma con los tokens (ADR-0013), como la tarjeta de "Mis pedidos".
//
// De la imagen b04-historial de la Propuesta 04 no entran: el monto (DD-013, DD-020 y DD-012), la calificación recibida
// (DD-052) ni el selector de periodo (proposal.md del cambio, "Fuera del contrato").
import { StyleSheet, Text, View } from 'react-native';

import { pedidoConFolio } from '../estados/textos';
import { letra } from '../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';
import type { PedidoEntregado } from './api';
import { entregadoEl, pedidoEntregadoParaLeer } from './textos';

export function TarjetaDePedidoEntregado({ pedido }: { pedido: PedidoEntregado }) {
  return (
    <View
      accessible
      accessibilityLabel={pedidoEntregadoParaLeer(pedido)}
      testID={`entregado-${pedido.id_publico}`}
      style={estilos.tarjeta}
    >
      <Text style={[estilos.folio, letra('titulares', 'fuerte')]}>{pedidoConFolio(pedido.folio)}</Text>
      <Text style={[estilos.fecha, letra('cuerpo', 'regular')]}>{entregadoEl(pedido.fecha)}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    gap: espaciado[1],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  folio: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  fecha: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
