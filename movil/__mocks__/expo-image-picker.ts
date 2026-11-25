// Simulación de expo-image-picker para Jest: en las pruebas no hay cámara, galería ni diálogo de permisos del sistema.
// Guarda en memoria el permiso de la cámara y la foto que da cada una, que cada prueba programa con las funciones __ de
// abajo. Por omisión, como en una app recién instalada: el permiso de la cámara no se ha pedido y, si se pide, se
// concede; la cámara y la galería se cierran sin foto, como si la persona cancelara. Jest la usa en todas las pruebas
// porque está en __mocks__/ junto a node_modules. La cámara, la galería y los diálogos reales de cada sistema se prueban
// en un teléfono real (alta-lavanderias-E03).

// Los mismos valores que la librería.
export enum PermissionStatus {
  DENIED = 'denied',
  GRANTED = 'granted',
  UNDETERMINED = 'undetermined',
}

interface Permiso {
  status: PermissionStatus;
  granted: boolean;
  canAskAgain: boolean;
  expires: 'never';
}

/** Una foto como la da la librería: su dirección en el teléfono y sus medidas en píxeles. */
export interface FotoDelTelefono {
  uri: string;
  width: number;
  height: number;
  mimeType?: string;
  fileName?: string | null;
}

type Resultado = { canceled: false; assets: FotoDelTelefono[] } | { canceled: true; assets: null };

const CANCELADO: Resultado = { canceled: true, assets: null };

function permiso(status: PermissionStatus, canAskAgain: boolean): Permiso {
  return { status, granted: status === PermissionStatus.GRANTED, canAskAgain, expires: 'never' };
}

const SIN_PEDIR = permiso(PermissionStatus.UNDETERMINED, true);

let permisoDeLaCamara = SIN_PEDIR;
let alPedirLaCamara = permiso(PermissionStatus.GRANTED, true);
let deLaCamara: Resultado = CANCELADO;
let deLaGaleria: Resultado = CANCELADO;

function resultado(foto: FotoDelTelefono | null): Resultado {
  return foto ? { canceled: false, assets: [foto] } : CANCELADO;
}

export const getCameraPermissionsAsync = jest.fn(async (): Promise<Permiso> => permisoDeLaCamara);

export const requestCameraPermissionsAsync = jest.fn(async (): Promise<Permiso> => {
  permisoDeLaCamara = alPedirLaCamara;
  return permisoDeLaCamara;
});

// La galería del sistema no pide permiso para elegir una foto (PHPicker en iPhone y el selector de fotos de Android).
export const getMediaLibraryPermissionsAsync = jest.fn(async (): Promise<Permiso> => SIN_PEDIR);
export const requestMediaLibraryPermissionsAsync = jest.fn(async (): Promise<Permiso> => SIN_PEDIR);

export const launchCameraAsync = jest.fn(async (_opciones?: unknown): Promise<Resultado> => {
  if (!permisoDeLaCamara.granted) {
    throw new Error('Missing camera permission.');
  }
  return deLaCamara;
});

export const launchImageLibraryAsync = jest.fn(async (_opciones?: unknown): Promise<Resultado> => deLaGaleria);

/** Solo para las pruebas: lo que la persona contesta en el diálogo del permiso de la cámara. */
export function __respuestaAlPedirLaCamara(status: PermissionStatus, canAskAgain = status !== PermissionStatus.DENIED): void {
  alPedirLaCamara = permiso(status, canAskAgain);
}

/** Solo para las pruebas: la foto que toma la persona con la cámara, o null si cierra la cámara sin tomarla. */
export function __fotoDeLaCamara(foto: FotoDelTelefono | null): void {
  deLaCamara = resultado(foto);
}

/** Solo para las pruebas: la foto que elige la persona de su galería, o null si la cierra sin elegir. */
export function __fotoDeLaGaleria(foto: FotoDelTelefono | null): void {
  deLaGaleria = resultado(foto);
}

/** Solo para las pruebas: todo como en una app recién instalada, y las llamadas en cero. */
export function __reiniciar(): void {
  permisoDeLaCamara = SIN_PEDIR;
  alPedirLaCamara = permiso(PermissionStatus.GRANTED, true);
  deLaCamara = CANCELADO;
  deLaGaleria = CANCELADO;
  for (const funcion of [
    getCameraPermissionsAsync,
    requestCameraPermissionsAsync,
    getMediaLibraryPermissionsAsync,
    requestMediaLibraryPermissionsAsync,
    launchCameraAsync,
    launchImageLibraryAsync,
  ]) {
    funcion.mockClear();
  }
}
