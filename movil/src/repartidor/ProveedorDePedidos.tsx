// Los pedidos asignados del repartidor, compartidos por las pantallas del Módulo C: la de pedidos los consulta cada 30
// segundos y los pone al día con cada respuesta (repartidor-E10, E11 y E15); la del rechazo y la de la entrega leen de
// ella su pedido y, al terminar, lo quitan, porque ya no está en curso para él (repartidor-E16 y E23). Vive en
// app/repartidor/_layout.tsx: solo existe con la sesión de una cuenta de repartidor y se va con ella. Es la lista
// compartida de src/estados/listaCompartida.tsx, la misma forma que la de la lavandería.
import { crearListaCompartida, type ListaCompartida } from '../estados/listaCompartida';
import type { FallaDelRepartidor, PedidoDelRepartidor } from './api';

export type PedidosDelRepartidorCompartidos = ListaCompartida<PedidoDelRepartidor, FallaDelRepartidor>;

const { ProveedorDeLaLista, useListaCompartida } = crearListaCompartida<PedidoDelRepartidor, FallaDelRepartidor>(
  'usePedidosDelRepartidorCompartidos',
);

export const ProveedorDePedidosDelRepartidor = ProveedorDeLaLista;

/** La lista compartida. Solo funciona dentro de su proveedor, que está en app/repartidor/_layout.tsx. */
export const usePedidosDelRepartidorCompartidos = useListaCompartida;
