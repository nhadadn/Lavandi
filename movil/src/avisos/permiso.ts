// El permiso de avisos del teléfono (avisos-E07 y E08; ADR-0008). Android 13 o superior lo pide con
// POST_NOTIFICATIONS, que la configuración declara solo con Firebase (app.config.ts); iPhone lo pide con el diálogo de
// la librería de Firebase. En Android 12 o anterior no hay nada que pedir: los avisos vienen permitidos, y solo se apagan
// en los ajustes del sistema.
//
// Si el usuario lo niega, la app sigue sin errores y el seguimiento se pone al día con la consulta (ADR-0007). Solo se
// usa con los avisos disponibles (src/avisos/firebase.ts).
import * as SecureStore from 'expo-secure-store';
import { PermissionsAndroid, Platform } from 'react-native';

import { mensajeria } from './firebase';

/**
 * concedido: los avisos se muestran; sin-responder: la persona nunca contestó la petición y se puede mostrar;
 * negado: la persona la negó o los apagó, y solo se activan desde los ajustes del sistema.
 */
export type PermisoDeAvisos = 'concedido' | 'sin-responder' | 'negado';

// Android no distingue "nunca respondió" de "lo negó": la app anota que ya mostró la petición. La nota vive en el
// almacenamiento del teléfono, como la marca de la bienvenida, y Android la borra al desinstalar la app.
const CLAVE_DE_LA_PETICION = 'avisos.permiso-pedido';
const PEDIDA = 'si';

/** Si el sistema pide el permiso: Android 13, la API 33, o superior. */
function androidLoPide(): boolean {
  return Platform.OS === 'android' && Number(Platform.Version) >= 33;
}

async function yaSePidio(): Promise<boolean> {
  try {
    return (await SecureStore.getItemAsync(CLAVE_DE_LA_PETICION)) === PEDIDA;
  } catch {
    // Sin poder leer la nota, se cuenta como pedida: la app no vuelve a mostrar la petición sola.
    return true;
  }
}

async function anotarLaPeticion(): Promise<void> {
  try {
    await SecureStore.setItemAsync(CLAVE_DE_LA_PETICION, PEDIDA);
  } catch {
    // Sin la nota, la siguiente apertura vuelve a mostrar la petición; Android deja de mostrarla tras dos negativas.
  }
}

function libreria() {
  const firebase = mensajeria();
  return { firebase, instancia: firebase.getMessaging() };
}

/** El permiso como está, sin pedirlo. */
export async function leerPermisoDeAvisos(): Promise<PermisoDeAvisos> {
  const { firebase, instancia } = libreria();
  const estado = await firebase.hasPermission(instancia);
  const { AUTHORIZED, PROVISIONAL, EPHEMERAL, NOT_DETERMINED } = firebase.AuthorizationStatus;
  if (estado === AUTHORIZED || estado === PROVISIONAL || estado === EPHEMERAL) {
    return 'concedido';
  }
  if (Platform.OS === 'ios') {
    return estado === NOT_DETERMINED ? 'sin-responder' : 'negado';
  }
  // En Android 12 o anterior, unos avisos sin permiso los apagó la persona en los ajustes.
  return androidLoPide() && !(await yaSePidio()) ? 'sin-responder' : 'negado';
}

// Quién quiere saber lo que contestó la persona a la petición, como la sección de notificaciones del perfil: la petición
// puede salir de otro lado, al abrirse la sesión.
const escuchas = new Set<(permiso: PermisoDeAvisos) => void>();

/** Escucha lo que contesta la persona cada vez que se le muestra la petición. Devuelve la función que deja de escuchar. */
export function alResponderLaPeticion(escucha: (permiso: PermisoDeAvisos) => void): () => void {
  escuchas.add(escucha);
  return () => {
    escuchas.delete(escucha);
  };
}

/** Muestra la petición del sistema y devuelve lo que contestó la persona. Donde no hay petición, el permiso como está. */
export async function pedirPermisoDeAvisos(): Promise<PermisoDeAvisos> {
  const permiso = await mostrarLaPeticion();
  for (const escucha of [...escuchas]) {
    escucha(permiso);
  }
  return permiso;
}

async function mostrarLaPeticion(): Promise<PermisoDeAvisos> {
  if (Platform.OS === 'ios') {
    const { firebase, instancia } = libreria();
    const estado = await firebase.requestPermission(instancia);
    const { AUTHORIZED, PROVISIONAL, EPHEMERAL } = firebase.AuthorizationStatus;
    return estado === AUTHORIZED || estado === PROVISIONAL || estado === EPHEMERAL ? 'concedido' : 'negado';
  }
  if (!androidLoPide()) {
    return leerPermisoDeAvisos();
  }
  // La nota va después de la respuesta: si la petición no llegó a mostrarse, la persona sigue sin responder.
  const respuesta = await PermissionsAndroid.request(PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS);
  await anotarLaPeticion();
  return respuesta === PermissionsAndroid.RESULTS.GRANTED ? 'concedido' : 'negado';
}

/**
 * El permiso al abrirse la sesión (avisos-E07): si la persona nunca respondió, le muestra la petición del sistema; si
 * ya respondió, no la vuelve a mostrar.
 */
export async function permisoAlAbrirLaSesion(): Promise<PermisoDeAvisos> {
  const permiso = await leerPermisoDeAvisos();
  return permiso === 'sin-responder' ? pedirPermisoDeAvisos() : permiso;
}
