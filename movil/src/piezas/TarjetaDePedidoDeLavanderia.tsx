// La tarjeta de pedido de socio lavandería, el componente 4 del design system (vault/design-system/componentes.md y
// canvas/PedidoLavanderia.dc.html), para los pedidos activos del Módulo B (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3,
// Módulo B #2): arriba, "Pedido <folio>" y la insignia cuadrada de su estado operativo, con el nombre que le da el
// servidor; debajo, la fecha y la ventana de recolección en la hora de la plaza y el servicio (pedidos-lavanderia-E03).
// El pedido que espera respuesta ofrece Rechazar y Aceptar, con la nota de que el rechazo pide el motivo. El aceptado
// ofrece solo el botón del paso siguiente, el que da el servidor en "siguiente", con su texto: Recolectado, En proceso,
// Listo o En proceso de entrega (Módulo B #3; estados-E06). Nunca un botón de un salto ni de un regreso; sin paso
// siguiente, como en En proceso de entrega, ninguno. Sin las acciones, como en la pantalla del rechazo, la tarjeta solo
// muestra el pedido.
//
// Del componente no entran: el monto y la calificación del pedido cerrado (historial-de-la-lavanderia, F-B04), ni el
// peso (sin escenario). La tarjeta no tiene datos del usuario: la API no los manda (pedidos-lavanderia-E04, DD-022 y
// DD-014). Colores y medidas de theme.ts (ADR-0013).
import { StyleSheet, Text, View } from 'react-native';

import { pedidoConFolio } from '../estados/textos';
import type { PedidoDeLaLavanderia } from '../pedidos-lavanderia/api';
import { recoleccionYServicio, TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA as TEXTOS } from '../pedidos-lavanderia/textos';
import { letra } from '../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';
import { Boton } from './Boton';
import { colorDelEstado, InsigniaDeEstado } from './InsigniaDeEstado';

/**
 * El color de cada estado operativo en su insignia. "Por aceptar" lleva el de atención, como en el canvas del
 * componente 4. Los que se llaman como uno de los cinco del usuario (Recolectado, En proceso, Listo, Entregado) llevan
 * el suyo de color.pedido; "Aceptado" y "En proceso de entrega", el del paso en que los ve el usuario, Confirmado y
 * Listo (adenda de ADR-0021).
 */
const TONO_OPERATIVO = new Map<string, string>([
  ['confirmado', color.estado.atencion],
  ['aceptado', color.pedido.confirmado],
  ['en_entrega', color.pedido.listo],
]);

export function colorDelEstadoOperativo(codigo: string): string {
  return TONO_OPERATIVO.get(codigo) ?? colorDelEstado(codigo);
}

interface Propiedades {
  pedido: PedidoDeLaLavanderia;
  /** Aceptar el pedido que espera respuesta. */
  alAceptar?: () => void;
  /** Rechazar el pedido que espera respuesta: abre el motivo. */
  alRechazar?: () => void;
  /** Tocar el botón del paso siguiente. */
  alAvanzar?: () => void;
  /** Mientras se manda una acción del pedido, sus botones se bloquean: un doble toque no manda dos veces. */
  enviando?: boolean;
}

export function TarjetaDePedidoDeLavanderia({
  pedido,
  alAceptar,
  alRechazar,
  alAvanzar,
  enviando = false,
}: Propiedades) {
  const { id_publico: idPublico, folio, estado, por_responder: porResponder, siguiente } = pedido;
  return (
    <View style={estilos.tarjeta} testID={`pedido-${idPublico}`}>
      <View style={estilos.cabecera}>
        <Text style={[estilos.folio, letra('cuerpo', 'semifuerte')]}>{pedidoConFolio(folio)}</Text>
        <InsigniaDeEstado
          codigo={estado.codigo}
          nombre={estado.nombre}
          forma="cuadrada"
          tono={colorDelEstadoOperativo(estado.codigo)}
          testID="estado-del-pedido"
        />
      </View>
      <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{recoleccionYServicio(pedido)}</Text>
      {porResponder && alAceptar && alRechazar ? (
        <>
          <View style={estilos.respuestas}>
            <View>
              <Boton variante="secundario" deshabilitado={enviando} onPress={alRechazar}>
                {TEXTOS.rechazar}
              </Boton>
            </View>
            <View style={estilos.crece}>
              <Boton ocupado={enviando} onPress={alAceptar}>
                {TEXTOS.aceptar}
              </Boton>
            </View>
          </View>
          <Text style={[estilos.nota, letra('cuerpo', 'regular')]}>{TEXTOS.notaDelRechazo}</Text>
        </>
      ) : null}
      {siguiente && alAvanzar ? (
        <Boton ocupado={enviando} onPress={alAvanzar}>
          {siguiente.boton}
        </Boton>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    gap: espaciado[3],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  cabecera: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  folio: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    lineHeight: tipografia.escala.menor * tipografia.interlineado.normal,
  },
  // Rechazar a su medida y Aceptar con el resto del ancho, como en el canvas.
  respuestas: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[2],
  },
  crece: {
    flex: 1,
  },
  nota: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.micro,
    lineHeight: tipografia.escala.micro * tipografia.interlineado.normal,
  },
});
