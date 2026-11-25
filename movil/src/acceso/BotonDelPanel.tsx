// El botón "Abrir el panel", que acompaña al aviso de la cuenta que no usa la app, como la de administración, que entra
// por el panel (administracion-E25; decisión 56 de acta-2026-11-20-modelo-y-pasarela.md, C-245, que complementa la 31).
// Va en las dos pantallas donde se inicia sesión: el inicio de sesión, con el correo o con Google, y el registro, con
// Google.
//
// La dirección sale de la configuración (EXPO_PUBLIC_PANEL_URL, src/configuracion/entorno.ts), nunca del código; sin
// ella, el aviso sale sin el botón. Abre el panel en la vista de navegador del sistema dentro de la app, con
// openBrowserAsync de expo-web-browser, como el checkout del pago (adenda del 2026-11-22 de ADR-0010, decisión 32).
import * as WebBrowser from 'expo-web-browser';

import { direccionDelPanel } from '../configuracion/entorno';
import { Boton } from '../piezas/Boton';
import { MENSAJES } from './mensajes';

/** Abre el panel en la vista de navegador de la app. Si la vista no se abre, la pantalla sigue como estaba. */
function abrirElPanel(direccion: string): void {
  WebBrowser.openBrowserAsync(direccion).catch(() => {});
}

interface Propiedades {
  /** Si la pantalla muestra el aviso de la cuenta que no usa la app (sinAccesoALaApp de useEntrada). */
  conElAviso: boolean;
}

export function BotonDelPanel({ conElAviso }: Propiedades) {
  const panel = conElAviso ? direccionDelPanel() : undefined;
  if (!panel) {
    return null;
  }
  return (
    <Boton variante="secundario" onPress={() => abrirElPanel(panel)}>
      {MENSAJES.abrirElPanel}
    </Boton>
  );
}
