// La lista de pedidos de la lavandería, compartida por las pantallas del Módulo B: la de pedidos la consulta cada 30
// segundos y la pone al día con cada respuesta (pedidos-lavanderia-E03, E05 y E07); la del rechazo lee de ella el
// pedido que se rechaza y, al rechazarlo, lo quita, para que no vuelva a verse en la lista (pedidos-lavanderia-E12).
// Vive en app/lavanderia/_layout.tsx: solo existe con la sesión de una cuenta de socio lavandería y se va con ella. Es la
// lista compartida de src/estados/listaCompartida.tsx, la misma forma que la del repartidor.
import type { Falla } from '../configuracion/api';
import {
  crearListaCompartida,
  type EstadoDeLaLista as EstadoDeUnaLista,
  type ListaCompartida,
} from '../estados/listaCompartida';
import type { PedidoDeLaLavanderia } from './api';

export type EstadoDeLaLista = EstadoDeUnaLista<PedidoDeLaLavanderia, Falla>;

export type PedidosCompartidos = ListaCompartida<PedidoDeLaLavanderia, Falla>;

const { ProveedorDeLaLista, useListaCompartida } = crearListaCompartida<PedidoDeLaLavanderia, Falla>(
  'usePedidosCompartidos',
);

export const ProveedorDePedidos = ProveedorDeLaLista;

/** La lista compartida. Solo funciona dentro de ProveedorDePedidos, que está en app/lavanderia/_layout.tsx. */
export const usePedidosCompartidos = useListaCompartida;
