// La ficha de una cuenta de usuario para su pantalla (administracion-app-E24 y E25): la pide al servidor al abrirse, y
// bloquea la cuenta o levanta su bloqueo. Cada acción responde la ficha con el estado nuevo, que es la que se muestra
// (UsuariosController: "200 { usuario: su ficha }"). Si la cuenta no existe, o deja de existir antes de la acción, la
// ficha lo dice con el mensaje del 404 y ya no ofrece nada (administracion-app-E12). Con un 401, el cliente de la
// administración ya llevó al inicio de sesión: la pantalla no tiene nada que mostrar (administracion-app-E05).
//
// Una respuesta que llega cuando ya salió otra petición de la ficha no se pinta: gana la última. Mientras una acción va,
// no sale otra: un doble toque manda una sola, como en la ficha de la lavandería y en la del repartidor.
import { useCallback, useEffect, useRef, useState } from 'react';

import { useApiDeLaAdministracion, type FallaDeLaAdministracion } from '../api';
import { confirmar } from '../confirmar';
import { bloquearAlUsuario, consultarUsuario, levantarElBloqueo, type FichaDelUsuario } from './api';
import { TEXTOS_DE_LOS_USUARIOS as TEXTOS } from './textos';

/** cargando: se pide al servidor; lista: llegó la ficha; no-existe: el 404; error: no llegó. */
export type EstadoDeLaFicha =
  | { fase: 'cargando' }
  | { fase: 'lista'; usuario: FichaDelUsuario }
  | { fase: 'no-existe'; falla: FallaDeLaAdministracion }
  | { fase: 'error'; falla: FallaDeLaAdministracion };

export type AccionDelBloqueo = 'bloquear' | 'levantar';

export interface FichaDeLaPantalla {
  estado: EstadoDeLaFicha;
  /** La acción en camino, mientras el servidor no responde. */
  enCurso: AccionDelBloqueo | null;
  /** Por qué no salió la última acción, si no salió; la ficha sigue como estaba. */
  fallaDeLaAccion: FallaDeLaAdministracion | null;
  /** Piden la confirmación con el diálogo del sistema y, si la persona confirma, mandan la acción. */
  bloquear: () => void;
  levantarElBloqueo: () => void;
  volverAConsultar: () => void;
}

export function useFichaDelUsuario(idPublico: string | undefined): FichaDeLaPantalla {
  const pedir = useApiDeLaAdministracion();
  const [estado, setEstado] = useState<EstadoDeLaFicha>({ fase: 'cargando' });
  const [enCurso, setEnCurso] = useState<AccionDelBloqueo | null>(null);
  const [fallaDeLaAccion, setFallaDeLaAccion] = useState<FallaDeLaAdministracion | null>(null);
  // La última petición de la ficha que salió; al cerrar la pantalla, ninguna.
  const ultima = useRef(0);
  // Ataja el segundo toque antes de que el botón se vea ocupado: mientras una acción va, no sale otra.
  const enCamino = useRef(false);

  // Pide la ficha. La pantalla abre en "cargando", así que la primera consulta no tiene que decirlo.
  const consultar = useCallback(() => {
    if (!idPublico) {
      return;
    }
    ultima.current += 1;
    const esta = ultima.current;
    consultarUsuario(pedir, idPublico).then((resultado) => {
      if (esta !== ultima.current || (!resultado.ok && resultado.motivo === 'sin-sesion')) {
        return;
      }
      if (resultado.ok) {
        setEstado({ fase: 'lista', usuario: resultado.datos });
      } else {
        setEstado({ fase: resultado.motivo === 'no-existe' ? 'no-existe' : 'error', falla: resultado });
      }
    });
  }, [pedir, idPublico]);

  useEffect(() => {
    consultar();
    return () => {
      // La pantalla se cerró: ninguna respuesta en camino se pinta.
      ultima.current += 1;
    };
  }, [consultar]);

  // "Intentar de nuevo": la ficha vuelve a "cargando" mientras llega.
  const volverAConsultar = useCallback(() => {
    setEstado({ fase: 'cargando' });
    consultar();
  }, [consultar]);

  const hacer = useCallback(
    async (accion: AccionDelBloqueo) => {
      if (!idPublico || enCamino.current) {
        return;
      }
      enCamino.current = true;
      ultima.current += 1;
      const esta = ultima.current;
      setEnCurso(accion);
      setFallaDeLaAccion(null);
      // Nunca lanza: devuelve un Resultado (./api.ts).
      const resultado = await (accion === 'bloquear' ? bloquearAlUsuario : levantarElBloqueo)(pedir, idPublico);
      enCamino.current = false;
      if (esta !== ultima.current) {
        return;
      }
      setEnCurso(null);
      if (resultado.ok) {
        setEstado({ fase: 'lista', usuario: resultado.datos });
      } else if (resultado.motivo === 'no-existe') {
        setEstado({ fase: 'no-existe', falla: resultado });
      } else if (resultado.motivo !== 'sin-sesion') {
        setFallaDeLaAccion(resultado);
      }
    },
    [pedir, idPublico],
  );

  // La confirmación de cada acción, con el título y el texto del panel; con "Cancelar" no pasa nada. Mientras otra
  // acción va, ni siquiera se abre.
  const pedirConfirmacion = useCallback(
    (accion: AccionDelBloqueo) => {
      if (enCamino.current) {
        return;
      }
      const { titulo, texto } = accion === 'bloquear' ? TEXTOS.confirmarElBloqueo : TEXTOS.confirmarElLevantamiento;
      confirmar({
        titulo,
        texto,
        accion: accion === 'bloquear' ? TEXTOS.bloquear : TEXTOS.levantarElBloqueo,
        alConfirmar: () => void hacer(accion),
      });
    },
    [hacer],
  );

  return {
    estado,
    enCurso,
    fallaDeLaAccion,
    bloquear: useCallback(() => pedirConfirmacion('bloquear'), [pedirConfirmacion]),
    levantarElBloqueo: useCallback(() => pedirConfirmacion('levantar'), [pedirConfirmacion]),
    volverAConsultar,
  };
}
