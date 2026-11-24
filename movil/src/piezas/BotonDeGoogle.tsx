// "Continuar con Google" (registro-E19), el mismo en el registro y en el inicio de sesión, con la "G" de Google a la
// izquierda del texto, como piden las pautas de marca de Google Sign-In (DD-133, punto 3; decisión 24 del acta del
// 2026-11-20). Es la marca de Google, no la del producto.
//
// Las pautas (developers.google.com/identity/branding-guidelines, consultadas el 2026-11-21, grado E, SIN VERIFICAR):
// la "G" oficial a colores, sin cambiarle tamaño ni color, sobre fondo blanco, y un texto que diga la acción. La imagen
// sale de su g-logo.png, reducida sin deformarse a 20, 40 y 60 px de lado: el tamaño de la "G" en los botones de Android
// y de iPhone de su paquete de descarga. Por eso no lleva medidas: la app la muestra al tamaño del archivo. El fondo es
// la superficie del botón secundario, blanca.
//
// La "G" es decorativa: el lector de pantalla anuncia el botón por su texto.
import { Image, type ImageSourcePropType } from 'react-native';

import { Boton } from './Boton';

const LOGOTIPO_DE_GOOGLE: ImageSourcePropType = require('../../assets/terceros/google-g.png');

export function BotonDeGoogle({ onPress }: { onPress: () => void }) {
  return (
    <Boton
      variante="secundario"
      onPress={onPress}
      delante={
        <Image
          source={LOGOTIPO_DE_GOOGLE}
          testID="logotipo-google"
          accessible={false}
          accessibilityElementsHidden
          importantForAccessibility="no"
        />
      }
    >
      Continuar con Google
    </Boton>
  );
}
