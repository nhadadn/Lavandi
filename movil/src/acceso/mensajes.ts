// Los mensajes que las pantallas de entrada muestran cuando algo no sale. Viven juntos para revisarlos en un solo
// lugar. Los errores de cada campo (422) son los del servidor; estos son los generales.
import type { Falla } from './api';

export const MENSAJES = {
  sinConexion: 'No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.',
  servidor: 'Algo salió mal. Intenta de nuevo.',
  // ADR-0018: 5 intentos por minuto. El texto no repite el número, que vive en la configuración del servidor.
  demasiadosIntentos: 'Demasiados intentos. Espera un minuto e intenta de nuevo.',
  credenciales: 'El correo o la contraseña no son válidos.',
  google: 'No se pudo entrar con Google. Intenta de nuevo.',
  // Una cuenta sin módulo en la app, como la de administración, que entra por el panel (registro-E33).
  sinAccesoALaApp: 'Esta cuenta no tiene acceso a la app. Las cuentas de administración entran por el panel.',
} as const;

/**
 * El mensaje general de una falla. Con 422, solo si algún error es de un campo que la pantalla no muestra (como el
 * id_token de Google) o si el servidor no dijo qué campo falló.
 */
export function mensajeGeneral(falla: Falla, camposVisibles: readonly string[] = []): string | undefined {
  switch (falla.motivo) {
    case 'invalido': {
      const campos = Object.keys(falla.errores);
      const oculto = campos.find((campo) => !camposVisibles.includes(campo));
      if (campos.length > 0 && oculto === undefined) {
        return undefined;
      }
      return (oculto ? falla.errores[oculto]?.[0] : undefined) || falla.mensaje || MENSAJES.servidor;
    }
    case 'sin-sesion':
      return falla.mensaje || MENSAJES.credenciales;
    case 'demasiados-intentos':
      return MENSAJES.demasiadosIntentos;
    case 'sin-conexion':
      return MENSAJES.sinConexion;
    case 'servidor':
      return MENSAJES.servidor;
  }
}

/** El primer error de un campo, para mostrarlo junto a él (registro-E04). */
export function errorDeCampo(falla: Falla | undefined, campo: string): string | undefined {
  return falla?.motivo === 'invalido' ? falla.errores[campo]?.[0] : undefined;
}
