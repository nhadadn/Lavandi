// La confirmación de una acción de la administración (suspender, dar de baja, dar de alta, bloquear, mandar un aviso,
// conciliar…), con el diálogo del sistema de cada teléfono, como "Cerrar sesión" (src/sesion/CerrarSesion.tsx) y
// "Eliminar mi cuenta" (src/perfil-usuario/BorrarCuenta.tsx): el título, el texto, "Cancelar" y el botón de la acción.
// Con "Cancelar" no pasa nada. El design system no tiene un diálogo propio y el del sistema no necesita uno.
import { Alert } from 'react-native';

import { TEXTOS_DE_LA_ADMINISTRACION } from './textos';

export interface Confirmacion {
  /** La pregunta, como "¿Suspender la lavandería?". */
  titulo: string;
  /** Lo que pasa si se confirma, en palabras del dueño del negocio. */
  texto?: string;
  /** El texto del botón que hace la acción. */
  accion: string;
  /** Lo que hace la acción, solo si la persona la confirma. */
  alConfirmar: () => void;
}

export function confirmar({ titulo, texto, accion, alConfirmar }: Confirmacion): void {
  Alert.alert(titulo, texto, [
    { text: TEXTOS_DE_LA_ADMINISTRACION.cancelar, style: 'cancel' },
    { text: accion, onPress: alConfirmar },
  ]);
}
