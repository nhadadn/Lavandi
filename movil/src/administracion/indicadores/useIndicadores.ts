// Los indicadores de la pestaña que se ve, puestos al día solos (administracion-app-E45 y E51; ADR-0022, decisión 14;
// adenda del 2026-11-22 de ADR-0007): se pide solo la categoría visible, con el rango y la granularidad elegidos (o sin
// ellos, el rango del servidor) y, en la geográfica, la agrupación. La consulta corre con el gancho de la app
// (src/estados/useConsultaPeriodica.ts), solo con la pantalla visible y la app al frente, cada tanto como diga el
// intervalo que trae la respuesta (indicadores.refresco; 30 s por omisión):
//
// - al abrir, la primera consulta sale con los 30 s de omisión; si la respuesta trae otro intervalo, la consulta empieza
//   de nuevo con él, así que sale una consulta más en ese momento;
// - si la respuesta no trae intervalo (null: la configuración no tiene uno que sirva), la pantalla no se consulta sola:
//   pide al abrirse o al volver a ella, al cambiar de pestaña, de rango o de agrupación, y con "Intentar de nuevo". La
//   respuesta que dijo que no hay intervalo ya es la de lo que se ve: no se pide otra vez lo mismo en ese momento;
// - cambiar de pestaña, de rango o de agrupación consulta de inmediato y vuelve a contar desde ahí; la respuesta que
//   todavía venía en camino para lo anterior ya no se pinta.
//
// Mientras llega la primera respuesta de una pestaña no hay nada que mostrar de ella. Con una consulta que no llega, se
// queda lo último que llegó y se dice por qué; la siguiente que llega lo quita. Con un 401, el cliente de la
// administración ya cerró la sesión y abrió el inicio de sesión (administracion-app-E05).
import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';

import { CADA_CUANTO_SE_CONSULTA_MS, useConsultaPeriodica } from '../../estados/useConsultaPeriodica';
import { useApiDeLaAdministracion, type FallaDeLaAdministracion, type ResultadoDeLaAdministracion } from '../api';
import { diaParaElServidor, type RangoElegido } from '../rango/rango';
import {
  consultarIndicadores,
  type Agrupacion,
  type Categoria,
  type FiltrosDeLosIndicadores,
  type IndicadoresDeLaCategoria,
} from './api';

/** Con qué se pidió una respuesta: la categoría, el rango elegido (null: el del servidor) y la agrupación. */
export interface PedidaCon {
  categoria: Categoria;
  elegido: RangoElegido | null;
  agrupacion: Agrupacion;
}

/** La última respuesta que llegó, con lo que la pidió. */
export interface UltimaLectura {
  indicadores: IndicadoresDeLaCategoria;
  pedidaCon: PedidaCon;
}

export interface LosIndicadores {
  /** La pestaña que se ve. */
  categoria: Categoria;
  elegirCategoria(categoria: Categoria): void;
  /** El rango que eligió la administración; null mientras usa el del servidor. */
  elegido: RangoElegido | null;
  aplicar(rango: RangoElegido): void;
  /** Por colonia o por zona de servicio, en la pestaña geográfica. */
  agrupacion: Agrupacion;
  elegirAgrupacion(agrupacion: Agrupacion): void;
  /** Lo último que llegó, de cualquier pestaña; null mientras no llega la primera respuesta. */
  ultima: UltimaLectura | null;
  /** Por qué no llegó la última consulta; null si llegó o si todavía no responde. */
  falla: FallaDeLaAdministracion | null;
  /** "Intentar de nuevo": consulta ya y vuelve a contar desde ahí. */
  consultarDeNuevo(): void;
}

interface Llegada {
  resultado: ResultadoDeLaAdministracion<IndicadoresDeLaCategoria>;
  pedidaCon: PedidaCon;
}

function mismaPedida(una: PedidaCon, otra: PedidaCon): boolean {
  return una.categoria === otra.categoria && una.elegido === otra.elegido && una.agrupacion === otra.agrupacion;
}

function filtrosDe(rango: RangoElegido): FiltrosDeLosIndicadores {
  return {
    desde: diaParaElServidor(rango.desde),
    hasta: diaParaElServidor(rango.hasta),
    granularidad: rango.granularidad,
  };
}

