// La calificación del servicio de un pedido (historial-E06 a E08): manda al servidor las estrellas y el comentario, tal
// como se escribió, o null si se dejó vacío o con puros espacios (historial-E06). Sin estrellas no manda nada
// (historial-E08). Mientras manda, no manda otra vez: un doble toque no califica dos veces. Lo que el servidor no acepta
// de cada campo (422) va junto a él; el 409, el 404 y las fallas generales, arriba del botón.
import { useRef, useState } from 'react';

import { useSesion } from '../sesion/ProveedorDeSesion';
import { calificarPedido, CONTRATO_DEL_HISTORIAL, type FallaAlCalificar } from './api';
import { mensajeAlCalificar } from './textos';

const { estrellas: CAMPO_DE_LAS_ESTRELLAS, comentario: CAMPO_DEL_COMENTARIO } = CONTRATO_DEL_HISTORIAL;
const CAMPOS = [CAMPO_DE_LAS_ESTRELLAS, CAMPO_DEL_COMENTARIO];

export function useCalificacion(idPublico: string | undefined) {
  const { token } = useSesion();
  const enCamino = useRef(false);
  const [enviando, setEnviando] = useState(false);
  const [falla, setFalla] = useState<FallaAlCalificar | null>(null);

  /** Manda la calificación; true si el servidor la guardó. */
  async function calificar(estrellas: number | null, comentario: string): Promise<boolean> {
    if (!token || !idPublico || estrellas === null || enCamino.current) {
      return false;
    }
    enCamino.current = true;
    setEnviando(true);
    setFalla(null);
    const resultado = await calificarPedido(token, idPublico, {
      estrellas,
      comentario: comentario.trim() === '' ? null : comentario,
    });
    enCamino.current = false;
    if (resultado.ok) {
      return true;
    }
    setEnviando(false);
    setFalla(resultado);
    return false;
  }

  const errorDe = (campo: string) => (falla?.motivo === 'invalido' ? falla.errores[campo]?.[0] : undefined);
  // Un 422 sin error en los dos campos de la pantalla va arriba del botón, con su mensaje.
  const conErrorDeCampo = falla?.motivo === 'invalido' && CAMPOS.some((campo) => errorDe(campo));

  return {
    enviando,
    calificar,
    errorDeLasEstrellas: errorDe(CAMPO_DE_LAS_ESTRELLAS),
    errorDelComentario: errorDe(CAMPO_DEL_COMENTARIO),
    errorGeneral: falla && !conErrorDeCampo ? mensajeAlCalificar(falla) : undefined,
  };
}
