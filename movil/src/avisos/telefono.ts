// El teléfono del usuario en la api, para que le lleguen los avisos de sus pedidos (avisos-E03 y E05; ADR-0008).
//
// Mientras la sesión del usuario está abierta, la app manda a la api el token de Firebase del teléfono, con su
// plataforma: al abrirse la sesión, al iniciarla o al abrir la app con ella, y cada vez que Firebase lo renueva
// (POST /api/dispositivos). Solo si el usuario concedió el permiso de avisos (avisos-E03), que al abrirse la sesión se
// pide a quien nunca respondió (avisos-E07, src/avisos/permiso.ts): sin él, el teléfono no se registra y el seguimiento
// se pone al día con la consulta (avisos-E08, ADR-0007). Al cerrar la sesión, la app manda el
// token registrado con DELETE /api/sesion para que la api lo borre (avisos-E05; src/sesion/ProveedorDeSesion.tsx).
// Mientras tanto, entrega los mensajes que llegan con la app al frente (avisos-E15; src/avisos/cambiosDeEstado.ts).
//
// Si algo falla (Firebase no da el token, o la api no contesta), la app sigue sin error: lo vuelve a intentar al abrirse
// otra vez o cuando Firebase renueve el token. Solo se usa con los avisos disponibles (src/avisos/firebase.ts).
import { Platform } from 'react-native';

import { registrarDispositivo, type Plataforma } from './api';
import { avisarElCambioDeEstado } from './cambiosDeEstado';
import { mensajeria } from './firebase';
import { leerPermisoDeAvisos, permisoAlAbrirLaSesion } from './permiso';

/** El token que la app mandó con la sesión abierta. Uno a la vez: la app tiene una sola sesión. */
let registrado: { sesion: string; tokenDelTelefono: string } | null = null;

/** El registro del teléfono de la sesión abierta, para repetirlo cuando la persona activa los avisos. */
let registrarConLaSesion: (() => void) | null = null;

/**
 * Registra otra vez el teléfono con la sesión abierta: lo usa la sección de notificaciones del perfil cuando la persona
 * activa los avisos (perfil-usuario-E08). Sin sesión de usuario o sin avisos, no hace nada. Nunca lanza.
 */
export function registrarElTelefonoDeNuevo(): void {
  registrarConLaSesion?.();
}

/** El token del teléfono que la app mandó con esa sesión, para mandarlo al cerrarla (avisos-E05). */
export function tokenRegistradoCon(tokenDeSesion: string): string | undefined {
  return registrado?.sesion === tokenDeSesion ? registrado.tokenDelTelefono : undefined;
}

function plataforma(): Plataforma {
  return Platform.OS === 'ios' ? 'ios' : 'android';
}

/**
 * Deja el teléfono registrado con esa sesión mientras dure: lo registra ya y cada vez que Firebase renueva el token.
 * Devuelve la función que deja de vigilarlo, que la app llama al cerrar la sesión. Nunca lanza.
 */
export function vigilarElTelefono(tokenDeSesion: string): () => void {
  let vigente = true;
  let libreria: ReturnType<typeof mensajeria>;
  let instancia: ReturnType<ReturnType<typeof mensajeria>['getMessaging']>;
  try {
    libreria = mensajeria();
    instancia = libreria.getMessaging();
  } catch {
    // La librería no cargó: la app sigue sin avisos.
    return () => undefined;
  }

  // Manda el token a la api: el que dio Firebase al renovarlo, o el vigente. Al abrirse la sesión, si la persona nunca
  // respondió la petición del permiso, antes se la muestra (avisos-E07); al renovarse el token, solo lee el permiso.
  async function registrar(renovado?: string): Promise<void> {
    const permiso = renovado === undefined ? await permisoAlAbrirLaSesion() : await leerPermisoDeAvisos();
    if (permiso !== 'concedido' || !vigente) {
      return;
    }
    const tokenDelTelefono = renovado ?? (await libreria.getToken(instancia));
    if (!vigente) {
      return;
    }
    registrado = { sesion: tokenDeSesion, tokenDelTelefono };
    await registrarDispositivo(tokenDeSesion, tokenDelTelefono, plataforma());
  }

  const sinFallar = (renovado?: string) => {
    registrar(renovado).catch(() => undefined);
  };

  let dejarDeEscuchar: () => void = () => undefined;
  let dejarDeRecibir: () => void = () => undefined;
  try {
    dejarDeEscuchar = libreria.onTokenRefresh(instancia, (renovado) => sinFallar(renovado));
    // Los mensajes con la app al frente: el de un cambio de estado adelanta la consulta del seguimiento (avisos-E15).
    dejarDeRecibir = libreria.onMessage(instancia, (mensaje) => avisarElCambioDeEstado(mensaje.data));
  } catch {
    // Sin las escuchas, el token se vuelve a mandar al abrir la app y el seguimiento consulta cada 30 segundos.
  }
  const deNuevo = () => sinFallar();
  registrarConLaSesion = deNuevo;
  sinFallar();

  return () => {
    vigente = false;
    dejarDeEscuchar();
    dejarDeRecibir();
    if (registrarConLaSesion === deNuevo) {
      registrarConLaSesion = null;
    }
    if (registrado?.sesion === tokenDeSesion) {
      registrado = null;
    }
  };
}
