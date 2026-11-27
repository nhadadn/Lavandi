// La foto de la lavandería (alta-lavanderias-E03 y E19) y la foto de perfil del usuario (perfil-usuario-E03, que la
// reusa desde src/perfil-usuario/FotoDePerfil.tsx): se toma con la cámara o se elige de la galería
// (expo-image-picker) y se comprime antes de subirla, con los parámetros de configuración (ADR-0006;
// src/configuracion/fotos.ts), con expo-image-manipulator. La compresión la deja siempre en JPEG, uno de los tipos que
// acepta el servidor (FOTOS_TIPOS), aunque el teléfono la dé en HEIC.
//
// - La cámara pide su permiso antes de abrirse. Sin él, no se abre: la pantalla lo dice y ofrece la galería.
// - La galería no pide permiso: el selector del sistema (PHPicker en iPhone y el de fotos en Android) solo entrega la
//   foto que la persona elige.
// - Si la persona cierra la cámara o la galería sin foto, no cambia nada.
//
// La cámara, la galería, sus diálogos y el peso real con que sale la foto se prueban en un teléfono real.
import { ImageManipulator, SaveFormat } from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';

import { COMPRESION_DE_FOTOS } from '../configuracion/fotos';

/** La foto ya comprimida, en el teléfono. */
export interface FotoPreparada {
  uri: string;
}

/** La foto, o por qué no hay: la persona cerró sin foto, no dio el permiso de la cámara o la foto no se pudo preparar. */
export type ResultadoDeLaFoto =
  | { ok: true; foto: FotoPreparada }
  | { ok: false; motivo: 'cancelada' | 'sin-permiso' | 'no-se-pudo' };

// Solo fotos: la app no graba video (app.config.ts no declara el micrófono). La compresión es una sola, la de abajo.
const OPCIONES: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], quality: 1 };

/** Abre la cámara, con su permiso, y comprime la foto que se tome. */
export async function tomarFoto(): Promise<ResultadoDeLaFoto> {
  try {
    const permiso = await ImagePicker.requestCameraPermissionsAsync();
    if (!permiso.granted) {
      return { ok: false, motivo: 'sin-permiso' };
    }
    return await preparar(await ImagePicker.launchCameraAsync(OPCIONES));
  } catch {
    return { ok: false, motivo: 'no-se-pudo' };
  }
}

/** Abre la galería y comprime la foto que se elija. */
export async function elegirFoto(): Promise<ResultadoDeLaFoto> {
  try {
    return await preparar(await ImagePicker.launchImageLibraryAsync(OPCIONES));
  } catch {
    return { ok: false, motivo: 'no-se-pudo' };
  }
}

/**
 * La foto con su lado más largo en el máximo de la configuración, si lo pasa, y guardada en JPEG con su calidad. Una
 * foto más chica no se agranda. Sin medidas (el sistema puede darlas en 0), solo se comprime.
 */
async function preparar(resultado: ImagePicker.ImagePickerResult): Promise<ResultadoDeLaFoto> {
  const elegida = resultado.canceled ? undefined : resultado.assets[0];
  if (!elegida) {
    return { ok: false, motivo: 'cancelada' };
  }
  const { ladoMaximoPx, calidad } = COMPRESION_DE_FOTOS;
  const { uri, width: ancho, height: alto } = elegida;
  let contexto = ImageManipulator.manipulate(uri);
  if (Math.max(ancho, alto) > ladoMaximoPx) {
    contexto = contexto.resize(ancho >= alto ? { width: ladoMaximoPx } : { height: ladoMaximoPx });
  }
  const imagen = await contexto.renderAsync();
  const guardada = await imagen.saveAsync({ compress: calidad, format: SaveFormat.JPEG });
  return { ok: true, foto: { uri: guardada.uri } };
}
