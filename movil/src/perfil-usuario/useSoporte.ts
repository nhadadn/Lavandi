// El número de soporte para "Ayuda" (perfil-usuario-E09 y E10): lo pide al servidor con la sesión cada vez que se abre
// la pantalla, así un número que llegue tarde a la configuración (DD-082) se ve sin publicar otra versión de la app.
import { useEffect, useState } from 'react';

import type { Falla } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarSoporte } from './api';
import { enlaceDeWhatsApp } from './whatsapp';

/** cargando: se pide al servidor; listo: llegó, con el enlace del chat o null si no hay número; error: no llegó. */
export type EstadoDelSoporte =
  | { fase: 'cargando' }
  | { fase: 'listo'; enlace: string | null }
  | { fase: 'error'; falla: Falla };

export function useSoporte(): EstadoDelSoporte {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDelSoporte>({ fase: 'cargando' });

  useEffect(() => {
    // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token) {
      return;
    }
    let vigente = true;
    consultarSoporte(token).then((resultado) => {
      if (vigente) {
        setEstado(
          resultado.ok
            ? { fase: 'listo', enlace: enlaceDeWhatsApp(resultado.datos.whatsapp) }
            : { fase: 'error', falla: resultado },
        );
      }
    });
    return () => {
      vigente = false;
    };
  }, [token]);

  return estado;
}

/** El enlace del chat, si ya llegó y hay número. */
export function enlaceDelSoporte(estado: EstadoDelSoporte): string | null {
  return estado.fase === 'listo' ? estado.enlace : null;
}
