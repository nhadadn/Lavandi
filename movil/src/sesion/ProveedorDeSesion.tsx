// El estado de la sesión para toda la app (ADR-0018). Al montarse, primero comprueba la marca de instalación: si la app
// se acaba de reinstalar, el token que sobrevivió en el almacén cifrado de iPhone se borra (registro-E32). Luego lee el
// token del almacén cifrado y lo comprueba con el servidor (registro-E11): si el servidor ya no lo reconoce (401), lo
// borra y la app queda sin sesión; si no se puede llegar al servidor, la sesión se conserva, porque solo el servidor
// dice que ya no sirve. Cerrar la sesión borra el token del teléfono y después pide al servidor revocarlo, sin esperar
// su respuesta: sale aunque el servidor no responda (registro-E13).
//
// La sesión lleva el módulo al que entra la cuenta, por su rol (registro-E33; src/sesion/modulos.ts): el rol llega al
// iniciar la sesión y en la cuenta, y se guarda con el token para saberlo aunque al abrir la app no se llegue al
// servidor. Una cuenta sin módulo en la app, como la de administración, no se queda con la sesión: al iniciarla, o al
// abrir la app si el servidor dice que no tiene acceso (403), la app la cierra como en "Cerrar sesión" y deja un aviso
// que la pantalla de entrada muestra.
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

import { cerrarSesionEnServidor, consultarCuentaConEstado } from '../acceso/api';
import { cerrarSesionDeGoogle } from '../acceso/google';
import { MENSAJES } from '../acceso/mensajes';
import { tokenRegistradoCon } from '../avisos/telefono';
import { comprobarInstalacion } from '../instalacion/marca';
import { borrarRol, borrarToken, guardarRol, guardarToken, leerRol, leerToken } from './almacen';
import { moduloDelRol, ROL_SIN_GUARDAR, type Modulo } from './modulos';

/** La sesión abierta: su token y el módulo al que entra su cuenta. */
interface SesionAbierta {
  token: string;
  modulo: Modulo;
}

/** Lo que encontró la app al abrirse: la sesión que sigue, ninguna, o una de una cuenta que no usa la app. */
type Lectura = SesionAbierta | 'sin-sesion' | 'sin-acceso';

// Lo que hace "Cerrar sesión" fuera del teléfono: pide al servidor revocar el token, con el token de Firebase que la app
// registró con la sesión para que lo borre (avisos-E05), y a Google cerrar su sesión, para que la siguiente entrada con
// Google deje elegir la cuenta. No espera a ninguno de los dos: si el servidor no contesta, la petición se corta sola al
// vencer su plazo, y Google nunca falla.
function cerrarFueraDelTelefono(token: string | null) {
  if (token) {
    void cerrarSesionEnServidor(token, tokenRegistradoCon(token));
  }
  void cerrarSesionDeGoogle();
}

async function borrarDelTelefono(): Promise<void> {
  await borrarToken();
  await borrarRol();
}

// La sesión guardada, si el servidor la sigue reconociendo. Si ya no, la borra del teléfono, salvo que mientras tanto
// la persona haya iniciado otra sesión (puedeBorrar).
async function leerSesionVigente(puedeBorrar: () => boolean): Promise<Lectura> {
  await comprobarInstalacion();
  const token = await leerToken();
  if (!token) {
    return 'sin-sesion';
  }
  const { resultado, estado } = await consultarCuentaConEstado(token);
  if (!resultado.ok && resultado.motivo === 'sin-sesion') {
    if (puedeBorrar()) {
      await borrarDelTelefono();
    }
    return 'sin-sesion';
  }

  const guardado = await leerRol();
  let rol: string | null;
  if (resultado.ok) {
    rol = resultado.datos.usuario.rol;
  } else if (estado === 403) {
    // El servidor reconoce el token, pero la cuenta no tiene acceso a la app (registro-E33).
    rol = null;
  } else {
    // Sin conexión, o el servidor falló: la sesión sigue con el rol que se guardó con ella (registro-E11).
    rol = guardado ?? ROL_SIN_GUARDAR;
  }

  const modulo = moduloDelRol(rol);
  if (!modulo) {
    if (puedeBorrar()) {
      await borrarDelTelefono();
      cerrarFueraDelTelefono(token);
    }
    return 'sin-acceso';
  }
  // El rol que dio el servidor, si no es el que estaba guardado (o la sesión es de antes de guardarlo).
  if (resultado.ok && rol !== null && rol !== guardado) {
    await guardarRol(rol);
  }
  return { token, modulo };
}

