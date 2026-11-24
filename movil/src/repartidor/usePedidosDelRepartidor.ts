// Los pedidos asignados del repartidor para su pantalla (repartidor-E10), puestos al día cada 30 segundos (repartidor-E11;
// ADR-0007): es la "Lista de pedidos asignados en tiempo real" del contrato (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3,
// Módulo C #2). La consulta corre solo mientras la pantalla está visible y la app al frente, y al volver a ella o al traer
// la app al frente consulta de inmediato, como la lista de la lavandería (src/estados/useConsultaPeriodica.ts).
//
// Si la consulta responde que el repartidor ya no opera (403, repartidor-E03 y E06), como al suspenderlo con la lista
// abierta, se vuelve a pedir su perfil: el módulo lo lleva a la espera, la pantalla se cierra y la consulta se detiene.
import { useEffect, useMemo } from 'react';

import { useConsultaPeriodica } from '../estados/useConsultaPeriodica';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarPedidosDelRepartidor, type PedidoDelRepartidor } from './api';
import { usePedidosDelRepartidorCompartidos } from './ProveedorDePedidos';
import { useRepartidorDeLaCuenta } from './ProveedorDelRepartidor';

export interface ListaDelRepartidor {
  pedidos: PedidoDelRepartidor[] | null;
  falla: ReturnType<typeof usePedidosDelRepartidorCompartidos>['falla'];
  /** Pone en la lista el pedido como lo devolvió el servidor, en el lugar del que tenía. */
  reemplazar(pedido: PedidoDelRepartidor): void;
  /** Consulta ya y vuelve a contar 30 segundos desde ahí. */
  consultarDeNuevo(): void;
}

export function usePedidosDelRepartidor(): ListaDelRepartidor {
  const { token } = useSesion();
  const { leerDeNuevo } = useRepartidorDeLaCuenta();
  const { pedidos, falla, recibir, reemplazar } = usePedidosDelRepartidorCompartidos();

  // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
  const consulta = useMemo(() => (token ? () => consultarPedidosDelRepartidor(token) : null), [token]);
  const consultarDeNuevo = useConsultaPeriodica(consulta, recibir);

  useEffect(() => {
    if (falla?.motivo === 'sin-acceso') {
      leerDeNuevo();
    }
  }, [falla, leerDeNuevo]);

  return { pedidos, falla, reemplazar, consultarDeNuevo };
}
