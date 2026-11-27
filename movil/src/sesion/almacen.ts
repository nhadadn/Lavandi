// Dónde vive la sesión en el teléfono: el almacenamiento cifrado del sistema, Keychain en iPhone y Keystore en Android,
// con expo-secure-store (ADR-0018, decisión 1). Guarda el token y el rol de la cuenta, que dice a qué módulo entra
// (registro-E33): con él, la app sabe a dónde llevar a la persona aunque al abrirla no llegue al servidor
// (registro-E11).
import * as SecureStore from 'expo-secure-store';

const CLAVE_DEL_TOKEN = 'sesion.token';
const CLAVE_DEL_ROL = 'sesion.rol';

/** El token guardado, o null si no hay sesión. */
export function leerToken(): Promise<string | null> {
  return SecureStore.getItemAsync(CLAVE_DEL_TOKEN);
}

/** Guarda el token de una sesión nueva. */
export async function guardarToken(token: string): Promise<void> {
  await SecureStore.setItemAsync(CLAVE_DEL_TOKEN, token);
}

/** Borra el token del teléfono: al cerrar la sesión o si el servidor ya no lo reconoce. */
export async function borrarToken(): Promise<void> {
  await SecureStore.deleteItemAsync(CLAVE_DEL_TOKEN);
}

/** El rol de la cuenta de la sesión guardada, como lo da el servidor ("usuario", "lavanderia"), o null si no hay. */
export function leerRol(): Promise<string | null> {
  return SecureStore.getItemAsync(CLAVE_DEL_ROL);
}

/** Guarda el rol de la cuenta de la sesión. */
export async function guardarRol(rol: string): Promise<void> {
  await SecureStore.setItemAsync(CLAVE_DEL_ROL, rol);
}

/** Borra el rol del teléfono, junto con el token. */
export async function borrarRol(): Promise<void> {
  await SecureStore.deleteItemAsync(CLAVE_DEL_ROL);
}
