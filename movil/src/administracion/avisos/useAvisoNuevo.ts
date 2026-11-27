// Mandar un aviso masivo desde "Escribir aviso" (administracion-app-E42): "Mandar aviso" pide antes la confirmación de
// que sale a todos los teléfonos registrados, con el diálogo del sistema (src/administracion/confirmar.ts); con
// "Cancelar" no sale nada. Al confirmarla, manda el título y el texto como se escribieron (POST avisos-masivos).
//
// La app no valida por su cuenta: el servidor aplica las reglas del panel (CreacionDeAvisoMasivo) y responde 422 con el
// error de cada campo; la pantalla lo pone junto a su campo y no sale nada. Con el 201, el aviso ya va en la cola y la
// pantalla vuelve a la lista. Mientras manda, el botón queda ocupado: no sale otro aviso encima.
//
// Mientras manda, el regreso del encabezado y el de Android siguen activos. Si la persona ya regresó cuando llega el 201,
// el aviso sale igual, pero la app no navega: ya está donde la persona quiso ir.
import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';

import { useApiDeLaAdministracion, type FallaDeLaAdministracion } from '../api';
import { confirmar } from '../confirmar';
import { CONTRATO_DE_LOS_AVISOS, mandarAviso, type AvisoNuevo } from './api';
import { errorDelCampo, mensajeDeLosAvisos, TEXTOS_DE_LOS_AVISOS as TEXTOS } from './textos';

/** Los campos que la pantalla muestra, cada uno con su error junto a él. */
const CAMPOS_VISIBLES: readonly string[] = [CONTRATO_DE_LOS_AVISOS.titulo, CONTRATO_DE_LOS_AVISOS.mensaje];

export interface EnvioDelAviso {
  enviando: boolean;
  /** El error del servidor para ese campo. */
  errorDe(campo: string): string | undefined;
  /** El mensaje del último envío que no salió, si no va junto a un campo. */
  aviso: string | undefined;
  /**
   * Pide la confirmación y, si la persona confirma, manda el aviso; con el 201, llama a alMandar si la pantalla sigue a
   * la vista.
   */
  mandar(aviso: AvisoNuevo, alMandar: () => void): void;
}

export function useAvisoNuevo(): EnvioDelAviso {
  const pedir = useApiDeLaAdministracion();
  const [enviando, setEnviando] = useState(false);
  const [falla, setFalla] = useState<FallaDeLaAdministracion | null>(null);
  const enCurso = useRef(false);
  // Si la pantalla sigue a la vista: deja de estarlo al regresar, aunque el envío siga en camino.
  const aLaVista = useRef(false);

  useFocusEffect(
    useCallback(() => {
      aLaVista.current = true;
      return () => {
        aLaVista.current = false;
      };
    }, []),
  );

  async function mandarYa(aviso: AvisoNuevo, alMandar: () => void) {
    if (enCurso.current) {
      return;
    }
    enCurso.current = true;
    setEnviando(true);
    setFalla(null);
    const resultado = await mandarAviso(pedir, aviso);
    enCurso.current = false;
    setEnviando(false);
    if (resultado.ok) {
      if (aLaVista.current) {
        alMandar();
      }
      return;
    }
    setFalla(resultado);
  }

  function mandar(aviso: AvisoNuevo, alMandar: () => void) {
    if (enCurso.current) {
      return;
    }
    confirmar({
      titulo: TEXTOS.confirmarElAviso,
      texto: TEXTOS.queHaceElAviso,
      accion: TEXTOS.mandar,
      alConfirmar: () => void mandarYa(aviso, alMandar),
    });
  }

  return {
    enviando,
    errorDe: (campo) => errorDelCampo(falla, campo),
    aviso: falla ? mensajeDeLosAvisos(falla, CAMPOS_VISIBLES) : undefined,
    mandar,
  };
}
