// Cliente de la API de acceso: registro, sesión, Google y recuperación de la contraseña
// (openspec/changes/registro-e-inicio-de-sesion). Toda consulta sale de la dirección base del entorno (base-E07).
// Ninguna función lanza: cada una devuelve un Resultado que la pantalla lee.
import { pedir, pedirConEstado, type Resultado, type ResultadoConEstado } from '../configuracion/api';

export type { ErroresPorCampo, Falla, Resultado } from '../configuracion/api';

/** La cuenta como la devuelve el servidor. Nunca trae la contraseña (registro-E06). */
export interface Usuario {
  id_publico: string;
  correo: string;
  /** El rol de la cuenta, que dice a qué módulo de la app entra (registro-E33): "usuario", "lavanderia"… */
  rol: string | null;
}

/** Lo que devuelven el registro, el inicio de sesión y Google. */
export interface SesionNueva {
  token: string;
  usuario: Usuario;
}

export interface Credenciales {
  correo: string;
  password: string;
}

/** POST /registro: crea la cuenta con el rol de usuario y abre su sesión (registro-E01 y E04). */
export function registrar(credenciales: Credenciales): Promise<Resultado<SesionNueva>> {
  return pedir<SesionNueva>('registro', { metodo: 'POST', cuerpo: soloCredenciales(credenciales) });
}

/** POST /sesion: abre la sesión con el correo y la contraseña (registro-E07 y E09). */
export function iniciarSesion(credenciales: Credenciales): Promise<Resultado<SesionNueva>> {
  return pedir<SesionNueva>('sesion', { metodo: 'POST', cuerpo: soloCredenciales(credenciales) });
}

/** GET /cuenta: la propia cuenta, con el token (registro-E10 y E11). */
export async function consultarCuenta(token: string): Promise<Resultado<{ usuario: Usuario }>> {
  return (await consultarCuentaConEstado(token)).resultado;
}

/**
 * GET /cuenta, con el código HTTP de la respuesta: el 403 dice que la cuenta no tiene acceso a la app, como la de
 * administración, que entra por el panel (registro-E33).
 */
export function consultarCuentaConEstado(token: string): Promise<ResultadoConEstado<{ usuario: Usuario }>> {
  return pedirConEstado<{ usuario: Usuario }>('cuenta', { metodo: 'GET', token });
}

/**
 * DELETE /sesion: el servidor revoca el token (registro-E12 y E13). Con el token de Firebase que la app registró con la
 * sesión, el servidor además lo borra, y los avisos de la cuenta ya no van a ese teléfono (avisos-E05). Sin él, solo
 * cierra la sesión: es el teléfono sin permiso de avisos.
 */
export function cerrarSesionEnServidor(token: string, tokenDelTelefono?: string): Promise<Resultado<void>> {
  return pedir<void>('sesion', {
    metodo: 'DELETE',
    token,
    ...(tokenDelTelefono ? { cuerpo: { token_fcm: tokenDelTelefono } } : {}),
  });
}

/** POST /sesion/google: el servidor valida el idToken y abre la sesión (registro-E16 y E19). */
export function iniciarSesionConGoogle(idToken: string): Promise<Resultado<SesionNueva>> {
  return pedir<SesionNueva>('sesion/google', { metodo: 'POST', cuerpo: { id_token: idToken } });
}

/** POST /contrasena/olvido: pide el enlace de recuperación; la respuesta es la misma exista o no la cuenta (E26). */
export function pedirRecuperacion(correo: string): Promise<Resultado<{ message: string }>> {
  return pedir<{ message: string }>('contrasena/olvido', { metodo: 'POST', cuerpo: { correo } });
}

// Solo el correo y la contraseña: la app nunca manda un rol (registro-E01).
function soloCredenciales({ correo, password }: Credenciales): Credenciales {
  return { correo, password };
}
