// Las descargas de la cuenta para la pantalla Descargas (administracion-app-E59): las vivas, de la más nueva a la más
// vieja, como las da GET /api/administracion/descargas. Se piden al abrir la pantalla y cada vez que vuelve a verse
// (useFocusEffect de expo-router), así aparece la que se pidió desde otra pantalla. Cada tarjeta que se genera consulta
// la suya cada 5 segundos (useEsperaDeLaDescarga) y la pone al día aquí con actualizar(). La que se pide otra vez desde
// una tarjeta entra al principio con agregar(), como la más nueva. Con la lista llegan los días que vive cada descarga.
//
// Mientras llega la nueva, se queda la que había. La respuesta de una consulta que ya no es la última (porque salió otra
// con "Intentar de nuevo" o porque la pantalla dejó de verse) no se pinta. Con un 401, el cliente del módulo cierra la
// sesión (administracion-app-E05).
import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';

import { useApiDeLaAdministracion, type FallaDeLaAdministracion } from '../api';
import { consultarLasDescargas, type Descarga } from './api';

export interface DescargasDeLaCuenta {
  /** null mientras llega la primera lista. */
  descargas: Descarga[] | null;
  /** Cuántos días vive cada descarga, como lo dice el servidor; null mientras llega la primera lista. */
  diasDeVida: number | null;
  /** La falla de la última consulta, o null. */
  falla: FallaDeLaAdministracion | null;
  /** Pone en la lista la descarga como la dio el servidor, en el lugar de la que tenía. */
  actualizar(descarga: Descarga): void;
  /** Pone al principio de la lista una descarga recién pedida, la más nueva. */
  agregar(descarga: Descarga): void;
  /** Consulta ya, con "Intentar de nuevo". */
  consultarDeNuevo(): void;
}

export function useDescargas(): DescargasDeLaCuenta {
  const pedir = useApiDeLaAdministracion();
  const [descargas, setDescargas] = useState<Descarga[] | null>(null);
  const [diasDeVida, setDiasDeVida] = useState<number | null>(null);
  const [falla, setFalla] = useState<FallaDeLaAdministracion | null>(null);
  // La consulta vigente: cada consulta y cada salida de la pantalla toman un número nuevo.
  const vigente = useRef(0);

  // Consulta ya. Devuelve lo que hace al dejar de verse la pantalla: lo que llegue tarde ya no se pinta.
  const consultarYa = useCallback(() => {
    const esta = ++vigente.current;
    void consultarLasDescargas(pedir).then((resultado) => {
      if (esta !== vigente.current) {
        return;
      }
      if (resultado.ok) {
        setDescargas(resultado.datos.descargas);
        setDiasDeVida(resultado.datos.dias_de_vida);
        setFalla(null);
      } else if (resultado.motivo !== 'sin-sesion') {
        setFalla(resultado);
      }
    });
    return () => {
      vigente.current++;
    };
  }, [pedir]);

  useFocusEffect(consultarYa);

  const consultarDeNuevo = useCallback(() => {
    setFalla(null);
    consultarYa();
  }, [consultarYa]);

  const actualizar = useCallback((descarga: Descarga) => {
    setDescargas((anteriores) =>
      anteriores?.map((otra) => (otra.id_publico === descarga.id_publico ? descarga : otra)) ?? anteriores,
    );
  }, []);

  const agregar = useCallback((descarga: Descarga) => {
    setDescargas((anteriores) => [
      descarga,
      ...(anteriores ?? []).filter((otra) => otra.id_publico !== descarga.id_publico),
    ]);
  }, []);

  return { descargas, diasDeVida, falla, actualizar, agregar, consultarDeNuevo };
}
