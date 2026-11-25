// Los enlaces al aviso de privacidad y a los términos y condiciones (openspec/changes/textos-legales, textos-legales-E05;
// NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7). Las direcciones salen de la configuración
// (EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL y EXPO_PUBLIC_TERMINOS_URL, src/configuracion/entorno.ts), nunca del código: son las
// páginas públicas del sitio, /aviso-de-privacidad y /terminos, que muestran el documento vigente que entregó EL CLIENTE
// (textos-legales-E04). Sin la dirección de un documento, su enlace no aparece (registro-E28); sin ninguna, no se muestra
// nada.
//
// Se abren en la vista de navegador del sistema dentro de la app, con openBrowserAsync de expo-web-browser, como el
// checkout del pago (ADR-0010 y su adenda del 2026-11-22): la persona lee el documento y, al cerrarlo, sigue donde
// estaba. Por delegación de Arthur (C-236; design.md de textos-legales, pregunta abierta 3).
//
// Van en el registro, en una línea donde el mock pone la casilla del consentimiento, y en "Mi perfil", en la sección
// "Legal" del mock A15 (pregunta abierta 2).
import * as WebBrowser from 'expo-web-browser';
import { StyleSheet, View } from 'react-native';

import { direccionDeLosTerminos, direccionDelAvisoDePrivacidad } from '../configuracion/entorno';
import { Enlace } from '../piezas/Enlace';
import { TituloDeSeccion } from '../piezas/TituloDeSeccion';
import { espaciado } from '../tema/theme';
import { TEXTOS_LEGALES } from './textos';

/** Abre el documento en la vista de navegador de la app. Si la vista no se abre, la pantalla sigue como estaba. */
export function abrirDocumentoLegal(direccion: string): void {
  WebBrowser.openBrowserAsync(direccion).catch(() => {});
}

interface Propiedades {
  /** El título de la sección, como "Legal" en el perfil. Sin él, los enlaces van solos. */
  titulo?: string;
  /** En una línea y en el tamaño menor, como en el registro. Sin esto, uno debajo del otro. */
  enLinea?: boolean;
}

export function EnlacesLegales({ titulo, enLinea = false }: Propiedades) {
  const aviso = direccionDelAvisoDePrivacidad();
  const terminos = direccionDeLosTerminos();
  if (!aviso && !terminos) {
    return null;
  }
  return (
    <View style={titulo ? estilos.seccion : undefined} testID="enlaces-legales">
      {titulo ? <TituloDeSeccion>{titulo}</TituloDeSeccion> : null}
      <View style={enLinea ? estilos.enLinea : undefined}>
        {aviso ? (
          <Enlace
            menor={enLinea}
            texto={TEXTOS_LEGALES.avisoDePrivacidad}
            alTocar={() => abrirDocumentoLegal(aviso)}
          />
        ) : null}
        {terminos ? (
          <Enlace menor={enLinea} texto={TEXTOS_LEGALES.terminos} alTocar={() => abrirDocumentoLegal(terminos)} />
        ) : null}
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  seccion: {
    gap: espaciado[2],
  },
  // Los dos en una línea; si no caben, bajan a la siguiente.
  enLinea: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    columnGap: espaciado[4],
  },
});
