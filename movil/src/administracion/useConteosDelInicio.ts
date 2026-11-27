// Los dos conteos del inicio del módulo (administracion-app-E03; design.md del cambio, sección 5; imágenes 03 y 56 de la
// Propuesta 06): cuántos repartidores están pendientes de alta, de GET repartidores (los de estado pendiente;
// administracion-app-E19), y cuántos pedidos activos hay, de GET monitoreo (los que trae; administracion-app-E28). Sin
// ruta nueva: son las consultas de esas dos pantallas, con el cliente del módulo, así cada conteo es el que muestra su
// pantalla.
//
// Se piden cada vez que el inicio queda a la vista (useFocusEffect de expo-router), sin consulta periódica. Si una
// consulta falla, su conteo queda vacío y su entrada va sin insignia; el inicio sigue igual. Con un 401, el cliente del
// módulo ya cerró la sesión. Mientras llega la nueva, se queda la que había, y la respuesta de una consulta que ya no es
// la última, porque el inicio dejó de verse, no se pinta.
import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';

import { useApiDeLaAdministracion } from './api';
import { consultarElMonitoreo } from './monitoreo/api';
import { consultarRepartidores } from './repartidores/api';

export interface ConteosDelInicio {
  /** Los repartidores pendientes de alta; null mientras no llegan o si su consulta falló. */
  pendientes: number | null;
  /** Los pedidos activos; null mientras no llegan o si su consulta falló. */
  activos: number | null;
}

export function useConteosDelInicio(): ConteosDelInicio {
  const pedir = useApiDeLaAdministracion();
  const [pendientes, setPendientes] = useState<number | null>(null);
  const [activos, setActivos] = useState<number | null>(null);
  // La consulta vigente: cada vez que el inicio queda a la vista, y cada vez que deja de verse, toma un número nuevo.
  const vigente = useRef(0);

  const consultar = useCallback(() => {
    const esta = ++vigente.current;
    void consultarRepartidores(pedir).then((resultado) => {
      if (esta === vigente.current) {
        setPendientes(resultado.ok ? resultado.datos.filter(({ estado }) => estado === 'pendiente').length : null);
      }
    });
    void consultarElMonitoreo(pedir).then((resultado) => {
      if (esta === vigente.current) {
        setActivos(resultado.ok ? resultado.datos.pedidos.length : null);
      }
    });
    return () => {
      vigente.current++;
    };
  }, [pedir]);

  useFocusEffect(consultar);

  return { pendientes, activos };
}
