// Las colonias de un código postal para la dirección nueva (direcciones-E19 y E20): con los cinco números, la app las
// pide a GET /api/codigos-postales/{codigo_postal}/colonias. Antes de los cinco no pide nada. Si la persona cambia el
// código postal mientras llega una respuesta, esa respuesta ya no cuenta.
//
// El catálogo ayuda y no bloquea (requisito "La colonia, por código postal"): sin colonias, o si no llegaron, la colonia
// se escribe a mano.
import { useEffect, useState } from 'react';

import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarColonias, type ColoniaDelCatalogo } from './api';

/** El código postal de México son cinco números (direcciones-E20). */
export const LARGO_DEL_CODIGO_POSTAL = 5;
const CINCO_NUMEROS = new RegExp(`^\\d{${LARGO_DEL_CODIGO_POSTAL}}$`);

/**
 * sin-codigo: faltan números; buscando: se piden; con-colonias: llegaron; sin-colonias: el código postal no está en el
 * catálogo (direcciones-E20); no-disponibles: no llegaron.
 */
export type EstadoDeLasColonias =
  | { fase: 'sin-codigo' }
  | { fase: 'buscando' }
  | { fase: 'con-colonias'; colonias: ColoniaDelCatalogo[] }
  | { fase: 'sin-colonias' }
  | { fase: 'no-disponibles' };

export function useColonias(codigoPostal: string): EstadoDeLasColonias {
  const { token } = useSesion();
  const completo = CINCO_NUMEROS.test(codigoPostal);
  // La última respuesta, con el código postal que pidió.
  const [respuesta, setRespuesta] = useState<{ codigo: string; estado: EstadoDeLasColonias } | null>(null);

  useEffect(() => {
    if (!token || !completo) {
      return;
    }
    let vigente = true;
    consultarColonias(token, codigoPostal).then((resultado) => {
      if (!vigente) {
        return;
      }
      let estado: EstadoDeLasColonias = { fase: 'no-disponibles' };
      if (resultado.ok) {
        estado = resultado.datos.length > 0 ? { fase: 'con-colonias', colonias: resultado.datos } : { fase: 'sin-colonias' };
      }
      setRespuesta({ codigo: codigoPostal, estado });
    });
    return () => {
      vigente = false;
    };
  }, [token, codigoPostal, completo]);

  if (!completo) {
    return { fase: 'sin-codigo' };
  }
  return respuesta?.codigo === codigoPostal ? respuesta.estado : { fase: 'buscando' };
}
