// La lista de lavanderías de la administración, con la búsqueda por nombre (administracion-app-E13). La búsqueda la hace
// el servidor (GET /api/administracion/lavanderias?buscar=…): cada texto nuevo vuelve a pedir la lista, y una respuesta
// que llega después de otro texto no se pinta. Se pide al abrir la pantalla y cada vez que vuelve a verse, para que el
// estado que cambió en una ficha aparezca al regresar (useFocusEffect de expo-router), sin consulta periódica: el
// contrato pide "en tiempo real" solo el monitoreo (ADR-0007). Mientras llega la nueva, se queda la que había.
//
// Un 401 lo atiende el cliente del módulo, que ya llevó al inicio de sesión: no hay nada que mostrar.
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { useApiDeLaAdministracion, type FallaDeLaAdministracion } from '../api';
import { consultarLavanderias, type LavanderiaEnLaLista } from './api';

/** cargando: se pide al servidor; lista: llegó; error: no llegó. */
export type EstadoDeLaLista =
  | { fase: 'cargando' }
  | { fase: 'lista'; lavanderias: LavanderiaEnLaLista[] }
  | { fase: 'error'; falla: FallaDeLaAdministracion };

export function useLavanderias() {
  const pedir = useApiDeLaAdministracion();
  const [buscar, setBuscar] = useState('');
  const [estado, setEstado] = useState<EstadoDeLaLista>({ fase: 'cargando' });
  // Cada intento nuevo vuelve a correr la consulta.
  const [intento, setIntento] = useState(0);

  useFocusEffect(
    useCallback(() => {
      let vigente = true;
      consultarLavanderias(pedir, buscar).then((resultado) => {
        if (!vigente || (!resultado.ok && resultado.motivo === 'sin-sesion')) {
          return;
        }
        setEstado(resultado.ok ? { fase: 'lista', lavanderias: resultado.datos } : { fase: 'error', falla: resultado });
      });
      return () => {
        vigente = false;
      };
      // intento solo dispara la consulta otra vez.
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pedir, buscar, intento]),
  );

  const reintentar = useCallback(() => {
    setEstado({ fase: 'cargando' });
    setIntento((anterior) => anterior + 1);
  }, []);

  return { buscar, setBuscar, estado, reintentar };
}
