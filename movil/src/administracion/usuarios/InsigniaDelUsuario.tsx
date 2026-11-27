// El estado de una cuenta de usuario, en la lista y en la ficha (administracion-app-E24; imágenes 21, 22 y 24 de la
// Propuesta 06): la insignia de la app (src/piezas/InsigniaDeEstado.tsx) en su forma cuadrada, la de los estados de un
// módulo, con el color que le da el panel (UsuariosTable y UsuarioInfolist): activo en éxito y bloqueado en error. El
// nombre siempre va escrito: el color nunca va solo.
import { InsigniaDeEstado } from '../../piezas/InsigniaDeEstado';
import { color } from '../../tema/theme';
import type { EstadoDelUsuario } from './api';
import { TEXTOS_DE_LOS_USUARIOS as TEXTOS } from './textos';

const TONO: Record<EstadoDelUsuario, string> = {
  activo: color.estado.exito,
  bloqueado: color.estado.error,
};

export function InsigniaDelUsuario({ estado, testID }: { estado: EstadoDelUsuario; testID?: string }) {
  return (
    <InsigniaDeEstado
      codigo={estado}
      nombre={TEXTOS.estados[estado]}
      forma="cuadrada"
      tono={TONO[estado]}
      testID={testID}
    />
  );
}
