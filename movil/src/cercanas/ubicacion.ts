// La ubicación del teléfono solo mientras la app está abierta (cercanas-E03): se lee y se pide el permiso de primer
// plano, nunca el de segundo plano, que la configuración tampoco declara (app.config.ts). El texto de para qué se usa
// es el de permiso-de-ubicacion.json, el mismo que lleva el diálogo de iPhone.
import * as Location from 'expo-location';

import type { Punto } from './api';

/**
 * concedido: se puede leer la ubicación; por-pedir: se puede pedir el permiso; sin-permiso: la persona lo negó y el
 * sistema ya no deja pedirlo desde la app, o lo acaba de negar (cercanas-E05, bloqueado por DD-083).
 */
export type Permiso = 'concedido' | 'por-pedir' | 'sin-permiso';

/** El permiso como está, sin pedirlo. */
export async function leerPermiso(): Promise<Permiso> {
  const respuesta = await Location.getForegroundPermissionsAsync();
  if (respuesta.granted) {
    return 'concedido';
  }
  return respuesta.canAskAgain ? 'por-pedir' : 'sin-permiso';
}

/** Pide el permiso de uso mientras la app está abierta. Si la persona no lo da, queda sin permiso. */
export async function pedirPermiso(): Promise<Permiso> {
  const respuesta = await Location.requestForegroundPermissionsAsync();
  return respuesta.granted ? 'concedido' : 'sin-permiso';
}

/**
 * Dónde está el teléfono ahora. La precisión equilibrada basta para buscar lavanderías en línea recta (ADR-0009). En
 * Android esa precisión sale de la red; sin red (o en el emulador, que solo simula el GPS) no llega, y entonces se pide
 * con precisión alta, que usa el GPS. Si tampoco llega, sirve la última ubicación que el teléfono recuerda. Sin
 * ninguna, lanza: la pantalla lo dice (cercanas-E04).
 */
export async function ubicacionActual(): Promise<Punto> {
  for (const accuracy of [Location.Accuracy.Balanced, Location.Accuracy.High]) {
    try {
      const { coords } = await Location.getCurrentPositionAsync({ accuracy });
      return { latitud: coords.latitude, longitud: coords.longitude };
    } catch {
      // Se intenta con la siguiente precisión.
    }
  }
  const ultima = await Location.getLastKnownPositionAsync();
  if (!ultima) {
    throw new Error('Sin ubicación');
  }
  return { latitud: ultima.coords.latitude, longitud: ultima.coords.longitude };
}
