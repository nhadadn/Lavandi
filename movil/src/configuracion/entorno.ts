// La configuración de la app que llega del entorno (.env en local, variables de EAS al compilar).
// Expo incrusta las variables EXPO_PUBLIC_* al armar la app y solo las reconoce con acceso directo,
// como process.env.EXPO_PUBLIC_API_URL; un acceso dinámico como process.env[nombre] no las encuentra.
import Constants, { ExecutionEnvironment } from 'expo-constants';

/**
 * Si la app corre en Expo Go, para probarla en un iPhone sin cuenta de Apple (base-E11, C-240). Expo Go no trae todos
 * los módulos nativos de la app, como el de la entrada con Google: lo que dependa de ellos se oculta ahí y no se carga.
 * Solo Expo Go dice "storeClient": en el development build y en la app de las tiendas, expo-constants dice "bare"
 * (EXConstantsService.m y ConstantsService.kt) y expo-dev-client no lo cambia. Es el único lugar que lo pregunta.
 */
export function enExpoGo(): boolean {
  return Constants.executionEnvironment === ExecutionEnvironment.StoreClient;
}

function requerida(nombre: string, valor: string | undefined): string {
  const limpio = valor?.trim();
  if (!limpio) {
    throw new Error(`Falta la variable de entorno ${nombre}.`);
  }
  return limpio;
}

/** El nombre de la app que se muestra en pantalla. */
export function nombreDeLaApp(): string {
  return requerida('EXPO_PUBLIC_NOMBRE_APP', process.env.EXPO_PUBLIC_NOMBRE_APP);
}

/** La dirección base de la API del servidor. */
export function direccionDeLaApi(): string {
  return requerida('EXPO_PUBLIC_API_URL', process.env.EXPO_PUBLIC_API_URL);
}

// Las opcionales: sin ellas la app arranca y no ofrece lo que dependa de ellas.
function opcional(valor: string | undefined): string | undefined {
  const limpio = valor?.trim();
  return limpio ? limpio : undefined;
}

// El plazo por omisión. Lo confirma Arthur.
const PLAZO_DE_LA_API_POR_OMISION_MS = 15_000;

/**
 * Cuántos milisegundos espera la app la respuesta del servidor antes de cancelar la petición y tratarla como sin
 * conexión (registro-E11 y registro-E13). Es opcional: sin él, 15000. Si trae algo que no es un número entero de
 * milisegundos mayor que cero, falla en vez de inventar un plazo.
 */
export function plazoDeLaApi(): number {
  const valor = opcional(process.env.EXPO_PUBLIC_API_PLAZO_MS);
  if (valor === undefined) {
    return PLAZO_DE_LA_API_POR_OMISION_MS;
  }
  const plazo = /^\d+$/.test(valor) ? Number(valor) : NaN;
  if (!Number.isSafeInteger(plazo) || plazo <= 0) {
    throw new Error(
      'La variable de entorno EXPO_PUBLIC_API_PLAZO_MS no es un número entero de milisegundos mayor que 0.',
    );
  }
  return plazo;
}

/**
 * El ID del cliente OAuth web de Google (ADR-0005 y ADR-0012). Es la audiencia del idToken que valida el servidor:
 * tiene que estar en su lista de clientes. Sin él, las pantallas de entrada no ofrecen Google.
 */
export function clienteWebDeGoogle(): string | undefined {
  return opcional(process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID);
}

/** El ID del cliente OAuth de iPhone de Google. Sin él, Google no funciona en iPhone (app.config.ts). */
export function clienteIosDeGoogle(): string | undefined {
  return opcional(process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID);
}

/** La dirección del aviso de privacidad que entregue EL CLIENTE (registro-E28, DD-080). */
export function direccionDelAvisoDePrivacidad(): string | undefined {
  return opcional(process.env.EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL);
}

/** La dirección de los términos y condiciones que entregue EL CLIENTE (registro-E28, DD-080). */
export function direccionDeLosTerminos(): string | undefined {
  return opcional(process.env.EXPO_PUBLIC_TERMINOS_URL);
}
