// Una lista de pedidos compartida por las pantallas de un módulo de socio: la de pedidos la consulta cada 30 segundos y
// la pone al día con cada respuesta (ADR-0007); las de una acción con su propia pantalla, como el rechazo con motivo,
// leen de ella el pedido y, al terminar, lo quitan o lo reemplazan, para que al volver ya se vea como quedó. La usan la
// lista de la lavandería (pedidos-lavanderia-E03, E05, E07 y E12) y la del repartidor (repartidor-E10, E11, E16 y E23).
//
// Cada módulo crea la suya con crearListaCompartida y pone su proveedor en el layout del módulo: solo existe con la
// sesión de su cuenta y se va con ella.
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

export interface EstadoDeLaLista<P, F> {
  /** La última lista que llegó; null hasta la primera respuesta. */
  pedidos: P[] | null;
  /** Por qué no llegó la última consulta; null si llegó. */
  falla: F | null;
}

export interface ListaCompartida<P, F> extends EstadoDeLaLista<P, F> {
  /**
   * La respuesta de una consulta de la lista: con la lista, la pone; si no llegó, conserva la última con el motivo,
   * para que la pantalla lo diga. La siguiente que llega lo quita.
   */
  recibir(resultado: { ok: true; datos: P[] } | F): void;
  /** Pone en la lista el pedido como lo devolvió el servidor, en el lugar del que tenía. */
  reemplazar(pedido: P): void;
  /** Quita de la lista un pedido que ya no está en ella, como uno rechazado. */
  quitar(idPublico: string): void;
}

/** La lista de un módulo: su proveedor, para el layout, y el hook con que la leen sus pantallas. */
export function crearListaCompartida<P extends { id_publico: string }, F extends { ok: false }>(nombre: string) {
  const Contexto = createContext<ListaCompartida<P, F> | null>(null);

  function ProveedorDeLaLista({ children }: { children: ReactNode }) {
    const [estado, setEstado] = useState<EstadoDeLaLista<P, F>>({ pedidos: null, falla: null });

    const recibir = useCallback((resultado: { ok: true; datos: P[] } | F) => {
      setEstado((anterior) =>
        resultado.ok ? { pedidos: resultado.datos, falla: null } : { pedidos: anterior.pedidos, falla: resultado },
      );
    }, []);

    const reemplazar = useCallback((pedido: P) => {
      setEstado((anterior) => ({
        ...anterior,
        pedidos: anterior.pedidos?.map((otro) => (otro.id_publico === pedido.id_publico ? pedido : otro)) ?? null,
      }));
    }, []);

    const quitar = useCallback((idPublico: string) => {
      setEstado((anterior) => ({
        ...anterior,
        pedidos: anterior.pedidos?.filter((pedido) => pedido.id_publico !== idPublico) ?? null,
      }));
    }, []);

    const compartida = useMemo<ListaCompartida<P, F>>(
      () => ({ ...estado, recibir, reemplazar, quitar }),
      [estado, recibir, reemplazar, quitar],
    );

    return <Contexto.Provider value={compartida}>{children}</Contexto.Provider>;
  }

  function useListaCompartida(): ListaCompartida<P, F> {
    const compartida = useContext(Contexto);
    if (!compartida) {
      throw new Error(`${nombre} se usa dentro de su proveedor, en el layout del módulo.`);
    }
    return compartida;
  }

  return { ProveedorDeLaLista, useListaCompartida };
}
