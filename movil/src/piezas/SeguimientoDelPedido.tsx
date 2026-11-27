// El seguimiento del estado del pedido, componente 3 del design system (vault/design-system/componentes.md;
// NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #12). Los cinco estados son literales del contrato y llegan del
// servidor: no se renombran, no se agrupan y no se agrega un sexto. Arriba, el folio y la lavandería del pedido, y el
// estado actual en su insignia, con su nombre escrito y el color propio de color.pedido. Debajo, los cinco en orden:
//
// - cumplido: la marca llena con la palomita y la hora en que el pedido llegó a él (estados-E24);
// - actual: la marca con el color del estado, el nombre en fuerte y su hora;
// - pendiente: la marca vacía, el nombre en texto suave y "Pendiente", sin hora.
//
// El color nunca va solo: cada estado lleva su nombre y su situación en texto, y el lector de pantalla los lee juntos
// (design system, reglas transversales). La composición es la de la imagen a12-seguimiento de la Propuesta 04 y la
// muestra del design system; de la imagen no entran el mensaje bajo los pasos, que el contrato no da para cada estado,
// el aviso de "Actualizado hace…", ni el detalle de la recolección, la dirección y el peso (resumen-y-confirmacion,
// DD-012), ni "Cancelar pedido" (DD-016). Colores y medidas de theme.ts (ADR-0013).
//
// La palomita es el carácter "✓" con la escala tipográfica, como en el selector de fecha y hora: la app no tiene una
// librería de íconos. Su trazo depende de la letra del sistema y se revisa en un teléfono real.
import { StyleSheet, Text, View } from 'react-native';

import type { PasoALaVista, Situacion } from '../estados/seguimiento';
import { pasoParaLeer, pedidoConFolio, TEXTOS_DEL_SEGUIMIENTO } from '../estados/textos';
import { letra } from '../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../tema/theme';
import { colorDelEstado, InsigniaDeEstado } from './InsigniaDeEstado';

interface Propiedades {
  /** Vacío hasta Confirmado: sin él no hay "Pedido …". */
  folio: string | null;
  /** El nombre de la lavandería del pedido. */
  lavanderia: string;
  /** Los cinco, en orden, con su situación y su hora. */
  pasos: readonly PasoALaVista[];
}

export function SeguimientoDelPedido({ folio, lavanderia, pasos }: Propiedades) {
  const actual = pasos.find((paso) => paso.situacion === 'actual');
  return (
    <View style={estilos.tarjeta} testID="seguimiento">
      <View style={estilos.cabecera}>
        <View style={estilos.identidad}>
          {folio ? <Text style={[estilos.folio, letra('titulares', 'fuerte')]}>{pedidoConFolio(folio)}</Text> : null}
          <Text style={[estilos.lavanderia, letra('cuerpo', 'regular')]}>{lavanderia}</Text>
        </View>
        {/* El estado actual, con el punto y el nombre en el color del estado. */}
        {actual ? <InsigniaDeEstado codigo={actual.codigo} nombre={actual.nombre} testID="estado-actual" /> : null}
      </View>
      <View>
        {pasos.map((paso, indice) => (
          <Paso key={paso.codigo} paso={paso} ultimo={indice === pasos.length - 1} />
        ))}
      </View>
    </View>
  );
}

function Paso({ paso, ultimo }: { paso: PasoALaVista; ultimo: boolean }) {
  const { codigo, nombre, situacion, hora } = paso;
  const detalle = situacion === 'pendiente' ? TEXTOS_DEL_SEGUIMIENTO.pendiente : hora;
  return (
    <View testID={`paso-${codigo}`} accessible accessibilityLabel={pasoParaLeer(paso)} style={estilos.paso}>
      <View style={estilos.riel}>
        <Marca codigo={codigo} situacion={situacion} />
        {ultimo ? null : <View style={[estilos.linea, situacion === 'cumplido' ? estilos.lineaCumplida : null]} />}
      </View>
      <View style={[estilos.textoDelPaso, ultimo ? null : estilos.separacion]}>
        <Text style={[estilos.nombre, estiloDelNombre(situacion)]}>{nombre}</Text>
        {detalle ? <Text style={[estilos.detalle, letra('cuerpo', 'regular')]}>{detalle}</Text> : null}
      </View>
    </View>
  );
}

// La marca de cada estado en el riel: llena con la palomita, con el color del estado o vacía.
function Marca({ codigo, situacion }: Pick<PasoALaVista, 'codigo' | 'situacion'>) {
  if (situacion === 'cumplido') {
    return (
      <View style={[estilos.marca, estilos.marcaCumplida]}>
        <Text
          style={[estilos.palomita, letra('cuerpo', 'fuerte')]}
          accessibilityElementsHidden
          importantForAccessibility="no-hide-descendants"
        >
          ✓
        </Text>
      </View>
    );
  }
  if (situacion === 'actual') {
    const tono = colorDelEstado(codigo);
    return (
      <View style={[estilos.marca, { borderColor: tono }]}>
        <View style={[estilos.punto, { backgroundColor: tono }]} />
      </View>
    );
  }
  return <View style={estilos.marca} />;
}

// El nombre de cada estado: el actual en fuerte, los cumplidos en semifuerte y los pendientes en texto suave. La letra se
// pide al dibujarse, cuando ya cargó (src/tema/letra.ts).
function estiloDelNombre(situacion: Situacion) {
  switch (situacion) {
    case 'cumplido':
      return [estilos.nombreCumplido, letra('cuerpo', 'semifuerte')];
    case 'actual':
      return [estilos.nombreActual, letra('cuerpo', 'fuerte')];
    case 'pendiente':
      return [estilos.nombrePendiente, letra('cuerpo', 'regular')];
  }
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
  cabecera: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  identidad: {
    flex: 1,
    gap: espaciado[1],
  },
  folio: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  lavanderia: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  punto: {
    width: espaciado[2],
    height: espaciado[2],
    borderRadius: radio.pildora,
  },
  paso: {
    flexDirection: 'row',
    gap: espaciado[3],
  },
  riel: {
    width: espaciado[5],
    alignItems: 'center',
  },
  marca: {
    width: espaciado[5],
    height: espaciado[5],
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: grosor.foco,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.superficie,
  },
  marcaCumplida: {
    borderColor: color.estado.exito,
    backgroundColor: color.estado.exito,
  },
  palomita: {
    color: color.semantico.texto_inverso,
    fontSize: tipografia.escala.micro,
    lineHeight: tipografia.escala.micro * tipografia.interlineado.apretado,
  },
  linea: {
    flexGrow: 1,
    width: grosor.foco,
    minHeight: espaciado[4],
    marginVertical: espaciado[1],
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.borde,
  },
  lineaCumplida: {
    backgroundColor: color.estado.exito,
  },
  textoDelPaso: {
    flex: 1,
    gap: espaciado[1],
  },
  separacion: {
    paddingBottom: espaciado[4],
  },
  nombre: {
    fontSize: tipografia.escala.cuerpo_grande,
    lineHeight: tipografia.escala.cuerpo_grande * tipografia.interlineado.apretado,
  },
  nombreCumplido: {
    color: color.semantico.texto,
  },
  nombreActual: {
    color: color.semantico.texto,
  },
  nombrePendiente: {
    color: color.semantico.texto_suave,
  },
  detalle: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    fontVariant: ['tabular-nums'],
  },
});
