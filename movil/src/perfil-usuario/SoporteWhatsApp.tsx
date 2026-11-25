// La opción de WhatsApp Business en "Ayuda" (perfil-usuario-E09 y E10), como la tarjeta "Escríbenos por WhatsApp" de la
// imagen a16-soporte de la Propuesta 04: el título, el texto y "Abrir WhatsApp", que abre el chat con el número de la
// configuración del servidor. Sin número, el botón dice "WhatsApp no disponible", no se puede tocar y no abre ningún
// enlace; el texto debajo lo explica, así el aspecto apagado no es la única señal (design system, reglas transversales).
//
// Del mock no entran el ícono de WhatsApp, que es una marca de otro y la app no tiene librería de íconos, ni la etiqueta
// "Número pendiente": sin número, la opción ya lo dice. No es uno de los seis componentes del design system: se arma
// con las piezas y los tokens (ADR-0013). Si el número no llega, sin escenario propio: el mensaje general de la app y
// la opción no disponible.
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { mensajeDeLaFalla } from '../cercanas/textos';
import { Boton } from '../piezas/Boton';
import { MensajeDeError } from '../piezas/MensajeDeError';
import { TituloDeSeccion } from '../piezas/TituloDeSeccion';
import { letra } from '../tema/letra';
import { color, espaciado, radio, tipografia } from '../tema/theme';
import { TEXTOS_DEL_PERFIL_DE_USUARIO as TEXTOS } from './textos';
import { enlaceDelSoporte, type EstadoDelSoporte } from './useSoporte';
import { abrirWhatsApp } from './whatsapp';

export function SoporteWhatsApp({ soporte }: { soporte: EstadoDelSoporte }) {
  const [noSeAbrio, setNoSeAbrio] = useState(false);
  const enlace = enlaceDelSoporte(soporte);

  async function abrir(conEnlace: string) {
    setNoSeAbrio(false);
    setNoSeAbrio(!(await abrirWhatsApp(conEnlace)));
  }

  return (
    <View style={estilos.tarjeta} testID="soporte-whatsapp">
      <TituloDeSeccion>{TEXTOS.tituloDeWhatsApp}</TituloDeSeccion>
      <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{TEXTOS.textoDeWhatsApp}</Text>
      {soporte.fase === 'cargando' ? (
        <View style={estilos.fila}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{TEXTOS.buscandoElNumero}</Text>
        </View>
      ) : enlace ? (
        <Boton onPress={() => abrir(enlace)}>{TEXTOS.abrirWhatsApp}</Boton>
      ) : (
        <Boton deshabilitado onPress={() => {}}>
          {TEXTOS.whatsAppNoDisponible}
        </Boton>
      )}
      {soporte.fase === 'listo' && !enlace ? (
        <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{TEXTOS.sinNumero}</Text>
      ) : null}
      {soporte.fase === 'error' ? <MensajeDeError texto={mensajeDeLaFalla(soporte.falla)} /> : null}
      {noSeAbrio ? <MensajeDeError texto={TEXTOS.noSeAbrioWhatsApp} /> : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    gap: espaciado[3],
    padding: espaciado[4],
    borderWidth: 1,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[2],
  },
  texto: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
});
