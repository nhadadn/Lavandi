// La mensajería de Firebase en la app (ADR-0008 y su adenda): @react-native-firebase/messaging, en el development build.
// Es el único lugar que la carga.
//
// La librería es un módulo nativo que no siempre está: Expo Go no lo trae (base-E11, C-240), y la compilación solo lo
// enlaza si trae el archivo de Firebase del entorno (firebase-nativo.js), que no existe mientras falten las llaves
// (DD-072). Sin el módulo, o sin el archivo, pedirle algo cierra la app. Por eso no se importa arriba: se pide con
// mensajeria() y solo cuando avisosDisponibles() dice que sí. Los tipos sí se importan: no llegan a la app.
import type * as Libreria from '@react-native-firebase/messaging';
import Constants from 'expo-constants';
import { Platform } from 'react-native';

import { enExpoGo } from '../configuracion/entorno';

/**
 * Si la app tiene con qué recibir avisos: fuera de Expo Go, en Android y con el archivo de Firebase en la compilación
 * (app.config.ts lo deja en extra). En iPhone todavía no: su configuración es avisos-E18 (tarea 5.2: DD-069, DD-017 y
 * DD-084). Sin avisos, la app sigue igual y el seguimiento se pone al día con la consulta (ADR-0007).
 */
export function avisosDisponibles(): boolean {
  if (enExpoGo() || Platform.OS !== 'android') {
    return false;
  }
  return Constants.expoConfig?.extra?.firebaseEnAndroid === true;
}

/** La librería, que se carga la primera vez que se pide. Solo se llama con avisosDisponibles(). */
export function mensajeria(): typeof Libreria {
  // eslint-disable-next-line @typescript-eslint/no-require-imports -- carga diferida: sin el módulo nativo no se carga
  return require('@react-native-firebase/messaging') as typeof Libreria;
}
