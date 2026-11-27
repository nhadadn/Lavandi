// La foto en un formulario multipart: la parte que arma toda subida de la app, la foto de perfil (perfil-usuario-E03), el
// registro y la edición de la lavandería (alta-lavanderias-E03 y E19), el registro del repartidor (repartidor-E01) y la
// evidencia de entrega (repartidor-E23). Es la única pieza que la arma (perfil-y-soporte, design.md, "Cómo sale la foto
// del teléfono").
//
// En el teléfono, fetch es expo/fetch (expo/src/winter/runtime.native.ts), y arma el cuerpo con convertFormDataAsync
// (expo/src/winter/fetch/convertFormData.ts): solo sabe mandar un texto, un Blob o un objeto con bytes(). La forma
// { uri, name, type } de React Native la rechaza, la petición no sale y la app dice que no hubo conexión.
//
// Por eso la parte lleva bytes(), que lee la foto con File de expo-file-system, y el nombre y el tipo de abajo. El nombre
// va en la parte, además del tercer argumento de append(), porque el FormData del teléfono (expo/src/winter/FormData.ts)
// solo toma ese argumento para un Blob, y File no lo es: sin él, el archivo llegaría con el nombre al azar que le puso
// la compresión. El File se abre al mandar, no al armar el formulario: si la foto no se puede leer, la petición falla
// como sin conexión y la consulta no lanza.
import { File } from 'expo-file-system';

/** El nombre y el tipo de toda foto que sube la app: JPEG, porque así la deja la compresión (src/alta-lavanderias/foto.ts). */
export const ARCHIVO_DE_LA_FOTO = {
  nombre: 'foto.jpg',
  tipo: 'image/jpeg',
} as const;

/** La foto ya comprimida, en el teléfono. */
export interface FotoDelTelefono {
  uri: string;
}

/** Agrega la foto al formulario, en ese campo, con el nombre y el tipo de ARCHIVO_DE_LA_FOTO y los bytes del archivo. */
export function agregarFoto(formulario: FormData, campo: string, foto: FotoDelTelefono): void {
  const { nombre, tipo } = ARCHIVO_DE_LA_FOTO;
  const parte = { name: nombre, type: tipo, bytes: () => new File(foto.uri).bytes() };
  // El tipo de FormData de la web no describe una parte con bytes(): convertFormDataAsync sí la manda.
  formulario.append(campo, parte as unknown as Blob, nombre);
}
