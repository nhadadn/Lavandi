// Una fila del reporte de la conciliación, en su tarjeta (imágenes 33 y 34 de la Propuesta 06; administracion-app-E38):
// su tipo en la insignia, el pedido con su plaza o "Sin pedido", la orden en la pasarela con la fecha del pago, y los
// montos de la plataforma y de la pasarela, con "—" en el lado que no lo tiene. Los textos son los del servidor y las
// columnas, las del panel (servidor/app/Filament/Pages/Conciliacion.php). El tipo va escrito, no solo en color (design
// system, reglas transversales); su color es el del panel (App\Finanzas\ResultadoDeConciliacion::getColor()): conciliado
// en éxito, el monto que no coincide en atención y las otras dos diferencias en error.
import { StyleSheet, Text, View } from 'react-native';

import { InsigniaDeEstado } from '../../piezas/InsigniaDeEstado';
import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import type { FilaDeLaConciliacion, ResultadoDeLaConciliacion } from './api';
import { ordenYFecha, pedidoDeLaFila, TEXTOS_DE_LA_CONCILIACION as TEXTOS } from './textos';

const TONO: Record<ResultadoDeLaConciliacion, string> = {
  conciliado: color.estado.exito,
  monto_distinto: color.estado.atencion,
  movimiento_sin_pedido: color.estado.error,
  pedido_sin_movimiento: color.estado.error,
};

export function TarjetaDeLaFila({ fila }: { fila: FilaDeLaConciliacion }) {
  return (
    <View style={estilos.tarjeta} testID={`fila-${fila.orden}`}>
      <View style={estilos.insignia}>
        <InsigniaDeEstado
          codigo={fila.resultado}
          nombre={fila.resultado_texto}
          forma="cuadrada"
          tono={TONO[fila.resultado]}
        />
      </View>
      <Text style={[estilos.pedido, letra('cuerpo', 'semifuerte')]}>{pedidoDeLaFila(fila)}</Text>
      <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{ordenYFecha(fila)}</Text>
      <View style={estilos.montos}>
        <Monto etiqueta={TEXTOS.enLaPlataforma} valor={fila.monto_plataforma_texto} />
        <Monto etiqueta={TEXTOS.enLaPasarela} valor={fila.monto_pasarela_texto} />
      </View>
    </View>
  );
}

// Un monto, leído en un solo anuncio: "En la pasarela: $175.00".
function Monto({ etiqueta, valor }: { etiqueta: string; valor: string | null }) {
  const aLaVista = valor ?? TEXTOS.sinMonto;
  return (
    <View accessible accessibilityLabel={`${etiqueta}: ${aLaVista}`} style={estilos.monto}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <Text style={[estilos.valor, letra('cuerpo', 'semifuerte')]}>{aLaVista}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    gap: espaciado[2],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  insignia: {
    alignSelf: 'flex-start',
  },
  pedido: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    lineHeight: tipografia.escala.menor * tipografia.interlineado.normal,
  },
  montos: {
    gap: espaciado[1],
  },
  monto: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  etiqueta: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  // Los números tabulares, para que los montos no bailen (design system, reglas transversales).
  valor: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    fontVariant: ['tabular-nums'],
  },
});
