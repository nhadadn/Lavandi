// La tarjeta del rango de un reporte de la administración, plegada arriba de las cifras (design.md, sección 4; imágenes
// 31 y 41 de la Propuesta 06): el rango que usó el servidor, como lo lee ("Del 26/10/2026 al 29/11/2026, por semana",
// el texto de App\Indicadores\Rango::descripcion()), y a un lado "Cambiar", que abre la hoja del rango
// (HojaDelRango.tsx). Debajo del rango puede ir una nota, como la del refresco de los indicadores (tarea 18.4). No es uno
// de los seis componentes del design system: se arma con las piezas y los tokens (ADR-0013).
import { StyleSheet, Text, View } from 'react-native';

import { Enlace } from '../../piezas/Enlace';
import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import { TEXTOS_DEL_RANGO } from './textos';

interface Propiedades {
  /** El rango que usó el servidor, como lo lee. */
  descripcion: string;
  /** Una línea debajo del rango, en el tono suave. */
  nota?: string;
  alCambiar: () => void;
}

export function TarjetaDelRango({ descripcion, nota, alCambiar }: Propiedades) {
  return (
    <View style={estilos.tarjeta} testID="tarjeta-del-rango">
      <View style={estilos.textos}>
        <Text style={[estilos.descripcion, letra('cuerpo', 'fuerte')]}>{descripcion}</Text>
        {nota ? <Text style={[estilos.nota, letra('cuerpo', 'regular')]}>{nota}</Text> : null}
      </View>
      <Enlace texto={TEXTOS_DEL_RANGO.cambiar} alTocar={alCambiar} />
    </View>
  );
}

const estilos = StyleSheet.create({
  // Como la tarjeta de las cifras (src/piezas/TarjetaDeCifras.tsx).
  tarjeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[3],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  textos: {
    flex: 1,
    gap: espaciado[1],
  },
  descripcion: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
  },
  nota: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
