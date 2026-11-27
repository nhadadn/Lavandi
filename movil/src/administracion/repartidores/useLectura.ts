// Una lectura de la gestión de repartidores para su pantalla: la lista, la ficha o las plazas del alta. Se pide al
// servidor con el cliente de la administración al abrir la pantalla y cada vez que vuelve a verse (useFocusEffect de
// expo-router): al regresar de la ficha, la lista trae el estado nuevo, y al regresar de la edición, la ficha trae los
// datos nuevos y una URL nueva de la foto, que vence. Sin consulta periódica: el Módulo D #2 no la pide (ADR-0007).
//
// Mientras llega la nueva se queda la que había. La respuesta de una consulta que ya no es la última (porque salió otra,
// porque la pantalla dejó de verse o porque la pantalla puso lo que devolvió una acción) no se pinta.
import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';

import {
  useApiDeLaAdministracion,
  type FallaDeLaAdministracion,
  type PedirALaAdministracion,
  type ResultadoDeLaAdministracion,
} from '../api';

export interface Lectura<T> {
  /** Lo último que llegó; null mientras no llega nada. */
  datos: T | null;
  /** Por qué no llegó la última consulta; null si llegó o si todavía no responde. */
  falla: FallaDeLaAdministracion | null;
  /** Consulta ya, como "Intentar de nuevo" o después de un 409. */
  consultarDeNuevo(): void;
  /** Pone lo que devolvió una acción, como la ficha con el estado nuevo. */
  poner(datos: T): void;
}

/**
 * consultar debe ser estable (useCallback): si cambia, se consulta de nuevo. Sin consulta (null), como las plazas de un
 * repartidor que no se puede dar de alta, no pide nada.
 */
export function useLectura<T>(
  consultar: ((pedir: PedirALaAdministracion) => Promise<ResultadoDeLaAdministracion<T>>) | null,
): Lectura<T> {
  const pedir = useApiDeLaAdministracion();
  const [datos, setDatos] = useState<T | null>(null);
  const [falla, setFalla] = useState<FallaDeLaAdministracion | null>(null);
  // La consulta vigente: cada consulta, cada respuesta de una acción y cada salida de la pantalla toman un número nuevo.
  const vigente = useRef(0);

  // Consulta ya. Devuelve lo que hace al dejar de verse la pantalla: lo que llegue tarde ya no se pinta.
  const consultarYa = useCallback(() => {
    if (!consultar) {
      return undefined;
    }
    const esta = ++vigente.current;
    void consultar(pedir).then((resultado) => {
      if (esta !== vigente.current) {
        return;
      }
      if (resultado.ok) {
        setDatos(resultado.datos);
        setFalla(null);
      } else {
        setFalla(resultado);
      }
    });
    return () => {
      vigente.current++;
    };
  }, [consultar, pedir]);

  useFocusEffect(consultarYa);

  const consultarDeNuevo = useCallback(() => {
    setFalla(null);
    consultarYa();
  }, [consultarYa]);

  const poner = useCallback((nuevos: T) => {
    vigente.current++;
    setDatos(nuevos);
    setFalla(null);
  }, []);

  return { datos, falla, consultarDeNuevo, poner };
}
