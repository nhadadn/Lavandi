// Una ilustración decorativa: ocupa el espacio libre de la pantalla sin deformarse, y los lectores de pantalla la
// saltan porque el texto de al lado ya dice lo que importa.
import { Image, StyleSheet, type ImageSourcePropType } from 'react-native';

export function Ilustracion({ fuente }: { fuente: ImageSourcePropType }) {
  return (
    <Image
      source={fuente}
      resizeMode="contain"
      style={estilos.ilustracion}
      testID="ilustracion"
      accessible={false}
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
    />
  );
}

const estilos = StyleSheet.create({
  ilustracion: {
    flex: 1,
    width: '100%',
  },
});
