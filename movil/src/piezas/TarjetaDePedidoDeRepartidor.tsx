// La tarjeta de pedido de socio repartidor, el componente 5 del design system (vault/design-system/componentes.md y
// canvas/PedidoRepartidor.dc.html), para los pedidos asignados del Módulo C (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3,
// Módulo C #2, "versión operativa mínima"): arriba, "Pedido <folio>" y la insignia cuadrada de su estado operativo, con
// el nombre que le da el servidor; debajo, lo que pide repartidor-E10: la fecha y la ventana de recolección en la hora de
// la plaza, la dirección de recogida, la de entrega y la lavandería.
//
// El pedido que espera su respuesta ofrece Rechazar y Aceptar, con la nota de que el rechazo pide el motivo
// (repartidor-E15 y E16). El que aceptó dice que lo aceptó y ofrece solo el botón de su paso siguiente, el que da el
// servidor en "siguiente", con su texto: Recolectado, En tránsito o Entregado (Módulo C #3; repartidor-E19). Nunca un
// botón de un salto ni de un regreso, ni uno antes de aceptar; entre Recolectado y Listo, que le tocan a la lavandería,
// ninguno (repartidor-E20). Sin las acciones, como en el rechazo y en la entrega, la tarjeta solo muestra el pedido.
//
// Del canvas no entran: el pin de la dirección (la app no tiene librería de íconos ni de SVG, y agregarla es una
// dependencia nueva), la distancia (sin ubicación del teléfono: el Módulo C no la usa) ni el acumulado del día (DD-019 y
// DD-013, repartidor-E29). No tiene datos de la cuenta que hizo el pedido: la API no los manda. Colores y medidas de
// theme.ts (ADR-0013).
import { StyleSheet, Text, View } from 'react-native';

import { complementoEnTexto, senasEnTexto } from '../direcciones/textos';
import { pedidoConFolio } from '../estados/textos';
import type { DireccionDelPedido } from '../pedido/api';
import type { PedidoDelRepartidor } from '../repartidor/api';
import {
  direccionDeLaLavanderia,
  recoleccionEnTexto,
  seEntregaDondeSeRecoge,
  TEXTOS_DEL_REPARTIDOR as TEXTOS,
} from '../repartidor/textos';
import { letra } from '../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';
import { Boton } from './Boton';
import { colorDelEstado, InsigniaDeEstado } from './InsigniaDeEstado';

/** La oferta que el repartidor ya aceptó (pedido_asignaciones.estado): la tarjeta lo dice. */
const OFERTA_ACEPTADA = 'aceptado';

/**
 * El color de cada estado en su insignia, como en el canvas del componente 5: el pedido que se le ofrece, Confirmado
 * para el usuario, lleva el de Confirmado; En tránsito, el de Recolectado. Los demás, el suyo de color.pedido.
 */
const TONO_DEL_REPARTIDOR = new Map<string, string>([
  ['confirmado', color.pedido.confirmado],
  ['aceptado', color.pedido.confirmado],
  ['en_entrega', color.pedido.recolectado],
]);

export function colorDelEstadoDelRepartidor(codigo: string): string {
  return TONO_DEL_REPARTIDOR.get(codigo) ?? colorDelEstado(codigo);
}

interface Propiedades {
  pedido: PedidoDelRepartidor;
  /** Aceptar la oferta que espera su respuesta. */
  alAceptar?: () => void;
  /** Rechazar la oferta que espera su respuesta: abre el motivo. */
  alRechazar?: () => void;
  /** Tocar el botón del paso siguiente. */
  alAvanzar?: () => void;
  /** Mientras se manda una acción del pedido, sus botones se bloquean: un doble toque no manda dos veces. */
  enviando?: boolean;
}

export function TarjetaDePedidoDeRepartidor({ pedido, alAceptar, alRechazar, alAvanzar, enviando = false }: Propiedades) {
  const { id_publico: idPublico, folio, estado, lavanderia, por_responder: porResponder, siguiente } = pedido;
  return (
    <View style={estilos.tarjeta} testID={`pedido-${idPublico}`}>
      <View style={estilos.cabecera}>
        <Text style={[estilos.folio, letra('cuerpo', 'semifuerte')]}>{pedidoConFolio(folio)}</Text>
        <InsigniaDeEstado
          codigo={estado.codigo}
          nombre={estado.nombre}
          forma="cuadrada"
          tono={colorDelEstadoDelRepartidor(estado.codigo)}
          testID="estado-del-pedido"
        />
      </View>
      <Dato testID="dato-recoleccion" etiqueta={TEXTOS.recoleccion} valor={recoleccionEnTexto(pedido.ventana)} />
      <DatoDeDireccion testID="dato-recogida" etiqueta={TEXTOS.recogerEn} direccion={pedido.recogida} />
      {seEntregaDondeSeRecoge(pedido) ? (
        <Dato testID="dato-entrega" etiqueta={TEXTOS.entregarEn} valor={TEXTOS.mismaDeLaRecogida} />
      ) : (
        <DatoDeDireccion testID="dato-entrega" etiqueta={TEXTOS.entregarEn} direccion={pedido.entrega} />
      )}
      <Dato
        testID="dato-lavanderia"
        etiqueta={TEXTOS.lavanderia}
        valor={lavanderia.nombre}
        lineas={[direccionDeLaLavanderia(lavanderia)]}
      />
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
      {pedido.oferta === OFERTA_ACEPTADA && alAvanzar ? (
        <Text style={[estilos.nota, letra('cuerpo', 'regular')]}>{TEXTOS.aceptaste}</Text>
      ) : null}
    </View>
  );
}

interface PropiedadesDelDato {
  etiqueta: string;
  valor: string;
  /** Las líneas debajo del valor, en el tono suave. */
  lineas?: string[];
  testID: string;
}

// Un dato de la tarjeta: la etiqueta en el tono suave, el valor y, debajo, sus líneas, como el resumen del pedido.
function Dato({ etiqueta, valor, lineas = [], testID }: PropiedadesDelDato) {
  return (
    <View style={estilos.dato} testID={testID}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <Text style={[estilos.valor, letra('cuerpo', 'semifuerte')]}>{valor}</Text>
      {lineas.filter(Boolean).map((linea) => (
        <Text key={linea} style={[estilos.linea, letra('cuerpo', 'regular')]}>
          {linea}
        </Text>
      ))}
    </View>
  );
}

// Una dirección del pedido: las señas, que es por donde la busca el repartidor, y debajo el municipio y las
// referencias.
function DatoDeDireccion({ etiqueta, direccion, testID }: { etiqueta: string; direccion: DireccionDelPedido; testID: string }) {
  return (
    <Dato testID={testID} etiqueta={etiqueta} valor={senasEnTexto(direccion)} lineas={[complementoEnTexto(direccion)]} />
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
  dato: {
    gap: espaciado[1],
  },
  etiqueta: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.micro,
  },
  valor: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
    lineHeight: tipografia.escala.menor * tipografia.interlineado.normal,
  },
  linea: {
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
