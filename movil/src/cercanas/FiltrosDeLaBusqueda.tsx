// Los filtros de la búsqueda (cercanas-E21): distancia máxima y calificación mínima, con los cortes de la configuración
// (src/configuracion/filtros.ts, valores de DD-083) y no escritos aquí. Un corte por filtro; los dos se combinan, y
// volver a tocar el elegido lo quita. La composición es la del mock A04: la etiqueta de cada filtro y sus cortes en una
// fila que se parte. Del mock no entran el filtro de disponibilidad (cercanas-E19, DD-083 y DD-078), la búsqueda por
// nombre ni el orden (DD-136, punto 3). Colores y medidas de theme.ts (ADR-0013).
import { StyleSheet, Text, View } from 'react-native';

import { FILTROS } from '../configuracion/filtros';
import { color, espaciado, tipografia } from '../tema/theme';
import { OpcionDeFiltro } from './OpcionDeFiltro';
import { corteDeCalificacion, corteDeDistancia, TEXTOS } from './textos';

interface Propiedades {
  distanciaMaximaMetros: number | null;
  calificacionMinima: number | null;
  alCambiarDistancia: (metros: number | null) => void;
  alCambiarCalificacion: (estrellas: number | null) => void;
}

export function FiltrosDeLaBusqueda({
  distanciaMaximaMetros,
  calificacionMinima,
  alCambiarDistancia,
  alCambiarCalificacion,
}: Propiedades) {
  return (
    <View style={estilos.filtros} testID="filtros">
      <Filtro
        etiqueta={TEXTOS.filtroDeDistancia}
        cortes={FILTROS.distanciasMaximasMetros}
        elegido={distanciaMaximaMetros}
        enTexto={corteDeDistancia}
        alCambiar={alCambiarDistancia}
      />
      <Filtro
        etiqueta={TEXTOS.filtroDeCalificacion}
        cortes={FILTROS.calificacionesMinimas}
        elegido={calificacionMinima}
        enTexto={corteDeCalificacion}
        alCambiar={alCambiarCalificacion}
      />
    </View>
  );
}

interface PropiedadesDelFiltro {
  etiqueta: string;
  cortes: readonly number[];
  elegido: number | null;
  enTexto: (corte: number) => string;
  alCambiar: (corte: number | null) => void;
}

function Filtro({ etiqueta, cortes, elegido, enTexto, alCambiar }: PropiedadesDelFiltro) {
  return (
    <View style={estilos.filtro}>
      <Text style={estilos.etiqueta}>{etiqueta}</Text>
      <View style={estilos.cortes}>
        {cortes.map((corte) => (
          <OpcionDeFiltro
            key={corte}
            texto={enTexto(corte)}
            elegida={corte === elegido}
            alTocar={() => alCambiar(corte === elegido ? null : corte)}
          />
        ))}
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  filtros: {
    gap: espaciado[4],
  },
  filtro: {
    gap: espaciado[2],
  },
  etiqueta: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    fontWeight: tipografia.peso.semifuerte,
  },
  cortes: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: espaciado[2],
  },
});
