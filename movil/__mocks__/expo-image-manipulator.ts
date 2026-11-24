// Simulación de expo-image-manipulator para Jest: en las pruebas no hay imágenes que transformar. Registra cada foto que
// la app prepara, con su tamaño nuevo y cómo la guarda, y devuelve una dirección nueva con las medidas resultantes, como
// la librería. Como la librería, deja el archivo en esa dirección: en la memoria que expo-file-system trae para Jest y
// que jest-expo conecta sola, con un contenido que nombra su dirección, para que la app lo lea al subirlo
// (src/configuracion/foto-del-formulario.ts). Jest la usa en todas las pruebas porque está en __mocks__/ junto a
// node_modules. La compresión real, y el peso con que sale, se prueban en un teléfono real (alta-lavanderias-E03).
import { File } from 'expo-file-system';

// Los mismos valores que la librería.
export enum SaveFormat {
  JPEG = 'jpeg',
  PNG = 'png',
  WEBP = 'webp',
}

interface Tamano {
  width?: number | null;
  height?: number | null;
}

interface OpcionesDeGuardado {
  compress?: number;
  format?: SaveFormat;
  base64?: boolean;
}

/** Lo que la app hizo con una foto: de cuál partió, a qué tamaño la llevó (si la llevó) y cómo la guardó. */
export interface Preparacion {
  fuente: string;
  redimension: Tamano | null;
  guardado: OpcionesDeGuardado | null;
  /** La dirección de la foto que salió. */
  resultado: string | null;
}

let preparaciones: Preparacion[] = [];
let falla: Error | null = null;

/** La dirección de la foto que sale de otra: la misma con "-preparada.jpg". */
export function __preparada(fuente: string): string {
  return `${fuente}-preparada.jpg`;
}

function contexto(preparacion: Preparacion) {
  const operaciones = {
    resize(tamano: Tamano) {
      preparacion.redimension = tamano;
      return operaciones;
    },
    async renderAsync() {
      if (falla) {
        throw falla;
      }
      return {
        width: preparacion.redimension?.width ?? 0,
        height: preparacion.redimension?.height ?? 0,
        async saveAsync(opciones: OpcionesDeGuardado = {}) {
          preparacion.guardado = opciones;
          preparacion.resultado = __preparada(preparacion.fuente);
          const archivo = new File(preparacion.resultado);
          archivo.create({ intermediates: true, overwrite: true });
          await archivo.write(`foto preparada en ${preparacion.resultado}`);
          return {
            uri: preparacion.resultado,
            width: preparacion.redimension?.width ?? 0,
            height: preparacion.redimension?.height ?? 0,
          };
        },
      };
    },
  };
  return operaciones;
}

export const ImageManipulator = {
  manipulate: jest.fn((fuente: string) => {
    const preparacion: Preparacion = { fuente, redimension: null, guardado: null, resultado: null };
    preparaciones.push(preparacion);
    return contexto(preparacion);
  }),
};

/** Solo para las pruebas: lo que la app hizo con cada foto, en orden. */
export function __preparaciones(): Preparacion[] {
  return preparaciones;
}

/** Solo para las pruebas: que la librería no pueda preparar la foto, como una imagen dañada. */
export function __fallar(error: Error | null): void {
  falla = error;
}

/** Solo para las pruebas: sin fotos preparadas ni fallas, y las llamadas en cero. */
export function __reiniciar(): void {
  preparaciones = [];
  falla = null;
  ImageManipulator.manipulate.mockClear();
}
