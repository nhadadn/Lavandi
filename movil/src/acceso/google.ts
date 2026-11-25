// Google en la app con react-native-nitro-google-signin (ADR-0005): pide a Google el idToken, que después valida el
// servidor (registro-E16 y E19). Si la librería cambia (opción B de ADR-0005), solo cambia este archivo.
//
// La librería es un módulo nativo de Nitro que Expo Go no trae: al cargarse crea su objeto nativo, y en Expo Go eso
// cierra la app. Por eso no se importa arriba: se pide dentro de cada función y solo fuera de Expo Go. En Expo Go, Google
// no se ofrece y la app abre con el correo y la contraseña (base-E11, C-240). Los tipos sí se importan: no llegan a la app.
import type * as Libreria from 'react-native-nitro-google-signin';

import { clienteIosDeGoogle, clienteWebDeGoogle, enExpoGo } from '../configuracion/entorno';

export type ResultadoDeGoogle =
  | { tipo: 'exito'; idToken: string }
  /** La persona cerró la ventana de Google sin elegir cuenta: no es un error (registro-E19). */
  | { tipo: 'cancelado' }
  | { tipo: 'error'; codigo?: string };

// La librería, que se carga la primera vez que se pide. Nunca se llama en Expo Go.
function libreria(): typeof Libreria {
  // eslint-disable-next-line @typescript-eslint/no-require-imports -- carga diferida: en Expo Go no se debe cargar
  return require('react-native-nitro-google-signin') as typeof Libreria;
}

/** Si la app tiene con qué ofrecer Google: fuera de Expo Go (base-E11) y con el cliente web del entorno. */
export function googleDisponible(): boolean {
  return !enExpoGo() && clienteWebDeGoogle() !== undefined;
}

/** Abre la ventana de Google y devuelve el idToken de la cuenta elegida. No lanza. En Expo Go, error sin cargar nada. */
export async function pedirIdTokenDeGoogle(): Promise<ResultadoDeGoogle> {
  const webClientId = clienteWebDeGoogle();
  if (enExpoGo() || !webClientId) {
    return { tipo: 'error' };
  }

  let google: typeof Libreria | undefined;
  try {
    google = libreria();
    const { GoogleOneTapSignIn, isCancelledResponse, isNoSavedCredentialFoundResponse, isSuccessResponse } = google;
    GoogleOneTapSignIn.configure({ webClientId, iosClientId: clienteIosDeGoogle() ?? null });
    await GoogleOneTapSignIn.checkPlayServices();

    // El flujo de la librería: primero la cuenta ya autorizada; si no hay, todas las del teléfono; si tampoco,
    // la ventana explícita de "Iniciar sesión con Google".
    let respuesta: Libreria.OneTapResponse = await GoogleOneTapSignIn.signIn();
    if (isNoSavedCredentialFoundResponse(respuesta)) {
      respuesta = await GoogleOneTapSignIn.createAccount();
    }
    if (isNoSavedCredentialFoundResponse(respuesta)) {
      respuesta = await GoogleOneTapSignIn.presentExplicitSignIn();
    }

    if (isSuccessResponse(respuesta)) {
      return { tipo: 'exito', idToken: respuesta.data.idToken };
    }
    if (isCancelledResponse(respuesta)) {
      return { tipo: 'cancelado' };
    }
    return { tipo: 'error' };
  } catch (error) {
    // Si la librería ni siquiera cargó, no hay con qué leer el error.
    if (google?.isErrorWithCode(error)) {
      return error.code === google.statusCodes.SIGN_IN_CANCELLED
        ? { tipo: 'cancelado' }
        : { tipo: 'error', codigo: error.code };
    }
    return { tipo: 'error' };
  }
}

/**
 * Cierra la sesión de Google en el teléfono al cerrar la de la app (registro-E13). Sin esto, en iPhone signIn() vuelve
 * a la misma cuenta sin preguntar, y la siguiente entrada con Google no deja elegir otra (registro-E19). Nunca falla: si
 * la librería falla, el cierre de la sesión de la app sigue igual. En Expo Go no hace nada: ahí no hubo Google (base-E11).
 */
export async function cerrarSesionDeGoogle(): Promise<void> {
  if (enExpoGo()) {
    return;
  }
  try {
    await libreria().GoogleOneTapSignIn.signOut();
  } catch {
    // Google no pudo cerrar su sesión, o la librería no cargó: la de la app ya quedó cerrada.
  }
}
