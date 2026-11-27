// Los pedidos activos de la lavandería para su pantalla (pedidos-lavanderia-E03), puestos al día cada 30 segundos
// (pedidos-lavanderia-E05 y E06; ADR-0007): es la "Lista en tiempo real" del contrato (NSV-2026-001-v8.pdf:17:ANEXO A,
// Sección A.3, Módulo B #2). La consulta corre solo mientras la pantalla está visible y la app al frente, y al volver a
// ella o al traer la app al frente consulta de inmediato, como el seguimiento del usuario
// (src/estados/useConsultaPeriodica.ts). La lista vive en ProveedorDePedidos, que comparte con la pantalla del rechazo.
//
// Después de una acción, la pantalla pone al día la tarjeta con el pedido que devolvió el servidor (reemplazar) y
// vuelve a consultar la lista (consultarDeNuevo): la respuesta de una consulta que salió antes ya no se pinta.
import { useMemo } from 'react';

import { useConsultaPeriodica } from '../estados/useConsultaPeriodica';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarPedidosDeLaLavanderia, type PedidoDeLaLavanderia } from './api';
import { usePedidosCompartidos, type EstadoDeLaLista } from './ProveedorDePedidos';

export interface ListaDeLaLavanderia extends EstadoDeLaLista {
  /** Pone en la lista el pedido como lo devolvió el servidor, en el lugar del que tenía. */
  reemplazar(pedido: PedidoDeLaLavanderia): void;
  /** Consulta ya y vuelve a contar 30 segundos desde ahí. */
  consultarDeNuevo(): void;
}

export function usePedidosDeLaLavanderia(): ListaDeLaLavanderia {
  const { token } = useSesion();
  const { pedidos, falla, recibir, reemplazar } = usePedidosCompartidos();

  // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
  const consulta = useMemo(() => (token ? () => consultarPedidosDeLaLavanderia(token) : null), [token]);
  const consultarDeNuevo = useConsultaPeriodica(consulta, recibir);

  return { pedidos, falla, reemplazar, consultarDeNuevo };
}
