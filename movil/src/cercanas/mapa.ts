// Si la app puede montar el mapa nativo (ADR-0009). En iPhone, el de Apple no lleva llave. En Android, el SDK de Google
// Maps cierra la app al crear el mapa si el manifiesto no trae la llave: app.config.ts deja en extra si la compilación
// la trae (la llave sale del entorno, ADR-0012), y sin ella la pantalla sigue, sin el mapa.
import Constants from 'expo-constants';
import { Platform } from 'react-native';

export function hayMapaNativo(): boolean {
  if (Platform.OS !== 'android') {
    return true;
  }
  return Constants.expoConfig?.extra?.mapaDeGoogleEnAndroid === true;
}
