// Dónde guarda la app que la persona ya vio la bienvenida y las tres informativas (bienvenida-E02, C-225): en el mismo
// almacenamiento del teléfono que la sesión, expo-secure-store. En iPhone sobrevive a desinstalar la app; por eso la
// marca de instalación la borra al reinstalar (registro-E32). Las pantallas no lo usan directo: usan ./vista.ts.
import * as SecureStore from 'expo-secure-store';

const CLAVE = 'bienvenida.vista';
const VISTA = 'si';

export async function leerMarcaDeBienvenida(): Promise<boolean> {
  return (await SecureStore.getItemAsync(CLAVE)) === VISTA;
}

export async function guardarMarcaDeBienvenida(): Promise<void> {
  await SecureStore.setItemAsync(CLAVE, VISTA);
}

export async function borrarMarcaDeBienvenida(): Promise<void> {
  await SecureStore.deleteItemAsync(CLAVE);
}
