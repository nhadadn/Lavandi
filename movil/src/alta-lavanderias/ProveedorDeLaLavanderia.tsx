// La lavandería de la cuenta de socio lavandería, para todo el Módulo B (openspec/changes/alta-de-lavanderias): al entrar
// al módulo, la app la pide a GET /api/socio/lavanderias, que da las de la cuenta, hoy a lo más una. Sin lavandería, la
// app abre el registro en lugar de los pedidos (alta-lavanderias-E03; design.md, "El contrato de la API"); con ella, sus
// pedidos, y el perfil y horarios la edita por su id_publico (alta-lavanderias-E19).
//
// Al registrarla o editarla, la pantalla pone aquí la que devolvió el servidor. Si no llega, sin escenario propio: la
// app no adivina si la cuenta tiene lavandería; el módulo lo dice y deja intentarlo de nuevo. Vive en
// app/lavanderia/_layout.tsx: solo existe con la sesión de una cuenta de socio lavandería y se va con ella.
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import type { Falla } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarLavanderiasDelSocio, type LavanderiaDelSocio } from './api';

/** leyendo: se pide al servidor; lista: llegó, con su lavandería o null si no tiene; error: no llegó. */
export type EstadoDeLaLavanderia =
  | { fase: 'leyendo' }
  | { fase: 'lista'; lavanderia: LavanderiaDelSocio | null }
  | { fase: 'error'; falla: Falla };

export interface LavanderiaDeLaCuenta {
  estado: EstadoDeLaLavanderia;
  /** La vuelve a pedir: tras una falla, o si el servidor dice que la cuenta ya tiene una (409). */
  leerDeNuevo(): void;
  /** Pone la lavandería como la devolvió el servidor al registrarla o editarla. */
  poner(lavanderia: LavanderiaDelSocio): void;
}

const ContextoDeLaLavanderia = createContext<LavanderiaDeLaCuenta | null>(null);

export function ProveedorDeLaLavanderia({ children }: { children: ReactNode }) {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDeLaLavanderia>({ fase: 'leyendo' });
  // Cada lectura nueva vuelve a correr la petición.
  const [lectura, setLectura] = useState(0);

  useEffect(() => {
    // El módulo solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token) {
      return;
    }
    let vigente = true;
    consultarLavanderiasDelSocio(token).then((resultado) => {
      if (vigente) {
        setEstado(
          resultado.ok ? { fase: 'lista', lavanderia: resultado.datos[0] ?? null } : { fase: 'error', falla: resultado },
        );
      }
    });
    return () => {
      vigente = false;
    };
  }, [token, lectura]);

  // Después de una falla, el módulo dice que la está leyendo; si ya había llegado, la pantalla sigue a la vista
  // mientras llega la nueva.
  const leerDeNuevo = useCallback(() => {
    setEstado((anterior) => (anterior.fase === 'error' ? { fase: 'leyendo' } : anterior));
    setLectura((anterior) => anterior + 1);
  }, []);

  const poner = useCallback((lavanderia: LavanderiaDelSocio) => setEstado({ fase: 'lista', lavanderia }), []);

  const valor = useMemo<LavanderiaDeLaCuenta>(() => ({ estado, leerDeNuevo, poner }), [estado, leerDeNuevo, poner]);

  return <ContextoDeLaLavanderia.Provider value={valor}>{children}</ContextoDeLaLavanderia.Provider>;
}

/** La lavandería de la cuenta. Solo funciona dentro de ProveedorDeLaLavanderia, que está en app/lavanderia/_layout.tsx. */
export function useLavanderiaDeLaCuenta(): LavanderiaDeLaCuenta {
  const valor = useContext(ContextoDeLaLavanderia);
  if (!valor) {
    throw new Error('useLavanderiaDeLaCuenta se usa dentro de ProveedorDeLaLavanderia.');
  }
  return valor;
}
