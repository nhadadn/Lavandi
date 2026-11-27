// Un pedido en la lista de "Mis pedidos" (historial-E01): su folio y la insignia de su estado, la del componente 3 del
// design system; debajo, su lavandería y su fecha. Se toca toda la parte de arriba, que abre el seguimiento del pedido, y
// el lector de pantalla la lee completa, en un solo anuncio. No es uno de los seis componentes del design system: la
// pantalla no tiene componente propio (design.md de historial-y-calificacion, Open Questions) y se arma con los tokens
// (ADR-0013), como la tarjeta del mock A13 y la imagen a13-historial de la Propuesta 04.
//
// Abajo, la calificación del servicio (historial-E06), solo donde el contrato de la API la permite, en la lectura
// provisional de BR-25: "Calificar" en un pedido Entregado sin calificar, que abre la calificación, y "Calificaste 4 de
// 5" en uno ya calificado, una sola vez. Va fuera de la parte que abre el seguimiento, para que el lector de pantalla
// llegue a ella por separado.
//
// Del mock no entran: el total, que espera los precios (DD-010); los servicios, que la lista no trae; ni "Repetir"
// (historial-E05, bloqueado por BR-25).
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { pedidoConFolio } from '../estados/textos';
import { Enlace } from '../piezas/Enlace';
import { InsigniaDeEstado } from '../piezas/InsigniaDeEstado';
import { letra } from '../tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../tema/theme';
import type { PedidoDelHistorial } from './api';
import {
  calificarParaLeer,
  calificasteALaVista,
  calificasteParaLeer,
  fechaDelPedido,
  pedidoParaLeer,
  TEXTOS_DEL_HISTORIAL,
} from './textos';

// El estado en que el pedido se puede calificar, en la lectura provisional de BR-25 (design.md, decisión 6).
const SE_CALIFICA_EN = 'entregado';

interface Propiedades {
  pedido: PedidoDelHistorial;
  alTocar: () => void;
  alCalificar: () => void;
}

export function TarjetaDePedido({ pedido, alTocar, alCalificar }: Propiedades) {
  const { id_publico: idPublico, folio, fecha, lavanderia, estado, calificacion } = pedido;
  const sePuedeCalificar = estado.codigo === SE_CALIFICA_EN && calificacion === null;
  return (
    <View style={estilos.tarjeta}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={pedidoParaLeer(pedido)}
        onPress={alTocar}
        testID={`pedido-${idPublico}`}
        style={({ pressed }) => [estilos.contenido, pressed && estilos.presionada]}
      >
        <View style={estilos.cabecera}>
          <Text style={[estilos.folio, letra('titulares', 'fuerte')]}>{pedidoConFolio(folio)}</Text>
          {/* Con visible_usuario en false, la app no nombra el estado (estados-E02). */}
          {estado.visible_usuario ? (
            <InsigniaDeEstado codigo={estado.codigo} nombre={estado.nombre} testID="estado-del-pedido" />
          ) : null}
        </View>
        <Text style={[estilos.lavanderia, letra('cuerpo', 'semifuerte')]}>{lavanderia.nombre}</Text>
        <Text style={[estilos.fecha, letra('cuerpo', 'regular')]}>{fechaDelPedido(fecha)}</Text>
      </Pressable>
      {calificacion || sePuedeCalificar ? (
        <View style={estilos.pie} testID={`calificacion-${idPublico}`}>
          {calificacion ? (
            <Text
              accessibilityLabel={calificasteParaLeer(calificacion.estrellas)}
              style={[estilos.calificada, letra('cuerpo', 'regular')]}
            >
              {calificasteALaVista(calificacion.estrellas)}
            </Text>
          ) : (
            <Enlace texto={TEXTOS_DEL_HISTORIAL.calificar} paraLeer={calificarParaLeer(folio)} alTocar={alCalificar} />
          )}
        </View>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
    overflow: 'hidden',
  },
  contenido: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    gap: espaciado[1],
    padding: espaciado[4],
  },
  presionada: {
    backgroundColor: color.semantico.superficie_hundida,
  },
  cabecera: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
    marginBottom: espaciado[1],
  },
  folio: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  lavanderia: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  fecha: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  pie: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    justifyContent: 'center',
    paddingHorizontal: espaciado[4],
    borderTopWidth: grosor.control,
    borderTopColor: color.semantico.borde,
  },
  calificada: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
