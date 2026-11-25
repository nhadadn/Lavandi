// Las favoritas de la cuenta para el paso de la dirección (direcciones-E06): las pide al servidor con la sesión al
// abrirse la pantalla. Traen su punto guardado, así que elegirlas no geocodifica (direcciones-E07).
import { useEffect, useState } from 'react';

import type { Falla } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarFavoritas, type Direccion } from './api';

/** cargando: se piden al servidor; listas: llegaron; error: no llegaron. */
export type EstadoDeLasFavoritas =
  | { fase: 'cargando' }
  | { fase: 'listas'; favoritas: Direccion[] }
  | { fase: 'error'; falla: Falla };

export function useFavoritas(): EstadoDeLasFavoritas {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDeLasFavoritas>({ fase: 'cargando' });

  useEffect(() => {
    // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token) {
      return;
    }
    let vigente = true;
    consultarFavoritas(token).then((resultado) => {
      if (vigente) {
        setEstado(resultado.ok ? { fase: 'listas', favoritas: resultado.datos } : { fase: 'error', falla: resultado });
      }
    });
    return () => {
      vigente = false;
    };
  }, [token]);

  return estado;
}
