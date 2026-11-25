// Simulación de @react-native-firebase/messaging para Jest: en las pruebas no hay Firebase ni servicio de avisos
// (ADR-0008). Guarda en memoria el token del teléfono, el permiso de avisos y las funciones que la app deja escuchando,
// y cada prueba los programa con las funciones __ de abajo. Por omisión, como un teléfono con los avisos permitidos y un
// token ya dado por Firebase. Jest la usa en todas las pruebas porque está en __mocks__/ junto a node_modules. Que el
// aviso llegue de verdad a un teléfono se acredita con la captura de vault/evidencias/avisos/ (avisos-E17).
//
// Solo trae la API modular que usa la app (src/avisos/), con los mismos nombres y valores que la librería.

/** Los mismos valores que la librería (lib/statics.ts). */
export const AuthorizationStatus = {
  NOT_DETERMINED: -1,
  DENIED: 0,
  AUTHORIZED: 1,
  PROVISIONAL: 2,
  EPHEMERAL: 3,
} as const;

type Estado = (typeof AuthorizationStatus)[keyof typeof AuthorizationStatus];

/** Lo que la app lee de un mensaje de FCM: sus datos. */
interface MensajeRemoto {
  data?: Record<string, string | object>;
  notification?: { title?: string; body?: string };
}

type Escucha<T> = (valor: T) => unknown;

/** La instancia de la mensajería: un objeto cualquiera, siempre el mismo, como la de la librería. */
const MENSAJERIA = { nombre: 'mensajeria-de-prueba' };
const TOKEN_POR_OMISION = 'token-fcm-de-prueba';

let token = TOKEN_POR_OMISION;
let permiso: Estado = AuthorizationStatus.AUTHORIZED;
let alPedir: Estado = AuthorizationStatus.AUTHORIZED;
const renovaciones = new Set<Escucha<string>>();
const mensajes = new Set<Escucha<MensajeRemoto>>();

export const getMessaging = jest.fn(() => MENSAJERIA);

export const getToken = jest.fn(async (_mensajeria: unknown): Promise<string> => token);

export const onTokenRefresh = jest.fn((_mensajeria: unknown, escucha: Escucha<string>) => {
  renovaciones.add(escucha);
  return () => {
    renovaciones.delete(escucha);
  };
});

export const onMessage = jest.fn((_mensajeria: unknown, escucha: Escucha<MensajeRemoto>) => {
  mensajes.add(escucha);
  return () => {
    mensajes.delete(escucha);
  };
});

export const setBackgroundMessageHandler = jest.fn(
  (_mensajeria: unknown, _manejador: (mensaje: MensajeRemoto) => Promise<unknown>) => undefined,
);

export const hasPermission = jest.fn(async (_mensajeria: unknown): Promise<Estado> => permiso);

// En iPhone, la petición del permiso con el diálogo del sistema. En Android la librería contesta que sí sin preguntar.
export const requestPermission = jest.fn(async (_mensajeria: unknown): Promise<Estado> => {
  permiso = alPedir;
  return permiso;
});

/** Solo para las pruebas: el token que Firebase le da al teléfono. */
export function __token(nuevo: string): void {
  token = nuevo;
}

/** Solo para las pruebas: Firebase renueva el token y avisa a quien esté escuchando. */
export function __renovarToken(nuevo: string): void {
  token = nuevo;
  for (const escucha of [...renovaciones]) {
    escucha(nuevo);
  }
}

/** Solo para las pruebas: llega un mensaje con la app al frente. */
export function __mensaje(mensaje: MensajeRemoto): void {
  for (const escucha of [...mensajes]) {
    escucha(mensaje);
  }
}

/** Solo para las pruebas: el permiso de avisos que tiene la app en el teléfono. */
export function __permiso(estado: Estado): void {
  permiso = estado;
}

/** Solo para las pruebas: lo que la persona contesta en el diálogo de iPhone. */
export function __respuestaAlPedir(estado: Estado): void {
  alPedir = estado;
}

/** Solo para las pruebas: cuántas funciones dejó escuchando la app. */
export function __escuchas(): { renovaciones: number; mensajes: number } {
  return { renovaciones: renovaciones.size, mensajes: mensajes.size };
}

/** Solo para las pruebas: todo como en un teléfono con los avisos permitidos, sin escuchas y con las llamadas en cero. */
export function __reiniciar(): void {
  token = TOKEN_POR_OMISION;
  permiso = AuthorizationStatus.AUTHORIZED;
  alPedir = AuthorizationStatus.AUTHORIZED;
  renovaciones.clear();
  mensajes.clear();
  for (const funcion of [
    getMessaging,
    getToken,
    onTokenRefresh,
    onMessage,
    setBackgroundMessageHandler,
    hasPermission,
    requestPermission,
  ]) {
    funcion.mockClear();
  }
}