export function useIndicadores(): LosIndicadores {
  // No cambia mientras siga la misma sesión: la consulta solo cambia con la pestaña, el rango o la agrupación.
  const pedir = useApiDeLaAdministracion();
  const [categoria, setCategoria] = useState<Categoria>('operaciones');
  const [elegido, setElegido] = useState<RangoElegido | null>(null);
  const [agrupacion, setAgrupacion] = useState<Agrupacion>('colonia');
  const [ultima, setUltima] = useState<UltimaLectura | null>(null);
  const [falla, setFalla] = useState<FallaDeLaAdministracion | null>(null);
  // El intervalo de la última respuesta, en milisegundos; null: no se consulta sola.
  const [intervalo, setIntervalo] = useState<number | null>(CADA_CUANTO_SE_CONSULTA_MS);
  // Sin intervalo, cada "Intentar de nuevo" pide otra vez.
  const [vuelta, setVuelta] = useState(0);
  // Lo que pidió la consulta periódica cuya respuesta dijo que no hay intervalo: esa respuesta ya está a la vista.
  const sinIntervaloDesde = useRef<PedidaCon | null>(null);

  const consulta = useCallback(async (): Promise<Llegada> => {
    const pedidaCon = { categoria, elegido, agrupacion };
    const resultado = await consultarIndicadores(pedir, categoria, elegido && filtrosDe(elegido), agrupacion);
    return { resultado, pedidaCon };
  }, [pedir, categoria, elegido, agrupacion]);

  const alLlegar = useCallback(({ resultado, pedidaCon }: Llegada) => {
    if (resultado.ok) {
      setUltima({ indicadores: resultado.datos, pedidaCon });
      setFalla(null);
      setIntervalo(resultado.datos.refresco?.milisegundos ?? null);
    } else if (resultado.motivo !== 'sin-sesion') {
      setFalla(resultado);
    }
  }, []);

  const alLlegarLaPeriodica = useCallback(
    (llegada: Llegada) => {
      const { resultado, pedidaCon } = llegada;
      sinIntervaloDesde.current = resultado.ok && resultado.datos.refresco === null ? pedidaCon : null;
      alLlegar(llegada);
    },
    [alLlegar],
  );

  const reiniciar = useConsultaPeriodica(
    intervalo === null ? null : consulta,
    alLlegarLaPeriodica,
    intervalo ?? CADA_CUANTO_SE_CONSULTA_MS,
  );

  // Sin intervalo, una sola consulta cada vez que la pantalla queda a la vista o cambia lo que se pide. Justo después de
  // la respuesta periódica que quitó el intervalo, no: ya es la de lo que se pide. Después, sí.
  useFocusEffect(
    useCallback(() => {
      if (intervalo !== null) {
        return;
      }
      const recienLlegada = sinIntervaloDesde.current;
      sinIntervaloDesde.current = null;
      if (recienLlegada !== null && mismaPedida(recienLlegada, { categoria, elegido, agrupacion })) {
        return;
      }
      let vigente = true;
      void consulta().then((llegada) => {
        if (vigente) {
          alLlegar(llegada);
        }
      });
      return () => {
        vigente = false;
      };
      // eslint-disable-next-line react-hooks/exhaustive-deps -- vuelta: "Intentar de nuevo" pide otra vez lo mismo
    }, [intervalo, consulta, alLlegar, vuelta, categoria, elegido, agrupacion]),
  );

  const consultarDeNuevo = useCallback(() => {
    setFalla(null);
    if (intervalo === null) {
      setVuelta((anterior) => anterior + 1);
    } else {
      reiniciar();
    }
  }, [intervalo, reiniciar]);

  const elegirCategoria = useCallback((otra: Categoria) => {
    setFalla(null);
    setCategoria(otra);
  }, []);

  const aplicar = useCallback((rango: RangoElegido) => {
    setFalla(null);
    setElegido(rango);
  }, []);

  const elegirAgrupacion = useCallback((otra: Agrupacion) => {
    setFalla(null);
    setAgrupacion(otra);
  }, []);

  return { categoria, elegirCategoria, elegido, aplicar, agrupacion, elegirAgrupacion, ultima, falla, consultarDeNuevo };
}
