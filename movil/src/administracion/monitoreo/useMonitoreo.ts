// Los pedidos activos y sus marcadores para la pantalla del monitoreo (administracion-app-E28), puestos al día cada 30
// segundos (administracion-app-E29; ADR-0007; ADR-0022, decisión 12): es la "Vista en tiempo real" del contrato
// (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #4). La consulta corre solo mientras la pantalla está visible y
// la app al frente, y al volver a ella o al traer la app al frente consulta de inmediato, con el gancho de la app
// (src/estados/useConsultaPeriodica.ts), como las listas de pedidos de la lavandería y del repartidor.
//
// La lista y el mapa salen de la misma respuesta. Si una consulta no llega, se queda la última que llegó y se dice por
// qué; la siguiente que llega lo quita. Con un 401, el cliente de la administración ya cerró la sesión y abrió el inicio
// de sesión (administracion-app-E05): no hay nada que mostrar.
import { useCallback, useState } from 'react';

import { useConsultaPeriodica } from '../../estados/useConsultaPeriodica';
import { useApiDeLaAdministracion, type FallaDeLaAdministracion, type ResultadoDeLaAdministracion } from '../api';
import { consultarElMonitoreo, type Monitoreo } from './api';

export interface ElMonitoreo {
  /** Lo último que llegó; null mientras no llega la primera respuesta. */
  monitoreo: Monitoreo | null;
  /** Por qué no llegó la última consulta; null si llegó o si todavía no responde. */
  falla: FallaDeLaAdministracion | null;
  /** "Intentar de nuevo": consulta ya y vuelve a contar 30 segundos desde ahí. */
  consultarDeNuevo(): void;
}

export function useMonitoreo(): ElMonitoreo {
  // No cambia mientras siga la misma sesión, así que la consulta tampoco: el ciclo de 30 segundos no empieza de nuevo.
  const pedir = useApiDeLaAdministracion();
  const [monitoreo, setMonitoreo] = useState<Monitoreo | null>(null);
  const [falla, setFalla] = useState<FallaDeLaAdministracion | null>(null);

  const consulta = useCallback(() => consultarElMonitoreo(pedir), [pedir]);
  const alLlegar = useCallback((resultado: ResultadoDeLaAdministracion<Monitoreo>) => {
    if (resultado.ok) {
      setMonitoreo(resultado.datos);
      setFalla(null);
    } else if (resultado.motivo !== 'sin-sesion') {
      setFalla(resultado);
    }
  }, []);
  const reiniciar = useConsultaPeriodica(consulta, alLlegar);

  const consultarDeNuevo = useCallback(() => {
    setFalla(null);
    reiniciar();
  }, [reiniciar]);

  return { monitoreo, falla, consultarDeNuevo };
}
