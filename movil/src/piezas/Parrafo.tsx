// Un párrafo en el tono suave del texto, como el .suave de los mocks: el subtítulo de las pantallas y el texto de la
// bienvenida y de las informativas. Con el tamaño del cuerpo, como en las imágenes de la Propuesta 04 (C-230).
import { StyleSheet, Text } from 'react-native';

import { letra } from '../tema/letra';
import { color, tipografia } from '../tema/theme';
import type { Alineacion } from './Titulo';

interface Propiedades {
  children: string;
  alineacion?: Alineacion;
}

export function Parrafo({ children, alineacion = 'centro' }: Propiedades) {
  return (
    <Text style={[estilos.parrafo, letra('cuerpo', 'regular'), alineacion === 'inicio' ? estilos.inicio : estilos.centro]}>
      {children}
    </Text>
  );
}

const estilos = StyleSheet.create({
  parrafo: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
    lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
  },
  centro: {
    textAlign: 'center',
  },
  inicio: {
    textAlign: 'left',
  },
});
