// Los repartidores para su lista (administracion-app-E19): todos, de todas las plazas y en cualquier estado, como los da
// GET repartidores, con los pendientes primero, que son los que esperan que la administración los autorice (imagen 14
// de la Propuesta 06); los demás, en el orden del servidor. El orden es solo de presentación (ADR-0022, decisión 9).
import { useMemo } from 'react';

import { consultarRepartidores, type RepartidorEnLaLista } from './api';
import { useLectura, type Lectura } from './useLectura';

export function useRepartidores(): Lectura<RepartidorEnLaLista[]> {
  const lectura = useLectura(consultarRepartidores);
  const ordenados = useMemo(() => (lectura.datos ? conLosPendientesPrimero(lectura.datos) : null), [lectura.datos]);
  return { ...lectura, datos: ordenados };
}

function conLosPendientesPrimero(repartidores: RepartidorEnLaLista[]): RepartidorEnLaLista[] {
  const pendientes = repartidores.filter(({ estado }) => estado === 'pendiente');
  const demas = repartidores.filter(({ estado }) => estado !== 'pendiente');
  return [...pendientes, ...demas];
}
