// El logotipo arriba y al centro, como en la imagen a02-inicio-sesion de la Propuesta 04 (C-230), del tamaño del mock.
// La imagen es la del archivo de nombre neutro (../contenido/logotipo.ts, C-237) y su texto alternativo es el nombre de
// la app que da la configuración (base-E09).
import { Image, StyleSheet } from 'react-native';

import { nombreDeLaApp } from '../configuracion/entorno';
import { LOGOTIPO } from '../contenido/logotipo';
import { espaciado } from '../tema/theme';

export function Logotipo() {
  return (
    <Image
      source={LOGOTIPO}
      resizeMode="contain"
      accessible
      accessibilityRole="image"
      accessibilityLabel={nombreDeLaApp()}
      testID="logotipo"
      style={estilos.logotipo}
    />
  );
}

const estilos = StyleSheet.create({
  logotipo: {
    alignSelf: 'center',
    width: espaciado[8],
    height: espaciado[8],
  },
});
