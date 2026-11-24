// Abre el checkout de la pasarela en la vista de navegador del sistema, encima de la pantalla de pago, sin cambiar a
// Safari ni a Chrome (pago-E02; decisión 32 y adenda del 2026-11-22 de ADR-0010): así se paga dentro de la app y la
// tarjeta se escribe en la página de la pasarela, nunca en una pantalla de la app.
//
// - Con la dirección de regreso, openAuthSessionAsync de expo-web-browser: ASWebAuthenticationSession en iPhone, con
//   preferEphemeralSession para que no salga el aviso de inicio de sesión del sistema ni se compartan las cookies de
//   Safari; en Android, la pestaña de Chrome (Custom Tabs), que la librería cierra al recibir el enlace de regreso.
// - Sin ella (el servidor no tiene PASARELA_REGRESO), openBrowserAsync, la alternativa que nombra la adenda: la persona
//   cierra la vista al terminar.
//
// Termina cuando la persona vuelve a la app, por el regreso o cerrando la vista; cómo lo nombra cada sistema no importa,
// porque ningún regreso confirma el pago (pago-E07): lo que sigue es consultar el cobro. Si la vista no se pudo abrir,
// lanza.
import * as WebBrowser from 'expo-web-browser';
import { Platform } from 'react-native';

import type { Checkout } from './api';

export async function abrirCheckout({ url, regreso }: Checkout): Promise<void> {
  if (regreso === null) {
    await WebBrowser.openBrowserAsync(url);
    return;
  }
  if (Platform.OS === 'ios') {
    await WebBrowser.openAuthSessionAsync(url, regreso, { preferEphemeralSession: true });
    return;
  }
  await WebBrowser.openAuthSessionAsync(url, regreso);
}
