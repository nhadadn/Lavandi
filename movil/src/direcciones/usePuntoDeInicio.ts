// Dónde se abre el mapa para marcar la dirección (direcciones-E04): en la ubicación del teléfono, que la persona ya dio
// para buscar lavanderías (cercanas-E03). Se lee solo cuando abre el mapa, una vez, y nunca se guarda: el punto que se
// guarda es el del pin, el que la persona deja sobre su puerta.
//
// El mapa no pide el permiso: el texto que lo explica es el de la búsqueda de lavanderías (permiso-de-ubicacion.json).
// Sin permiso, o si el teléfono no da su ubicación, no hay dónde abrirlo; la pantalla lo dice y deja escribir la
// dirección. Ese estado no tiene escenario propio.
import { useEffect, useState } from 'react';

import type { Punto } from '../cercanas/api';
import { leerPermiso, ubicacionActual } from '../cercanas/ubicacion';

/** cargando: se lee el permiso o la ubicación; listo: con el punto; sin-ubicacion: sin permiso o sin ubicación. */
export type PuntoDeInicio = { fase: 'cargando' } | { fase: 'listo'; punto: Punto } | { fase: 'sin-ubicacion' };

/** Lee la ubicación la primera vez que leer es true; antes, espera. */
export function usePuntoDeInicio(leer: boolean): PuntoDeInicio {
  const [estado, setEstado] = useState<PuntoDeInicio>({ fase: 'cargando' });

  useEffect(() => {
    if (!leer) {
      return;
    }
    let vigente = true;
    async function ubicar(): Promise<PuntoDeInicio> {
      if ((await leerPermiso()) !== 'concedido') {
        return { fase: 'sin-ubicacion' };
      }
      return { fase: 'listo', punto: await ubicacionActual() };
    }
    ubicar()
      .catch((): PuntoDeInicio => ({ fase: 'sin-ubicacion' }))
      .then((siguiente) => {
        if (vigente) {
          setEstado(siguiente);
        }
      });
    return () => {
      vigente = false;
    };
  }, [leer]);

  return estado;
}
