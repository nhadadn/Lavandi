// La lista de "Mis pedidos" para su pantalla (historial-E01, E03 y E04): la pide al servidor con la sesión, con el rango
// del filtro si lo hay, al abrirse, al cambiar el filtro y cada vez que la pantalla vuelve a verse, por ejemplo al
// regresar del seguimiento de un pedido, para que el estado de cada uno esté al día (useFocusEffect de expo-router).
//
// La respuesta se guarda con el rango que la pidió: al volver a la pantalla con el mismo rango, se queda la lista que
// había mientras llega la nueva; con un rango nuevo, o sin él, la pantalla espera la suya y no muestra la de otro filtro.
// Si llegara la respuesta de un filtro que ya cambió, no se pinta.
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import type { Falla, Resultado } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarHistorial, type PedidoDelHistorial, type RangoDeFechas } from './api';

/** cargando: se pide al servidor; lista: llegaron los pedidos; error: no llegaron. */
export type EstadoDelHistorial =
  | { fase: 'cargando' }
  | { fase: 'lista'; pedidos: PedidoDelHistorial[] }
  | { fase: 'error'; falla: Falla };

interface Respuesta {
  /** El rango con que se pidió: el mismo objeto que dio la pantalla, o null sin filtro. */
  rango: RangoDeFechas | null;
  resultado: Resultado<PedidoDelHistorial[]>;
}

export function useHistorial(rango: RangoDeFechas | null): EstadoDelHistorial {
  const { token } = useSesion();
  const [respuesta, setRespuesta] = useState<Respuesta | null>(null);

  useFocusEffect(
    useCallback(() => {
      // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
      if (!token) {
        return;
      }
      let vigente = true;
      consultarHistorial(token, rango).then((resultado) => {
        if (vigente) {
          setRespuesta({ rango, resultado });
        }
      });
      return () => {
        vigente = false;
      };
    }, [token, rango]),
  );

  if (respuesta === null || respuesta.rango !== rango) {
    return { fase: 'cargando' };
  }
  const { resultado } = respuesta;
  return resultado.ok ? { fase: 'lista', pedidos: resultado.datos } : { fase: 'error', falla: resultado };
}
