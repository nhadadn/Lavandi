// Apoyo de las pruebas: el formulario multipart como sale del teléfono y como lo lee el servidor. No es una prueba: Jest
// no lo corre como tal (testPathIgnorePatterns en package.json).
//
// En el teléfono, el FormData global es el de React Native con el parche de Expo (installFormDataPatch, en
// expo/src/winter/runtime.native.ts), y fetch es expo/fetch, que arma el cuerpo con convertFormDataAsync
// (expo/src/winter/fetch/convertFormData.ts). En Jest, jest-expo deja el parche vacío y las pruebas simulan fetch: sin
// este apoyo, ninguna pasa por ahí, y una foto que el teléfono no sabe mandar pasaba las pruebas (perfil-y-soporte,
// design.md, "Cómo sale la foto del teléfono"). Aquí el formulario se arma con el mismo FormData del teléfono y se lee
// con el convertFormDataAsync real: si una parte no se puede mandar, la lectura falla como la petición en el teléfono.
//
// Los archivos del teléfono son la simulación en memoria que expo-file-system trae para Jest y que jest-expo conecta
// sola: la foto comprimida la deja ahí __mocks__/expo-image-manipulator.ts, y la app la lee con File al subirla.
import { File } from 'expo-file-system';
import { convertFormDataAsync } from 'expo/src/winter/fetch/convertFormData';

// El parche de verdad, no el vacío de jest-expo.
const { installFormDataPatch } = jest.requireActual<typeof import('expo/src/winter/FormData')>('expo/src/winter/FormData');

/** El FormData del teléfono: el de React Native con el parche de Expo. */
// eslint-disable-next-line @typescript-eslint/no-require-imports
export const FormDataDelTelefono = installFormDataPatch(require('react-native/Libraries/Network/FormData').default) as unknown as typeof FormData;

/** El contenido del archivo que hay en esa dirección del teléfono: lo que debe llegar al servidor si la app lo sube. */
export function contenidoDelArchivo(uri: string): string {
  return new File(uri).textSync();
}

/** Un archivo del formulario, como lo lee el servidor: su nombre de archivo, su tipo y su contenido. */
export interface ArchivoDelFormulario {
  nombre: string | null;
  tipo: string | null;
  contenido: string;
}

/** Una parte del formulario, como la lee el servidor: de texto, o un archivo. */
export type ParteDelFormulario = { campo: string; texto: string } | { campo: string; archivo: ArchivoDelFormulario };

/** Si el cuerpo de una petición es un formulario multipart del teléfono. */
export function esFormulario(cuerpo: unknown): cuerpo is FormData {
  return cuerpo instanceof FormDataDelTelefono;
}

// El separador de las partes: en el teléfono es al azar; aquí, fijo, para leer el cuerpo.
const LIMITE = '----LimiteDeLaPrueba';
const ENCABEZADO = /^content-disposition: form-data; name="([^"]*)"(?:; filename="([^"]*)")?$/;

// Las partes que ya leyó el servidor, por formulario.
const leidos = new WeakMap<FormData, ParteDelFormulario[]>();

/**
 * Lee el formulario como lo manda expo/fetch: lo convierte con convertFormDataAsync y separa sus partes, con sus
 * encabezados. Lanza, como la petición en el teléfono, si una parte no se puede mandar. Guarda lo leído para
 * partesLeidas().
 */
export async function leerFormulario(formulario: FormData): Promise<ParteDelFormulario[]> {
  const { body } = await convertFormDataAsync(formulario, LIMITE);
  const texto = new TextDecoder().decode(body);
  const final = `--${LIMITE}--\r\n`;
  if (!texto.endsWith(final)) {
    throw new Error(`El cuerpo no cierra con su separador: ${texto}`);
  }
  const partes = texto
    .slice(0, -final.length)
    .split(`--${LIMITE}\r\n`)
    .slice(1)
    .map(leerParte);
  leidos.set(formulario, partes);
  return partes;
}

/** Las partes de un formulario que el servidor ya leyó con leerFormulario(); null si todavía no lo lee. */
export function partesLeidas(formulario: FormData): ParteDelFormulario[] | null {
  return leidos.get(formulario) ?? null;
}

// Una parte: sus encabezados, una línea en blanco y su contenido, que termina en un salto de línea.
function leerParte(bloque: string): ParteDelFormulario {
  const blanco = bloque.indexOf('\r\n\r\n');
  const encabezados = bloque.slice(0, blanco).split('\r\n');
  const contenido = bloque.slice(blanco + 4, -2);
  const disposicion = ENCABEZADO.exec(encabezados[0]);
  if (!disposicion) {
    throw new Error(`Parte sin content-disposition: ${bloque}`);
  }
  const [, campo, nombre] = disposicion;
  const tipo = encabezados.find((linea) => linea.startsWith('content-type: '))?.slice('content-type: '.length) ?? null;
  if (nombre === undefined && tipo === null) {
    return { campo, texto: contenido };
  }
  return { campo, archivo: { nombre: nombre === undefined ? null : decodeURIComponent(nombre), tipo, contenido } };
}
