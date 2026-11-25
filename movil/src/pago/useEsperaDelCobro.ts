// La espera "Procesando pago" de un cobro (pago-E08): lo consulta en el servidor cada 5 segundos mientras siga pendiente
// y, a los 3 minutos sin respuesta, deja de esperar y ofrece volver a consultar (decisión 32; adenda del 2026-11-22 de
// ADR-0010). ADR-0007 sigue fijando 30 segundos para la pantalla del pedido: esta espera es corta y la persona la está
// mirando. Consulta con useConsultaPeriodica: solo con la pantalla visible y la app al frente, y al volver de nuevo al
// frente consulta de inmediato.
//
// - pagado: la pasarela confirmó el pago con su aviso y el pedido ya está Confirmado (pago-E18).
// - fallido: el último intento se rechazó (pago-E04); se vuelve a intentar con el mismo cobro.
// - vencido o cancelado: ya no se paga. Hoy no los pone nada (DD-056, DD-012 y DD-016).
// - 404: el cobro no existe o es de otra cuenta. Deja de consultar.
// - Sin conexión o con el servidor caído, sin escenario propio: como el seguimiento del pedido, la falla se dice y la
//   consulta sigue; la siguiente respuesta la quita.
//
// Solo lee: ninguna consulta le dice al servidor que ya se pagó (pago-E07).
import { useCallback, useEffect, useMemo, useState } from 'react';

import type { Falla } from '../configuracion/api';
import { useConsultaPeriodica } from '../estados/useConsultaPeriodica';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarCobro, type FallaDelCobro, type Cobro } from './api';

/** Decisión 32: cada 5 segundos. */
export const CADA_CUANTO_SE_CONSULTA_EL_COBRO_MS = 5_000;
/** Decisión 32: hasta 3 minutos. */
export const ESPERA_MAXIMA_DEL_COBRO_MS = 3 * 60_000;

export type FaseDeLaEspera =
  | { fase: 'esperando' }
  | { fase: 'en-revision' }
  | { fase: 'pagado'; pedido: string }
  | { fase: 'rechazado'; pedido: string }
  | { fase: 'ya-no-se-puede' }
  | { fase: 'no-encontrado' };

export function useEsperaDelCobro(cobro: string | undefined) {
  const { token } = useSesion();
  const [espera, setEspera] = useState<FaseDeLaEspera>({ fase: 'esperando' });
  // La falla de la última consulta mientras se espera; aparte, para que no reinicie la consulta.
  const [falla, setFalla] = useState<Falla | null>(null);
  const esperando = espera.fase === 'esperando';

  const consulta = useMemo(
    () => (esperando && token && cobro ? () => consultarCobro(token, cobro) : null),
    [esperando, token, cobro],
  );

  const alLlegar = useCallback((resultado: { ok: true; datos: Cobro } | FallaDelCobro) => {
    if (!resultado.ok) {
      if (resultado.motivo === 'no-encontrado') {
        setEspera({ fase: 'no-encontrado' });
      } else {
        setFalla(resultado);
      }
      return;
    }
    setFalla(null);
    const { estado, pedido } = resultado.datos;
    if (estado === 'pagado') {
      setEspera({ fase: 'pagado', pedido });
    } else if (estado === 'fallido') {
      setEspera({ fase: 'rechazado', pedido });
    } else if (estado === 'vencido' || estado === 'cancelado') {
      setEspera({ fase: 'ya-no-se-puede' });
    }
  }, []);

  useConsultaPeriodica(consulta, alLlegar, CADA_CUANTO_SE_CONSULTA_EL_COBRO_MS);

  // Los 3 minutos cuentan desde que empieza a esperar, y otra vez desde cada "volver a consultar".
  useEffect(() => {
    if (!esperando) {
      return;
    }
    const plazo = setTimeout(() => setEspera({ fase: 'en-revision' }), ESPERA_MAXIMA_DEL_COBRO_MS);
    return () => clearTimeout(plazo);
  }, [esperando]);

  /** Vuelve a esperar: consulta de inmediato y cuenta otros 3 minutos. */
  const volverAConsultar = useCallback(() => {
    setFalla(null);
    setEspera({ fase: 'esperando' });
  }, []);

  return { espera, falla: esperando ? falla : null, volverAConsultar };
}
