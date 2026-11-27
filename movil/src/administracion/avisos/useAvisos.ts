// Los avisos enviados para su lista (administracion-app-E43): se piden al servidor con el cliente de la administración
// al abrir la pantalla y cada vez que vuelve a verse (useFocusEffect de expo-router), así, al volver de "Escribir
// aviso", el que se acaba de mandar sale arriba, "En cola". Sin consulta periódica: el Módulo D #7 no la pide
// (ADR-0007). El orden es el del servidor, del más reciente al más antiguo.
//
// Mientras llega la nueva se queda la que había. La respuesta de una consulta que ya no es la última (porque salió otra
// o porque la pantalla dejó de verse) no se pinta.
import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';

import { useApiDeLaAdministracion, type FallaDeLaAdministracion } from '../api';
import { consultarAvisos, type AvisoMasivo } from './api';

export interface AvisosEnviados {
  /** Los avisos que llegaron la última vez; null mientras no llega nada. */
  avisos: AvisoMasivo[] | null;
  /** Por qué no llegó la última consulta; null si llegó o si todavía no responde. */
  falla: FallaDeLaAdministracion | null;
  /** Consulta ya, con "Intentar de nuevo". */
  consultarDeNuevo(): void;
}

export function useAvisos(): AvisosEnviados {
  const pedir = useApiDeLaAdministracion();
  const [avisos, setAvisos] = useState<AvisoMasivo[] | null>(null);
  const [falla, setFalla] = useState<FallaDeLaAdministracion | null>(null);
  // La consulta vigente: cada consulta y cada salida de la pantalla toman un número nuevo.
  const vigente = useRef(0);

  // Consulta ya. Devuelve lo que hace al dejar de verse la pantalla: lo que llegue tarde ya no se pinta.
  const consultarYa = useCallback(() => {
    const esta = ++vigente.current;
    void consultarAvisos(pedir).then((resultado) => {
      if (esta !== vigente.current) {
        return;
      }
      if (resultado.ok) {
        setAvisos(resultado.datos);
        setFalla(null);
      } else {
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

  return { avisos, falla, consultarDeNuevo };
}
