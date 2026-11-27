// Una lectura del Módulo B para su pantalla: el historial (historial-lavanderia-E01) y las estadísticas
// (historial-lavanderia-E05). Se pide al servidor con la sesión al abrir la pantalla y cada vez que vuelve a verse, para
// que lo que se entregó mientras tanto aparezca (useFocusEffect de expo-router). Sin consulta periódica: el contrato pide
// "en tiempo real" solo la lista de pedidos activos (Módulo B #2; ADR-0007).
//
// Mientras llega la nueva, se queda la que había; si llegara una respuesta después de salir de la pantalla, no se pinta.
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import type { Falla } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';

/**
 * cargando: se pide al servidor; lista: llegó; error: no llegó. La falla es la general de la app, salvo que la pantalla
 * distinga otras, como la del repartidor que ya no opera (src/repartidor/useLecturaDelRepartidor.ts).
 */
export type EstadoDeLaLectura<T, F = Falla> = { fase: 'cargando' } | { fase: 'lista'; datos: T } | { fase: 'error'; falla: F };

/** consultar debe ser estable: una función del módulo, no una creada en cada render. */
export function useLecturaDeLaLavanderia<T, F extends { ok: false } = Falla>(
  consultar: (token: string) => Promise<{ ok: true; datos: T } | F>,
): EstadoDeLaLectura<T, F> {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDeLaLectura<T, F>>({ fase: 'cargando' });

  useFocusEffect(
    useCallback(() => {
      // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
      if (!token) {
        return;
      }
      let vigente = true;
      consultar(token).then((resultado) => {
        if (vigente) {
          setEstado(resultado.ok ? { fase: 'lista', datos: resultado.datos } : { fase: 'error', falla: resultado });
        }
      });
      return () => {
        vigente = false;
      };
    }, [token, consultar]),
  );

  return estado;
}
