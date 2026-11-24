// El chat de WhatsApp Business de soporte (perfil-usuario-E09 y E10): el enlace https://wa.me/<número>, que el teléfono
// abre en WhatsApp, o en el navegador si no lo tiene. El número viene de la configuración del servidor y nunca está
// escrito en la app; sin número no hay enlace, y la app no abre uno vacío.
import { Linking } from 'react-native';

/** El enlace del chat con ese número, solo dígitos como lo lee ./api.ts, o null si no hay número. */
export function enlaceDeWhatsApp(numero: string | null): string | null {
  return numero ? `https://wa.me/${numero}` : null;
}

/** Abre el chat. Nunca lanza: si el teléfono no pudo abrir el enlace, devuelve false. */
export async function abrirWhatsApp(enlace: string): Promise<boolean> {
  try {
    await Linking.openURL(enlace);
    return true;
  } catch {
    return false;
  }
}
