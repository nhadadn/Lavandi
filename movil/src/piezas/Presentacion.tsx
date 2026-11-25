// El título grande de una pantalla con su texto debajo, juntos como en las imágenes de la Propuesta 04 (C-230): entre
// los dos hay menos espacio que entre las demás partes de la pantalla.
import { StyleSheet, View } from 'react-native';

import { espaciado } from '../tema/theme';
import { Parrafo } from './Parrafo';
import { Titulo, type Alineacion } from './Titulo';

interface Propiedades {
  titulo: string;
  /** El subtítulo, el lema o el texto de una informativa. */
  texto?: string;
  tamano?: 'mayor' | 'pantalla';
  alineacion?: Alineacion;
}

export function Presentacion({ titulo, texto, tamano, alineacion }: Propiedades) {
  return (
    <View style={estilos.grupo} testID="presentacion">
      <Titulo tamano={tamano} alineacion={alineacion}>
        {titulo}
      </Titulo>
      {texto ? <Parrafo alineacion={alineacion}>{texto}</Parrafo> : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  grupo: {
    gap: espaciado[2],
  },
});
