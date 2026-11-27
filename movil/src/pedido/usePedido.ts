// Un pedido de la cuenta para la confirmación (confirmacion-E09): lo pide al servidor con la sesión, por su id_publico,
// al abrirse la pantalla. Como el perfil de la lavandería, se pide de nuevo cada vez que se abre.
import { useEffect, useState } from 'react';

import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarPedido, type FallaAlConsultar, type Pedido } from './api';

/** cargando: se pide al servidor; listo: llegó el pedido; error: no llegó, o no existe (confirmacion-E12). */
export type EstadoDelPedido = { fase: 'cargando' } | { fase: 'listo'; pedido: Pedido } | { fase: 'error'; falla: FallaAlConsultar };

export function usePedido(idPublico: string | undefined): EstadoDelPedido {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDelPedido>({ fase: 'cargando' });

  useEffect(() => {
    // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token || !idPublico) {
      return;
    }
    let vigente = true;
    consultarPedido(token, idPublico).then((resultado) => {
      if (vigente) {
        setEstado(resultado.ok ? { fase: 'listo', pedido: resultado.datos } : { fase: 'error', falla: resultado });
      }
    });
    return () => {
      vigente = false;
    };
  }, [token, idPublico]);

  return estado;
}
