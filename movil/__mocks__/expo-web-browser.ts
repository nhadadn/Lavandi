// Simulación de expo-web-browser para Jest: en las pruebas no hay vista de navegador del sistema (ASWebAuthenticationSession
// y SFSafariViewController en iPhone; Custom Tabs de Chrome en Android). Guarda cómo termina la próxima vista que se
// abra, que cada prueba programa con las funciones __ de abajo. Por omisión, la persona la cierra sin terminar: la sesión
// de pago responde "cancel", como en iPhone, y la vista del documento, "opened", como en Android, que no espera a que se
// cierre. Jest la usa en todas las pruebas porque está en __mocks__/ junto a node_modules. La vista real de cada sistema,
// su regreso a la app y su cierre se prueban en un teléfono real (pago-E02 y textos-legales-E05).

// Los mismos valores que la librería.
export enum WebBrowserResultType {
  CANCEL = 'cancel',
  DISMISS = 'dismiss',
  OPENED = 'opened',
  LOCKED = 'locked',
}

/** Cómo termina una sesión de openAuthSessionAsync: con la dirección de regreso, o cerrada sin ella. */
export type ResultadoDeLaSesion = { type: 'success'; url: string } | { type: WebBrowserResultType };

let alTerminarLaSesion: () => Promise<ResultadoDeLaSesion> = async () => ({ type: WebBrowserResultType.CANCEL });

export const openAuthSessionAsync = jest.fn(
  async (_url: string, _regreso?: string | null, _opciones?: Record<string, unknown>): Promise<ResultadoDeLaSesion> =>
    alTerminarLaSesion(),
);

export const openBrowserAsync = jest.fn(
  async (_url: string, _opciones?: Record<string, unknown>): Promise<{ type: WebBrowserResultType }> => ({
    type: WebBrowserResultType.OPENED,
  }),
);

export const dismissBrowser = jest.fn(async () => ({ type: WebBrowserResultType.DISMISS }));
export const dismissAuthSession = jest.fn();
export const maybeCompleteAuthSession = jest.fn(() => ({ type: 'failed', message: 'Not supported on this platform' }));

/**
 * Solo para las pruebas: cómo termina la próxima sesión de pago. Con una función, la prueba decide cuándo: por ejemplo,
 * una promesa que suelta cuando quiere, o un error si la vista no se pudo abrir.
 */
export function __alTerminarLaSesion(resultado: ResultadoDeLaSesion | (() => Promise<ResultadoDeLaSesion>)): void {
  alTerminarLaSesion = typeof resultado === 'function' ? resultado : async () => resultado;
}

/** Solo para las pruebas: sin llamadas guardadas y con la sesión que se cierra sin terminar. */
export function __reiniciar(): void {
  openAuthSessionAsync.mockClear();
  openBrowserAsync.mockClear();
  dismissBrowser.mockClear();
  dismissAuthSession.mockClear();
  maybeCompleteAuthSession.mockClear();
  alTerminarLaSesion = async () => ({ type: WebBrowserResultType.CANCEL });
}
