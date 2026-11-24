// El repartidor de la cuenta, para todo el Módulo C (openspec/changes/repartidor): al entrar al módulo, la app pide su
// perfil a GET /api/repartidor/perfil, que responde en cualquier estado. Con "puede_operar" en true, el módulo abre sus
// pedidos; si no, la espera de su alta o de que le quiten la suspensión (repartidor-E01 y E03; design.md, "Contrato de
// la API").
//
// "Revisar de nuevo" lo vuelve a pedir: si ya lo dieron de alta, el módulo abre sus pedidos. También lo vuelve a pedir el
// módulo cuando una ruta de pedidos responde que el repartidor ya no opera (403 con su estado), como al suspenderlo con
// la app abierta. Mientras llega el nuevo, la pantalla sigue a la vista. Si no llega, sin escenario propio: la app no
// adivina si opera; el módulo lo dice y deja intentarlo de nuevo. Vive en app/repartidor/_layout.tsx: solo existe con la
// sesión de una cuenta de repartidor y se va con ella.
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import type { Falla } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarPerfilDelRepartidor, type PerfilDelRepartidor } from './api';

/** leyendo: se pide al servidor; listo: llegó; error: no llegó. */
export type EstadoDelPerfil =
  | { fase: 'leyendo' }
  | { fase: 'listo'; repartidor: PerfilDelRepartidor }
  | { fase: 'error'; falla: Falla };

export interface RepartidorDeLaCuenta {
  estado: EstadoDelPerfil;
  /** Si se está volviendo a pedir, con la pantalla a la vista. */
  releyendo: boolean;
  /** Lo vuelve a pedir: tras una falla, al tocar "Revisar de nuevo" o si una ruta dice que ya no opera. */
  leerDeNuevo(): void;
}

const ContextoDelRepartidor = createContext<RepartidorDeLaCuenta | null>(null);

export function ProveedorDelRepartidor({ children }: { children: ReactNode }) {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDelPerfil>({ fase: 'leyendo' });
  const [releyendo, setReleyendo] = useState(false);
  // Cada lectura nueva vuelve a correr la petición.
  const [lectura, setLectura] = useState(0);

  useEffect(() => {
    // El módulo solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token) {
      return;
    }
    let vigente = true;
    consultarPerfilDelRepartidor(token).then((resultado) => {
      if (vigente) {
        setEstado(resultado.ok ? { fase: 'listo', repartidor: resultado.datos } : { fase: 'error', falla: resultado });
        setReleyendo(false);
      }
    });
    return () => {
      vigente = false;
    };
  }, [token, lectura]);

  // Después de una falla, el módulo dice que lo está leyendo; si ya había llegado, la pantalla sigue a la vista
  // mientras llega el nuevo.
  const leerDeNuevo = useCallback(() => {
    setEstado((anterior) => (anterior.fase === 'error' ? { fase: 'leyendo' } : anterior));
    setReleyendo(true);
    setLectura((anterior) => anterior + 1);
  }, []);

  const valor = useMemo<RepartidorDeLaCuenta>(
    () => ({ estado, releyendo, leerDeNuevo }),
    [estado, releyendo, leerDeNuevo],
  );

  return <ContextoDelRepartidor.Provider value={valor}>{children}</ContextoDelRepartidor.Provider>;
}

/** El repartidor de la cuenta. Solo funciona dentro de ProveedorDelRepartidor, que está en app/repartidor/_layout.tsx. */
export function useRepartidorDeLaCuenta(): RepartidorDeLaCuenta {
  const valor = useContext(ContextoDelRepartidor);
  if (!valor) {
    throw new Error('useRepartidorDeLaCuenta se usa dentro de ProveedorDelRepartidor.');
  }
  return valor;
}
