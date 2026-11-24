// El perfil de una lavandería para su pantalla (perfil-lavanderia-E09): lo pide al servidor con la sesión al abrirse.
// Cada vez que se abre la pantalla se pide de nuevo; así, las URLs firmadas de las fotos, que vencen, vienen nuevas
// (perfil-lavanderia-E03 y E04; design.md, Risks).
import { useEffect, useState } from 'react';

import type { Falla } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarPerfil, type PerfilDeLavanderia } from './api';

/** cargando: se pide al servidor; listo: llegó el perfil; error: no llegó. */
export type EstadoDelPerfil =
  | { fase: 'cargando' }
  | { fase: 'listo'; perfil: PerfilDeLavanderia }
  | { fase: 'error'; falla: Falla };

export function usePerfilDeLavanderia(idPublico: string | undefined): EstadoDelPerfil {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDelPerfil>({ fase: 'cargando' });

  useEffect(() => {
    // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token || !idPublico) {
      return;
    }
    let vigente = true;
    consultarPerfil(token, idPublico).then((resultado) => {
      if (vigente) {
        setEstado(resultado.ok ? { fase: 'listo', perfil: resultado.datos } : { fase: 'error', falla: resultado });
      }
    });
    return () => {
      vigente = false;
    };
  }, [token, idPublico]);

  return estado;
}
