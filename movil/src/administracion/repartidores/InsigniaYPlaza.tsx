// El estado y la plaza de un repartidor, en un renglón: la insignia cuadrada con el nombre del estado y, a su lado, la
// plaza de su alta o "Sin plaza" (administracion-app-E19), como en la lista y en la ficha de las imágenes 14 a 19 de la
// Propuesta 06. Es la insignia de la app (src/piezas/InsigniaDeEstado.tsx) en su forma cuadrada, la de los estados
// operativos de un módulo, con el color de cada estado de los tokens: pendiente en atención, activo en éxito, suspendido
// en error y de baja en el texto suave, como el panel (RepartidoresTable). El nombre siempre va escrito: el color nunca
// va solo.
import { StyleSheet, Text, View } from 'react-native';

import { InsigniaDeEstado } from '../../piezas/InsigniaDeEstado';
import { letra } from '../../tema/letra';
import { color, espaciado, tipografia } from '../../tema/theme';
import type { EstadoDelRepartidor, RepartidorEnLaLista } from './api';
import { TEXTOS_DE_LOS_REPARTIDORES as TEXTOS } from './textos';

const TONO: Record<EstadoDelRepartidor, string> = {
  pendiente: color.estado.atencion,
  activo: color.estado.exito,
  suspendido: color.estado.error,
  baja: color.semantico.texto_suave,
};

/** "Pendiente, Sin plaza": el estado y la plaza, como los lee el lector de pantalla. */
export function estadoYPlazaEnTexto({ estado, plaza }: Pick<RepartidorEnLaLista, 'estado' | 'plaza'>): string {
  return `${TEXTOS.estados[estado]}, ${plaza?.nombre ?? TEXTOS.sinPlaza}`;
}

export function InsigniaYPlaza({ estado, plaza }: Pick<RepartidorEnLaLista, 'estado' | 'plaza'>) {
  return (
    <View style={estilos.renglon}>
      <InsigniaDeEstado codigo={estado} nombre={TEXTOS.estados[estado]} forma="cuadrada" tono={TONO[estado]} />
      <Text style={[estilos.plaza, letra('cuerpo', 'regular')]}>{plaza?.nombre ?? TEXTOS.sinPlaza}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  renglon: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: espaciado[3],
  },
  plaza: {
    flexShrink: 1,
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
