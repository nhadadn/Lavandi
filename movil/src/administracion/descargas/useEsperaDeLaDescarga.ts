// La espera de una descarga que se genera (administracion-app-E57): mientras su estado es "generandose", consulta
// GET /api/administracion/descargas/{id_publico} cada 5 segundos y entrega cada descarga que llega, hasta que está lista
// o no se pudo (ADR-0022, decisión 15). Consulta con useConsultaPeriodica, como "Procesando pago" (adenda del 2026-11-22
// de ADR-0010): solo con la pantalla visible y la app al frente, y al volver a ella consulta de inmediato. La usan la
// pieza que pide la descarga y cada tarjeta de la pantalla Descargas.
//
// - Sin conexión o con el servidor caído, la falla se dice y la consulta sigue; la siguiente respuesta la quita, como
//   la espera del cobro (src/pago/useEsperaDelCobro.ts).
// - 404: la descarga ya no existe (se borró a sus días de vida). Deja de consultar y dice el mensaje del servidor.
// - 401: el cliente del módulo cierra la sesión (administracion-app-E05); no hay nada que decir.
import { useCallback, useMemo, useState } from 'react';

import { useConsultaPeriodica } from '../../estados/useConsultaPeriodica';
import { useApiDeLaAdministracion, type FallaDeLaAdministracion, type ResultadoDeLaAdministracion } from '../api';
import { consultarLaDescarga, type Descarga } from './api';
import { CADA_CUANTO_SE_CONSULTA_LA_DESCARGA_MS } from './textos';

interface Respuesta {
  idPublico: string;
  resultado: ResultadoDeLaAdministracion<{ descarga: Descarga }>;
}

/**
 * Espera la descarga mientras se genera y entrega a alLlegar cada una que llega, que debe ser estable (useCallback).
 * Devuelve la falla de la última consulta de esa descarga, o null.
 */
export function useEsperaDeLaDescarga(
  descarga: Descarga | null,
  alLlegar: (descarga: Descarga) => void,
): FallaDeLaAdministracion | null {
  const pedir = useApiDeLaAdministracion();
  // La falla de la última consulta, con la descarga a la que pertenece: otra descarga empieza sin falla.
  const [ultima, setUltima] = useState<{ idPublico: string; falla: FallaDeLaAdministracion } | null>(null);
  const idPublico = descarga?.estado === 'generandose' ? descarga.id_publico : null;
  const falla = ultima && ultima.idPublico === idPublico ? ultima.falla : null;
  const yaNoExiste = falla?.motivo === 'no-existe';

  const consulta = useMemo(
    () =>
      idPublico && !yaNoExiste
        ? async (): Promise<Respuesta> => ({ idPublico, resultado: await consultarLaDescarga(pedir, idPublico) })
        : null,
    [idPublico, yaNoExiste, pedir],
  );

  const alResponder = useCallback(
    ({ idPublico: deLaDescarga, resultado }: Respuesta) => {
      if (resultado.ok) {
        setUltima(null);
        alLlegar(resultado.datos.descarga);
      } else if (resultado.motivo !== 'sin-sesion') {
        setUltima({ idPublico: deLaDescarga, falla: resultado });
      }
    },
    [alLlegar],
  );

  useConsultaPeriodica(consulta, alResponder, CADA_CUANTO_SE_CONSULTA_LA_DESCARGA_MS);

  return falla;
}
