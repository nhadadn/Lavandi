// Cliente de la API de los teléfonos registrados (openspec/changes/avisos, design.md, "Contrato de la API para la app";
// servidor/app/Http/Controllers/Api/DispositivosController.php). Nunca lanza: devuelve un Resultado.
import { pedir, type Resultado } from '../configuracion/api';

export type Plataforma = 'android' | 'ios';

/**
 * POST /dispositivos: liga el token de Firebase del teléfono a la cuenta de la sesión, con su plataforma (avisos-E03 y
 * E04). Si el token ya estaba, de esta cuenta o de otra, pasa a esta (avisos-E21). Responde 204.
 */
export function registrarDispositivo(
  tokenDeSesion: string,
  tokenDelTelefono: string,
  plataforma: Plataforma,
): Promise<Resultado<void>> {
  return pedir<void>('dispositivos', {
    metodo: 'POST',
    token: tokenDeSesion,
    cuerpo: { token_fcm: tokenDelTelefono, plataforma },
  });
}
