// Una lectura del Módulo C para su pantalla: el historial (repartidor-E25) y las estadísticas (repartidor-E28). Es la
// lectura de las pantallas de la lavandería (src/historial-lavanderia/useLecturaDeLaLavanderia.ts): se pide al abrir la
// pantalla y cada vez que vuelve a verse, sin consulta periódica, que el contrato pide solo para la lista de pedidos
// (Módulo C #2; ADR-0007). Si responde que el repartidor ya no opera (403, repartidor-E03 y E06), se vuelve a pedir su
// perfil y el módulo lo lleva a la espera.
import { useEffect } from 'react';

import { useLecturaDeLaLavanderia, type EstadoDeLaLectura } from '../historial-lavanderia/useLecturaDeLaLavanderia';
import type { FallaDelRepartidor, ResultadoDelRepartidor } from './api';
import { useRepartidorDeLaCuenta } from './ProveedorDelRepartidor';

/** consultar debe ser estable: una función del módulo, no una creada en cada render. */
export function useLecturaDelRepartidor<T>(
  consultar: (token: string) => Promise<ResultadoDelRepartidor<T>>,
): EstadoDeLaLectura<T, FallaDelRepartidor> {
  const { leerDeNuevo } = useRepartidorDeLaCuenta();
  const estado = useLecturaDeLaLavanderia<T, FallaDelRepartidor>(consultar);
  const sinAcceso = estado.fase === 'error' && estado.falla.motivo === 'sin-acceso';

  useEffect(() => {
    if (sinAcceso) {
      leerDeNuevo();
    }
  }, [sinAcceso, leerDeNuevo]);

  return estado;
}
