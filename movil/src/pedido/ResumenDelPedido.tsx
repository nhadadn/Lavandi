// El resumen del pedido, componente 6 del design system (vault/design-system/componentes.md), con la composición de las
// imágenes a09-resumen y a11-confirmacion de la Propuesta 04: la lavandería arriba y, debajo, el servicio, la fecha con
// su ventana en la hora de la plaza, la recogida y la entrega. Lo usan el resumen antes del pago (confirmacion-E01) y la
// confirmación con el folio (confirmacion-E09), que no lleva la entrega. Colores y medidas de theme.ts (ADR-0013).
//
// Abajo van los renglones que pase la pantalla: en el resumen, el precio estimado y la política de cancelación,
// reservados sin cifra con RenglonReservado mientras esperan DD-012, DD-010 y DD-016 (confirmacion-E02 y E04). El envío
// no lleva renglón mientras DD-019 y DD-025 no digan si es un cargo aparte (confirmacion-E03). Los íconos del mock no entran: la app no tiene una librería de íconos, y cada dato
// lleva su etiqueta escrita. Tampoco entran "Cambiar servicio", "Cambiar ventana" ni "Cambiar dirección": los pasos
// anteriores se alcanzan con "Regresar".
import type { ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import type { Ventana } from '../agenda/api';
import { eleccionEnTexto } from '../agenda/textos';
import type { Direccion } from '../direcciones/api';
import { complementoEnTexto, senasEnTexto } from '../direcciones/textos';
import { letra } from '../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';
import { TEXTOS_DEL_RESUMEN } from './textos';

/** Lo que el resumen muestra de una dirección: una guardada o la copia que guardó el pedido. */
export type DireccionALaVista = Pick<
  Direccion,
  'alias' | 'calle' | 'numero_exterior' | 'numero_interior' | 'colonia' | 'municipio' | 'referencias'
>;

interface Propiedades {
  lavanderia: string;
  /** El nombre del servicio elegido. */
  servicio: string;
  /** La ventana como la dio el servidor, con su fecha y sus horas en la hora de la plaza. */
  ventana: Ventana;
  recogida: DireccionALaVista;
  /** La entrega, o "misma" si es la de la recogida (direcciones-E09). Sin ella, no se muestra. */
  entrega?: DireccionALaVista | 'misma';
  /** Los renglones de abajo. */
  children?: ReactNode;
}

export function ResumenDelPedido({ lavanderia, servicio, ventana, recogida, entrega, children }: Propiedades) {
  return (
    <View style={estilos.tarjeta} testID="resumen-del-pedido">
      <Text style={[estilos.lavanderia, letra('titulares', 'fuerte')]}>{lavanderia}</Text>
      <Dato testID="dato-servicio" etiqueta={TEXTOS_DEL_RESUMEN.servicio} valor={servicio} />
      {/* "Jueves 3 de diciembre · 11:00 – 12:00": la fecha y las horas de la plaza, sin pasarlas a las del teléfono. */}
      <Dato testID="dato-recoleccion" etiqueta={TEXTOS_DEL_RESUMEN.recoleccion} valor={eleccionEnTexto(ventana, null)} />
      <DatoDeDireccion testID="dato-recogida" etiqueta={TEXTOS_DEL_RESUMEN.recogida} direccion={recogida} />
      {entrega === 'misma' ? (
        <Dato testID="dato-entrega" etiqueta={TEXTOS_DEL_RESUMEN.entrega} valor={TEXTOS_DEL_RESUMEN.mismaDeLaRecogida} />
      ) : null}
      {entrega && entrega !== 'misma' ? (
        <DatoDeDireccion testID="dato-entrega" etiqueta={TEXTOS_DEL_RESUMEN.entrega} direccion={entrega} />
      ) : null}
      {children ? <View style={estilos.renglones}>{children}</View> : null}
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

function Dato({ etiqueta, valor, lineas = [], testID }: PropiedadesDelDato) {
  return (
    <View style={estilos.dato} testID={testID}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <Text style={[estilos.valor, letra('cuerpo', 'semifuerte')]}>{valor}</Text>
      {lineas.map((linea) => (
        <Text key={linea} style={[estilos.linea, letra('cuerpo', 'regular')]}>
          {linea}
        </Text>
      ))}
    </View>
  );
}

// Una dirección: el nombre de la favorita, o sus señas si no tiene, y debajo las señas, el municipio y las referencias,
// como la opción del paso de la dirección.
function DatoDeDireccion({ etiqueta, direccion, testID }: { etiqueta: string; direccion: DireccionALaVista; testID: string }) {
  const senas = senasEnTexto(direccion);
  const lineas = [direccion.alias ? senas : '', complementoEnTexto(direccion)].filter(Boolean);
  return <Dato testID={testID} etiqueta={etiqueta} valor={direccion.alias ?? senas} lineas={lineas} />;
}

/**
 * Un renglón cuyo contenido todavía no existe: su etiqueta y, en lugar de la cifra o del texto, la marca "Por
 * confirmar", con su borde además del color (design system, reglas transversales); con dd, seguida del número de la
 * decisión que espera: "Por confirmar · DD-019". No se inventa ni un monto ni una política (componente 6, punto 2).
 */
export function RenglonReservado({ etiqueta, dd, testID }: { etiqueta: string; dd?: string; testID: string }) {
  return (
    <View style={estilos.renglon} testID={testID}>
      <Text style={[estilos.etiquetaDelRenglon, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <View style={estilos.marca}>
        <Text style={[estilos.textoDeLaMarca, letra('cuerpo', 'semifuerte')]}>{marcaDelRenglon(dd)}</Text>
      </View>
    </View>
  );
}

// La marca del renglón reservado: "Por confirmar" y, si se pasa, el número de la decisión que espera.
function marcaDelRenglon(dd: string | undefined): string {
  return dd ? `${TEXTOS_DEL_RESUMEN.porConfirmar} · ${dd}` : TEXTOS_DEL_RESUMEN.porConfirmar;
}

const estilos = StyleSheet.create({
  tarjeta: {
    gap: espaciado[4],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  lavanderia: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
    lineHeight: tipografia.escala.cuerpo_grande * tipografia.interlineado.apretado,
  },
  dato: {
    gap: espaciado[1],
  },
  etiqueta: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  valor: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  linea: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  // Los renglones van separados de los datos por la línea del color de borde, como el total del mock.
  renglones: {
    gap: espaciado[3],
    paddingTop: espaciado[4],
    borderTopWidth: grosor.control,
    borderTopColor: color.semantico.borde,
  },
  renglon: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  etiquetaDelRenglon: {
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
