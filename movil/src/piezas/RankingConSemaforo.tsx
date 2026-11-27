// El ranking con semáforo (ADR-0022, alternativa 5): la calificación promedio de cada lavandería, de la mayor a la menor,
// con el color del semáforo según los cortes de la configuración (administracion-app-E48; indicadores-E12 y E13), con la
// forma de la maqueta de la Propuesta 06 de la administración (46 y 47). Los cortes de EL CLIENTE esperan DD-114.
//
// - Cada lavandería en una fila, con una línea arriba: su lugar y su nombre ("1. Lavandería…"), su calificación debajo
//   y, a la derecha, el semáforo en la insignia cuadrada de InsigniaDeEstado, con su punto y su texto en el color de su
//   estado: cada color con su texto (design system, reglas transversales).
// - La que no tiene calificaciones va sin lugar ni semáforo y lo dice; sin cortes, la calificación va sin semáforo, como
//   en el panel (RankingDeLavanderiasTabla).
// - Cada fila se anuncia en un solo anuncio: "1. Lavandería…. 4.8 de 5. Semáforo: Verde".
//
// No trae cifras propias: el lugar, la calificación ya escrita y el texto del semáforo llegan por propiedades, del
// servidor. No es uno de los seis componentes del design system: se arma con los tokens (ADR-0013).
import { StyleSheet, Text, View } from 'react-native';

import { InsigniaDeEstado } from './InsigniaDeEstado';
import { letra } from '../tema/letra';
import { color, espaciado, grosor, tipografia } from '../tema/theme';

/** El color del semáforo, como App\Indicadores\Semaforo. */
export type ColorDelSemaforo = 'verde' | 'amarillo' | 'rojo';

/** Una lavandería del ranking. Sin calificaciones, sin lugar, sin calificación y sin semáforo. */
export interface LugarDelRanking {
  clave: string;
  lugar: number | null;
  nombre: string;
  /** La calificación, ya escrita: "4.8 de 5". */
  calificacion: string | null;
  /** El color y su texto ("Verde"); sin cortes del semáforo, nada. */
  semaforo: { color: ColorDelSemaforo; texto: string } | null;
}

interface Propiedades {
  lugares: readonly LugarDelRanking[];
  testID?: string;
}

const SIN_CALIFICACIONES = 'Sin calificaciones';

/** El color de cada estado del semáforo: los de estado del tema, como success, warning y danger del panel. */
const TONOS: Record<ColorDelSemaforo, string> = {
  verde: color.estado.exito,
  amarillo: color.estado.atencion,
  rojo: color.estado.error,
};

export function RankingConSemaforo({ lugares, testID = 'ranking-con-semaforo' }: Propiedades) {
  return (
    <View testID={testID}>
      {lugares.map((lugar) => {
        const nombre = lugar.lugar === null ? lugar.nombre : `${lugar.lugar}. ${lugar.nombre}`;
        const calificacion = lugar.calificacion ?? SIN_CALIFICACIONES;
        const anuncio = [nombre, calificacion, ...(lugar.semaforo ? [`Semáforo: ${lugar.semaforo.texto}`] : [])];
        return (
          <View
            key={lugar.clave}
            accessible
            accessibilityLabel={anuncio.join('. ')}
            testID={`lugar-${lugar.clave}`}
            style={estilos.fila}
          >
            <Text style={[estilos.nombre, letra('cuerpo', 'semifuerte')]}>{nombre}</Text>
            <View style={estilos.renglon}>
              <Text style={[estilos.calificacion, letra('cuerpo', 'regular')]}>{calificacion}</Text>
              {lugar.semaforo ? (
                <InsigniaDeEstado
                  codigo={lugar.semaforo.color}
                  nombre={lugar.semaforo.texto}
                  forma="cuadrada"
                  tono={TONOS[lugar.semaforo.color]}
                  testID={`semaforo-${lugar.clave}`}
                />
              ) : null}
            </View>
          </View>
        );
      })}
    </View>
  );
}

// Las medidas de la maqueta (46): ocho arriba y abajo de cada fila y una línea arriba de cada una, también de la primera,
// que la separa del título de la tarjeta.
const estilos = StyleSheet.create({
  fila: {
    gap: espaciado[1],
    paddingVertical: espaciado[2],
    borderTopWidth: grosor.control,
    borderTopColor: color.semantico.borde,
  },
  nombre: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  renglon: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  // Los números tabulares, para que las cifras no bailen (design system, reglas transversales).
  calificacion: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
    fontVariant: ['tabular-nums'],
  },
});
