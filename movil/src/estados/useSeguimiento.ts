// El pedido para su seguimiento (estados-E02 y E24), puesto al día cada 30 segundos (estados-E15 y E16; ADR-0007): así
// un cambio de la lavandería se ve en la app del usuario en 60 segundos o menos (NSV-2026-001-v8.pdf:21:ANEXO A,
// Sección A.4.3). La consulta corre solo mientras la pantalla está visible y la app al frente, y al volver a ella o al
// traer la app al frente consulta de inmediato (src/estados/useConsultaPeriodica.ts).
//
// Si una consulta no llega, se conserva el último pedido que llegó, con el motivo para que la pantalla lo diga; la
// siguiente que llega lo quita. Sin escenario propio: los mensajes generales de la app.
//
// Con la app al frente, el mensaje de Firebase del cambio de estado de este pedido hace la consulta de inmediato y vuelve
// a contar los 30 segundos desde ahí (avisos-E15; src/avisos/cambiosDeEstado.ts). Sin avisos, la consulta sigue igual.
import { useCallback, useEffect, useMemo, useState } from 'react';

import { alCambiarElEstado, mismoPedido } from '../avisos/cambiosDeEstado';
import type { Falla, Resultado } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarPedido, type PedidoEnSeguimiento } from './api';
import { useConsultaPeriodica } from './useConsultaPeriodica';

export interface EstadoDelSeguimiento {
  /** El último pedido que llegó; null hasta la primera respuesta. */
  pedido: PedidoEnSeguimiento | null;
  /** Por qué no llegó la última consulta; null si llegó. */
  falla: Falla | null;
}

export function useSeguimiento(idPublico: string | undefined): EstadoDelSeguimiento {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDelSeguimiento>({ pedido: null, falla: null });

  // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
  const consulta = useMemo(
    () => (token && idPublico ? () => consultarPedido(token, idPublico) : null),
    [token, idPublico],
  );
  const alLlegar = useCallback((resultado: Resultado<PedidoEnSeguimiento>) => {
    setEstado((anterior) =>
      resultado.ok ? { pedido: resultado.datos, falla: null } : { pedido: anterior.pedido, falla: resultado },
    );
  }, []);

  const consultarDeNuevo = useConsultaPeriodica(consulta, alLlegar);

  useEffect(() => {
    if (!idPublico) {
      return;
    }
    return alCambiarElEstado((pedido) => {
      if (mismoPedido(pedido, idPublico)) {
        consultarDeNuevo();
      }
    });
  }, [idPublico, consultarDeNuevo]);

  return estado;
}
