// Los avisos masivos de la administración en la app (F-D07; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7,
// "Envío segmentado o global para promociones o mantenimiento."; administracion-app-E42 y E43): los avisos enviados y
// mandar uno nuevo, con título y texto, a todos los teléfonos registrados. El aviso es global: el aviso a un segmento
// espera DD-113 (administracion-app-E44), así que ni la app ni la API lo piden.
//
// El contrato es el del comentario de servidor/app/Http/Controllers/Api/Administracion/AvisosMasivosController.php,
// copiado aquí:
//
//     GET  /api/administracion/avisos-masivos   200 { "avisos": [ <aviso>, … ] }, del más reciente al más antiguo
//     POST /api/administracion/avisos-masivos   { "titulo", "mensaje" } → 201 { "aviso": <aviso> }, con
//                                               "destinatarios": null; 422 con el error de cada campo, y no sale nada
//
//     <aviso> = { "titulo", "mensaje", "enviado_en" (en UTC), "enviado_por_esta_cuenta" (true o false: el correo de
//               otra cuenta de administración no sale), "destinatarios" (a cuántos teléfonos salió; null mientras
//               sigue en la cola, y la app dice "En cola") }
//
// Las reglas del título y del texto (obligatorios, hasta 255 y 1000 caracteres) las aplica el servidor, con la misma
// clase que el panel (App\Avisos\CreacionDeAvisoMasivo; ADR-0022, decisión 9): la app no valida por su cuenta, manda lo
// que se escribió y muestra el error 422 de cada campo junto a él. Los topes de aquí solo dicen, bajo cada campo,
// cuántos caracteres lleva de su tope.
//
// Va sobre el cliente de la administración (src/administracion/api.ts), que pone el token y borra la sesión con un 401.
// Como las demás consultas, nunca lanza: devuelve un Resultado. Una respuesta fuera del contrato no se pinta a medias:
// cuenta como falla del servidor.
import { esObjeto, numeroOVacio } from '../../configuracion/lectura';
import type { PedirALaAdministracion, ResultadoDeLaAdministracion } from '../api';

/** La ruta y los campos de los avisos masivos, bajo /api/administracion, en un solo lugar para ajustarlos. */
export const CONTRATO_DE_LOS_AVISOS = {
  avisos: 'avisos-masivos',
  titulo: 'titulo',
  mensaje: 'mensaje',
} as const;

/** Los topes del título y del texto, en caracteres, los de CreacionDeAvisoMasivo (administracion-E23). */
export const TOPE_DEL_TITULO = 255;
export const TOPE_DEL_MENSAJE = 1000;

/** Un aviso masivo enviado, como sale en las dos rutas. */
export interface AvisoMasivo {
  titulo: string;
  mensaje: string;
  /** Cuándo lo mandaron, en UTC (ISO 8601). */
  enviado_en: string;
  /** Si lo mandó la cuenta de la sesión; false si lo mandó otra cuenta de administración. */
  enviado_por_esta_cuenta: boolean;
  /** A cuántos teléfonos salió; null mientras sigue en la cola. */
  destinatarios: number | null;
}

/** Lo que se escribe en un aviso nuevo, como se manda. */
export interface AvisoNuevo {
  titulo: string;
  mensaje: string;
}

type Resultado<T> = ResultadoDeLaAdministracion<T>;

const FUERA_DEL_CONTRATO = { ok: false, motivo: 'servidor' } as const;

/** GET avisos-masivos: los enviados, del más reciente al más antiguo, en su orden (administracion-app-E43). */
export async function consultarAvisos(pedir: PedirALaAdministracion): Promise<Resultado<AvisoMasivo[]>> {
  const resultado = await pedir<unknown>(CONTRATO_DE_LOS_AVISOS.avisos);
  if (!resultado.ok) {
    return resultado;
  }
  const filas = esObjeto(resultado.datos) ? resultado.datos.avisos : undefined;
  const avisos = Array.isArray(filas) ? filas.map(leerAviso) : null;
  return avisos && avisos.every(esAviso) ? { ok: true, datos: avisos } : FUERA_DEL_CONTRATO;
}

/**
 * POST avisos-masivos con el título y el texto como se escribieron: el servidor les quita los espacios de los extremos
 * (administracion-app-E42). Solo se llama después de que la persona confirma que sale a todos los teléfonos
 * registrados.
 */
export async function mandarAviso(pedir: PedirALaAdministracion, aviso: AvisoNuevo): Promise<Resultado<AvisoMasivo>> {
  const resultado = await pedir<unknown>(CONTRATO_DE_LOS_AVISOS.avisos, {
    metodo: 'POST',
    cuerpo: {
      [CONTRATO_DE_LOS_AVISOS.titulo]: aviso.titulo,
      [CONTRATO_DE_LOS_AVISOS.mensaje]: aviso.mensaje,
    },
  });
  if (!resultado.ok) {
    return resultado;
  }
  const guardado = esObjeto(resultado.datos) ? leerAviso(resultado.datos.aviso) : null;
  return esAviso(guardado) ? { ok: true, datos: guardado } : FUERA_DEL_CONTRATO;
}

// Un aviso leído campo por campo; null si no es del contrato.
function leerAviso(valor: unknown): AvisoMasivo | null {
  if (
    !esObjeto(valor) ||
    typeof valor.titulo !== 'string' ||
    typeof valor.mensaje !== 'string' ||
    typeof valor.enviado_en !== 'string' ||
    Number.isNaN(Date.parse(valor.enviado_en)) ||
    typeof valor.enviado_por_esta_cuenta !== 'boolean'
  ) {
    return null;
  }
  const destinatarios = numeroOVacio(valor.destinatarios);
  if (destinatarios === undefined) {
    return null;
  }
  return {
    titulo: valor.titulo,
    mensaje: valor.mensaje,
    enviado_en: valor.enviado_en,
    enviado_por_esta_cuenta: valor.enviado_por_esta_cuenta,
    destinatarios,
  };
}

function esAviso(aviso: AvisoMasivo | null): aviso is AvisoMasivo {
  return aviso !== null;
}
