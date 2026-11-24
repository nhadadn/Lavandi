// La marca de instalación (registro-E32, C-229). En iPhone, el almacén cifrado (expo-secure-store, el Keychain)
// sobrevive a desinstalar la app; en Android se borra. Para que reinstalar empiece igual en los dos, sin sesión y en la
// bienvenida, la app deja una marca en sus documentos, que el sistema sí borra al desinstalar en los dos teléfonos. Si
// al arrancar no la encuentra, la instalación es nueva: borra del almacén cifrado lo que pudo sobrevivir y la escribe.
import { File, Paths } from 'expo-file-system';

import { borrarMarcaDeBienvenida } from '../bienvenida/almacen';
import { borrarRol, borrarToken } from '../sesion/almacen';

const NOMBRE_DE_LA_MARCA = 'instalacion';

async function comprobar(): Promise<void> {
  let marca: File;
  try {
    marca = new File(Paths.document, NOMBRE_DE_LA_MARCA);
    if (marca.exists) {
      return;
    }
  } catch {
    // Sin poder leer la marca no se sabe si es una reinstalación: no se borra nada y la app sigue.
    return;
  }

  try {
    await borrarToken();
    await borrarRol();
    await borrarMarcaDeBienvenida();
  } catch {
    // No se escribe la marca: el siguiente arranque lo vuelve a intentar.
    return;
  }

  try {
    marca.create();
  } catch {
    // Lo borrado ya no vuelve. Sin la marca, el siguiente arranque vuelve a borrar: la sesión anterior no se usa.
  }
}

// La comprobación en curso. Al arrancar la piden a la vez la sesión y la pantalla de arranque; corre una sola vez.
let enCurso: Promise<void> | null = null;

/**
 * Comprueba si esta es la primera apertura de la instalación. Si lo es, borra del almacén cifrado el token, su rol y la
 * marca de la bienvenida, y luego escribe la marca de instalación. Se llama antes de leer la sesión o la marca de la
 * bienvenida. Nunca falla: si algo no se puede leer o escribir, la app sigue.
 */
export function comprobarInstalacion(): Promise<void> {
  if (!enCurso) {
    enCurso = comprobar().finally(() => {
      enCurso = null;
    });
  }
  return enCurso;
}
