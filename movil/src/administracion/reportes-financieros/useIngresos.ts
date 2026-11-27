// Los ingresos del periodo para su pantalla (administracion-app-E37): se piden al servidor con el cliente de la
// administración al abrir la pantalla, sin rango, y cada vez que se aplica uno en la hoja del rango. Sin consulta
// periódica ni al volver a la pantalla: los reportes financieros no se ponen al día solos, como en el panel (design.md,
// F-D06).
//
// Mientras llegan los nuevos se quedan los que había, con el rango que los pidió. La respuesta de una consulta que ya no
// es la última, porque se aplicó otro rango o la pantalla se cerró, no se pinta.
import { useCallback, useEffect, useState } from 'react';

import { useApiDeLaAdministracion, type FallaDeLaAdministracion } from '../api';
import { diaParaElServidor, type RangoElegido } from '../rango/rango';
import { consultarIngresos, type FiltrosDeLosIngresos, type IngresosDelPeriodo } from './api';

export interface LecturaDeLosIngresos {
  /** Los últimos que llegaron, con el rango elegido que los pidió (null: el que dio el servidor); null mientras no llega nada. */
  cifras: { ingresos: IngresosDelPeriodo; pedidosCon: RangoElegido | null } | null;
  /** Por qué no llegó la última consulta; null si llegó o si todavía no responde. */
  falla: FallaDeLaAdministracion | null;
  /** El rango que eligió la administración; null mientras usa el del servidor. */
  elegido: RangoElegido | null;
  /** Pide los ingresos de otro rango. */
  aplicar(rango: RangoElegido): void;
  /** Pide otra vez los del rango en uso, como "Intentar de nuevo". */
  consultarDeNuevo(): void;
}

function filtrosDe(rango: RangoElegido): FiltrosDeLosIngresos {
  return {
    desde: diaParaElServidor(rango.desde),
    hasta: diaParaElServidor(rango.hasta),
    granularidad: rango.granularidad,
  };
}

export function useIngresos(): LecturaDeLosIngresos {
  const pedir = useApiDeLaAdministracion();
  const [elegido, setElegido] = useState<RangoElegido | null>(null);
  // Cada "Intentar de nuevo" pide otra vez, aunque el rango sea el mismo.
  const [vuelta, setVuelta] = useState(0);
  const [cifras, setCifras] = useState<LecturaDeLosIngresos['cifras']>(null);
  const [falla, setFalla] = useState<FallaDeLaAdministracion | null>(null);

  useEffect(() => {
    let vigente = true;
    void consultarIngresos(pedir, elegido && filtrosDe(elegido)).then((resultado) => {
      if (!vigente) {
        return;
      }
      if (resultado.ok) {
        setCifras({ ingresos: resultado.datos, pedidosCon: elegido });
        setFalla(null);
      } else {
        setFalla(resultado);
      }
    });
    return () => {
      vigente = false;
    };
  }, [pedir, elegido, vuelta]);

  const aplicar = useCallback((rango: RangoElegido) => {
    setFalla(null);
    setElegido(rango);
  }, []);

  const consultarDeNuevo = useCallback(() => {
    setFalla(null);
    setVuelta((anterior) => anterior + 1);
  }, []);

  return { cifras, falla, elegido, aplicar, consultarDeNuevo };
}
