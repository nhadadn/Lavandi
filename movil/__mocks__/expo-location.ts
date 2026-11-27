// Simulación de expo-location para Jest: en las pruebas no hay GPS ni diálogo de permisos del sistema. Guarda en memoria
// el permiso de ubicación y la ubicación del teléfono, que cada prueba programa con las funciones __ de abajo. Por
// omisión, como en una app recién instalada: el permiso no se ha pedido y, si se pide, se concede. Jest la usa en todas
// las pruebas porque está en __mocks__/ junto a node_modules. El diálogo real de cada sistema, con su texto, se prueba
// en un teléfono real (cercanas-E03).

// Los mismos valores que la librería.
export enum PermissionStatus {
  DENIED = 'denied',
  GRANTED = 'granted',
  UNDETERMINED = 'undetermined',
}

export enum Accuracy {
  Lowest = 1,
  Low = 2,
  Balanced = 3,
  High = 4,
  Highest = 5,
  BestForNavigation = 6,
}

interface Permiso {
  status: PermissionStatus;
  granted: boolean;
  canAskAgain: boolean;
  expires: 'never';
}

interface Coordenadas {
  latitude: number;
  longitude: number;
}

function permiso(status: PermissionStatus, canAskAgain: boolean): Permiso {
  return { status, granted: status === PermissionStatus.GRANTED, canAskAgain, expires: 'never' };
}

const SIN_PEDIR = permiso(PermissionStatus.UNDETERMINED, true);
const UBICACION_POR_OMISION: Coordenadas = { latitude: 0, longitude: 0 };

let actual = SIN_PEDIR;
let alPedir = permiso(PermissionStatus.GRANTED, true);
let ubicacion: Coordenadas | Error = UBICACION_POR_OMISION;
// Las precisiones con las que el teléfono no da la ubicación actual, como el emulador con la equilibrada, que en Android
// sale de la red.
let precisionesQueFallan: Accuracy[] = [];
let ultimaConocida: Coordenadas | null = null;

function posicion(coordenadas: Coordenadas) {
  return {
    coords: { ...coordenadas, altitude: null, accuracy: 10, altitudeAccuracy: null, heading: null, speed: null },
    timestamp: 0,
  };
}

export const getForegroundPermissionsAsync = jest.fn(async (): Promise<Permiso> => actual);

export const requestForegroundPermissionsAsync = jest.fn(async (): Promise<Permiso> => {
  actual = alPedir;
  return actual;
});

// El segundo plano existe en la librería; la app nunca lo pide (cercanas-E03). Están para que la prueba lo compruebe.
export const getBackgroundPermissionsAsync = jest.fn(async (): Promise<Permiso> => SIN_PEDIR);
export const requestBackgroundPermissionsAsync = jest.fn(async (): Promise<Permiso> => SIN_PEDIR);

export const getCurrentPositionAsync = jest.fn(async (opciones?: { accuracy?: Accuracy }) => {
  if (ubicacion instanceof Error) {
    throw ubicacion;
  }
  if (opciones?.accuracy !== undefined && precisionesQueFallan.includes(opciones.accuracy)) {
    throw new Error('Current location is unavailable.');
  }
  return posicion(ubicacion);
});

export const getLastKnownPositionAsync = jest.fn(async () => (ultimaConocida ? posicion(ultimaConocida) : null));

/** Solo para las pruebas: el permiso que la app lee al abrir la pantalla. */
export function __permiso(status: PermissionStatus, canAskAgain = status !== PermissionStatus.DENIED): void {
  actual = permiso(status, canAskAgain);
}

/** Solo para las pruebas: lo que la persona contesta en el diálogo del sistema. */
export function __respuestaAlPedir(status: PermissionStatus, canAskAgain = status !== PermissionStatus.DENIED): void {
  alPedir = permiso(status, canAskAgain);
}

/** Solo para las pruebas: dónde está el teléfono, o el error si no se puede saber. */
export function __ubicacion(valor: Coordenadas | Error): void {
  ubicacion = valor;
}

/** Solo para las pruebas: con qué precisiones el teléfono no da la ubicación actual. */
export function __fallaConPrecision(precisiones: Accuracy[]): void {
  precisionesQueFallan = precisiones;
}

/** Solo para las pruebas: la última ubicación que el teléfono recuerda, o null si no recuerda ninguna. */
export function __ultimaConocida(valor: Coordenadas | null): void {
  ultimaConocida = valor;
}

/** Solo para las pruebas: todo como en una app recién instalada, y las llamadas en cero. */
export function __reiniciar(): void {
  actual = SIN_PEDIR;
  alPedir = permiso(PermissionStatus.GRANTED, true);
  ubicacion = UBICACION_POR_OMISION;
  precisionesQueFallan = [];
  ultimaConocida = null;
  for (const funcion of [
    getForegroundPermissionsAsync,
    requestForegroundPermissionsAsync,
    getBackgroundPermissionsAsync,
    requestBackgroundPermissionsAsync,
    getCurrentPositionAsync,
    getLastKnownPositionAsync,
  ]) {
    funcion.mockClear();
  }
}
