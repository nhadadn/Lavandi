// El perfil de la cuenta para "Mi perfil" (perfil-usuario-E01): lo pide al servidor con la sesión al abrirse la
// pantalla, y lo reemplaza con el que devuelve el servidor al guardar el nombre.
import { useCallback, useEffect, useState } from 'react';

import type { Falla } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarPerfil, type PerfilDeUsuario } from './api';

/** cargando: se pide al servidor; listo: llegó el perfil; error: no llegó. */
export type EstadoDelPerfilDeUsuario =
  | { fase: 'cargando' }
  | { fase: 'listo'; perfil: PerfilDeUsuario }
  | { fase: 'error'; falla: Falla };

export interface PerfilEnPantalla {
  estado: EstadoDelPerfilDeUsuario;
  /** Pone el perfil que devolvió el servidor al guardar. */
  actualizar: (perfil: PerfilDeUsuario) => void;
}

export function usePerfilDeUsuario(): PerfilEnPantalla {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDelPerfilDeUsuario>({ fase: 'cargando' });

  useEffect(() => {
    // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token) {
      return;
    }
    let vigente = true;
    consultarPerfil(token).then((resultado) => {
      if (vigente) {
        setEstado(resultado.ok ? { fase: 'listo', perfil: resultado.datos } : { fase: 'error', falla: resultado });
      }
    });
    return () => {
      vigente = false;
    };
  }, [token]);

  const actualizar = useCallback((perfil: PerfilDeUsuario) => setEstado({ fase: 'listo', perfil }), []);

  return { estado, actualizar };
}
