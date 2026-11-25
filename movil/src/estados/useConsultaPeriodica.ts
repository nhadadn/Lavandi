// La consulta cada 30 segundos de una pantalla (ADR-0007), solo mientras la pantalla está visible y la app al frente:
//
// - al abrirse o al volver a la pantalla, consulta de inmediato y empieza a contar 30 segundos, o lo que diga la
//   pantalla;
// - al salir de la pantalla, o al cerrarla, deja de consultar (useFocusEffect de expo-router);
// - con la app al fondo deja de consultar, y al traerla al frente consulta de inmediato (AppState de React Native).
//
// La usan el seguimiento del pedido del usuario (estados-E15 y E16), la lista de pedidos de la lavandería
// (pedidos-lavanderia-E05 y E06) y la de pedidos asignados del repartidor (repartidor-E10 y E11), las dos listas por la
// adenda del 2026-11-24 de ADR-0007, cada 30 segundos, y la espera "Procesando pago" (pago-E08), cada 5: la espera del
// pago es corta y la persona la está mirando (decisión 32; adenda del 2026-11-22 de ADR-0010). Es un temporizador
// propio, sin biblioteca nueva: decisión por delegación de Arthur (C-236), anotada en las Open Questions del design.md
// de estados-del-pedido. Cuenta 30 segundos desde que sale cada consulta, no desde que llega su respuesta: con el plazo
// de las peticiones de 15 s (src/configuracion/entorno.ts), una respuesta llega antes de que salga la siguiente. Si
// llegara una respuesta vieja después de una nueva, no se entrega.
import { useFocusEffect } from 'expo-router';
import { useCallback, useRef } from 'react';
import { AppState } from 'react-native';

/** ADR-0007: cada 30 segundos con la pantalla visible. */
export const CADA_CUANTO_SE_CONSULTA_MS = 30_000;

/**
 * La app está al frente salvo que el sistema la haya mandado al fondo. Antes de que el sistema lo diga, el estado puede
 * no conocerse: la pantalla se está viendo, así que cuenta como al frente.
 */
function alFrente(estado: string | null | undefined): boolean {
  return estado !== 'background';
}

/**
 * Corre la consulta mientras la pantalla está visible y entrega cada respuesta vigente. Sin consulta (null), como
 * mientras se lee la sesión, espera. Las dos funciones deben ser estables (useCallback): si cambian, el ciclo empieza
 * de nuevo. Devuelve "consultar de nuevo": consulta ya, descarta la respuesta que esté en camino y vuelve a contar 30
 * segundos desde ahí; con la pantalla oculta o la app al fondo no hace nada. cadaCuantoMs cambia los 30 segundos.
 */
export function useConsultaPeriodica<T>(
  consulta: (() => Promise<T>) | null,
  alLlegar: (respuesta: T) => void,
  cadaCuantoMs: number = CADA_CUANTO_SE_CONSULTA_MS,
): () => void {
  const reiniciar = useRef<(() => void) | null>(null);

  useFocusEffect(
    useCallback(() => {
      if (!consulta) {
        return;
      }
      const pedir = consulta;
      let vigente = true;
      let ultimaConsulta = 0;
      let temporizador: ReturnType<typeof setInterval> | null = null;

      function consultar() {
        ultimaConsulta += 1;
        const estaConsulta = ultimaConsulta;
        pedir().then((respuesta) => {
          if (vigente && estaConsulta === ultimaConsulta) {
            alLlegar(respuesta);
          }
        });
      }

      function empezar() {
        consultar();
        temporizador = setInterval(consultar, cadaCuantoMs);
      }

      function detener() {
        if (temporizador !== null) {
          clearInterval(temporizador);
          temporizador = null;
        }
      }

      let alFrenteAhora = alFrente(AppState.currentState);
      if (alFrenteAhora) {
        empezar();
      }
      const suscripcion = AppState.addEventListener('change', (siguiente) => {
        if (alFrente(siguiente) === alFrenteAhora) {
          return;
        }
        alFrenteAhora = alFrente(siguiente);
        if (alFrenteAhora) {
          empezar();
        } else {
          detener();
        }
      });
      reiniciar.current = () => {
        if (alFrenteAhora) {
          detener();
          empezar();
        }
      };

      return () => {
        vigente = false;
        detener();
        suscripcion.remove();
        reiniciar.current = null;
      };
    }, [consulta, alLlegar, cadaCuantoMs]),
  );

  return useCallback(() => reiniciar.current?.(), []);
}