export type EstadoDeSesion = 'cargando' | 'sin-sesion' | 'con-sesion';

export interface Sesion {
  estado: EstadoDeSesion;
  token: string | null;
  /** El módulo al que entra la cuenta de la sesión (registro-E33); null sin sesión. */
  modulo: Modulo | null;
  /** Por qué la app cerró una sesión por su cuenta: la cuenta no usa la app. La pantalla de entrada lo muestra. */
  aviso: string | null;
  /**
   * Guarda el token y el rol de una sesión nueva y la abre. Devuelve el módulo al que entra la cuenta. Si la cuenta no
   * usa la app, no la abre: la cierra como en "Cerrar sesión", deja el aviso y devuelve null (registro-E33).
   */
  iniciar(token: string, rol: string | null): Promise<Modulo | null>;
  /**
   * Borra el token del teléfono, deja la app sin sesión y pide al servidor revocarlo, y a Google cerrar su sesión, sin
   * esperar a ninguno de los dos. Si el almacén cifrado no lo puede borrar, la app queda sin sesión de todos modos y la
   * promesa falla con ese error.
   */
  cerrar(): Promise<void>;
  /** Quita el aviso, cuando la persona vuelve a intentar entrar. */
  olvidarAviso(): void;
}

const ContextoDeSesion = createContext<Sesion | null>(null);

export function ProveedorDeSesion({ children }: { children: ReactNode }) {
  const [abierta, setAbierta] = useState<SesionAbierta | null>(null);
  const [cargando, setCargando] = useState(true);
  const [aviso, setAviso] = useState<string | null>(null);
  // Si la persona inicia o cierra la sesión antes de que termine la lectura, manda lo que hizo.
  const cambiada = useRef(false);

  useEffect(() => {
    let montado = true;
    leerSesionVigente(() => !cambiada.current)
      // Si el almacén cifrado no deja leer ni borrar, la carga termina sin sesión: la persona vuelve a entrar, en vez
      // de quedarse con la app en blanco.
      .catch((): Lectura => 'sin-sesion')
      .then((lectura) => {
        if (montado && !cambiada.current) {
          setAbierta(typeof lectura === 'string' ? null : lectura);
          if (lectura === 'sin-acceso') {
            setAviso(MENSAJES.sinAccesoALaApp);
          }
        }
        if (montado) {
          setCargando(false);
        }
      });
    return () => {
      montado = false;
    };
  }, []);

  const iniciar = useCallback(async (token: string, rol: string | null) => {
    cambiada.current = true;
    const modulo = moduloDelRol(rol);
    if (!modulo || rol === null) {
      // La cuenta no usa la app: nada se guarda en el teléfono y se pide revocar el token que se acaba de dar.
      cerrarFueraDelTelefono(token);
      setAviso(MENSAJES.sinAccesoALaApp);
      return null;
    }
    // Primero el rol: un token guardado sin su rol entraría, sin conexión, al módulo del usuario.
    await guardarRol(rol);
    await guardarToken(token);
    setAviso(null);
    setAbierta({ token, modulo });
    return modulo;
  }, []);

  const cerrar = useCallback(async () => {
    cambiada.current = true;
    const guardado = abierta?.token ?? null;
    try {
      // Primero el teléfono: el cierre no espera al servidor (registro-E13).
      await borrarDelTelefono();
    } finally {
      // Aunque el almacén cifrado falle, la app queda sin sesión.
      setAbierta(null);
      cerrarFueraDelTelefono(guardado);
    }
  }, [abierta]);

  const olvidarAviso = useCallback(() => setAviso(null), []);

  const sesion = useMemo<Sesion>(
    () => ({
      estado: cargando ? 'cargando' : abierta ? 'con-sesion' : 'sin-sesion',
      token: cargando ? null : (abierta?.token ?? null),
      modulo: cargando ? null : (abierta?.modulo ?? null),
      aviso,
      iniciar,
      cerrar,
      olvidarAviso,
    }),
    [cargando, abierta, aviso, iniciar, cerrar, olvidarAviso],
  );

  return <ContextoDeSesion.Provider value={sesion}>{children}</ContextoDeSesion.Provider>;
}

/** La sesión de la app. Solo funciona dentro de ProveedorDeSesion, que está en app/_layout.tsx. */
export function useSesion(): Sesion {
  const sesion = useContext(ContextoDeSesion);
  if (!sesion) {
    throw new Error('useSesion se usa dentro de ProveedorDeSesion.');
  }
  return sesion;
}
