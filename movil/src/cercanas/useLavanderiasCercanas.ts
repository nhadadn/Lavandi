// El recorrido de la búsqueda de lavanderías cercanas: el permiso de ubicación, la ubicación del teléfono y la consulta
// con la sesión (cercanas-E03 y E04). Con un punto dado, como el que la pantalla principal le pasa a la lista de
// "Pedido nuevo" (cercanas-E15), consulta con él sin volver a leer la ubicación. Con filtros (cercanas-E21), la consulta
// los lleva, y al cambiarlos se vuelve a consultar.
import { useCallback, useEffect, useState } from 'react';

import type { Falla } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarCercanas, type FiltrosDeCercanas, type LavanderiaCercana, type Punto } from './api';
import { leerPermiso, pedirPermiso, ubicacionActual } from './ubicacion';

/**
 * cargando: lee el permiso o la ubicación; por-pedir: falta el permiso y se puede pedir (cercanas-E03); sin-permiso:
 * la persona no lo dio (cercanas-E05, bloqueado por DD-083); sin-ubicacion: el teléfono no dio su ubicación;
 * consultando, lista y error: la consulta con el punto.
 */
export type EstadoDeCercanas =
  | { fase: 'cargando' }
  | { fase: 'por-pedir' }
  | { fase: 'sin-permiso' }
  | { fase: 'sin-ubicacion' }
  | { fase: 'consultando'; punto: Punto }
  | { fase: 'lista'; punto: Punto; lavanderias: LavanderiaCercana[] }
  | { fase: 'error'; punto: Punto; falla: Falla };

export interface BusquedaDeCercanas {
  estado: EstadoDeCercanas;
  /** Pide el permiso de ubicación: lo que hace "Continuar" (cercanas-E03). */
  permitir: () => void;
}

export function useLavanderiasCercanas(
  puntoDado: Punto | null = null,
  filtros: FiltrosDeCercanas = {},
): BusquedaDeCercanas {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDeCercanas>({ fase: 'cargando' });
  const [pedirElPermiso, setPedirElPermiso] = useState(false);
  // Por sus números y no por el objeto, que cambia en cada render.
  const latitudDada = puntoDado?.latitud;
  const longitudDada = puntoDado?.longitud;
  const distanciaMaximaMetros = filtros.distanciaMaximaMetros ?? null;
  const calificacionMinima = filtros.calificacionMinima ?? null;

  useEffect(() => {
    // Las pantallas que lo usan solo se ven con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token) {
      return;
    }
    let vigente = true;
    const poner = (siguiente: EstadoDeCercanas) => {
      if (vigente) {
        setEstado(siguiente);
      }
    };

    async function buscar(conToken: string) {
      let punto: Punto;
      if (latitudDada !== undefined && longitudDada !== undefined) {
        punto = { latitud: latitudDada, longitud: longitudDada };
      } else {
        const permiso = pedirElPermiso ? await pedirPermiso() : await leerPermiso();
        if (permiso !== 'concedido') {
          poner({ fase: permiso });
          return;
        }
        poner({ fase: 'cargando' });
        punto = await ubicacionActual();
      }
      poner({ fase: 'consultando', punto });
      const resultado = await consultarCercanas(conToken, punto, { distanciaMaximaMetros, calificacionMinima });
      poner(
        resultado.ok ? { fase: 'lista', punto, lavanderias: resultado.datos } : { fase: 'error', punto, falla: resultado },
      );
    }

    // Solo el permiso y la ubicación pueden lanzar (la consulta devuelve su falla): el teléfono no dio la ubicación.
    buscar(token).catch(() => poner({ fase: 'sin-ubicacion' }));
    return () => {
      vigente = false;
    };
  }, [token, pedirElPermiso, latitudDada, longitudDada, distanciaMaximaMetros, calificacionMinima]);

  const permitir = useCallback(() => setPedirElPermiso(true), []);

  return { estado, permitir };
}
