// Escribir aviso, el aviso masivo nuevo (F-D07; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7, "Envío
// segmentado o global para promociones o mantenimiento."; administracion-app-E42): se abre con "Escribir aviso" en la
// lista de los avisos. Lleva el título y el texto y, bajo cada uno, cuántos caracteres lleva de su tope (255 y 1000,
// los de administracion-E23); a quién le llega, con el texto del panel; y el aviso a un segmento en ámbar, con "Por
// confirmar · DD-113", sin ofrecerlo: el aviso es global hasta que DD-113 defina los segmentos
// (administracion-app-E44).
//
// "Mandar aviso" pide la confirmación de que sale a todos los teléfonos registrados; con "Cancelar" no sale nada. Al
// confirmarla, la app manda el título y el texto como se escribieron (POST /api/administracion/avisos-masivos). La app
// no valida por su cuenta: sin título o sin texto, o pasado su tope, el servidor responde 422 y su error va junto a su
// campo ("Escribe el título."), y no sale nada. Con el 201, el aviso va en la cola y la app vuelve a la lista, donde
// sale arriba, "En cola". Con un 401, el cliente de la administración ya volvió al inicio de sesión
// (administracion-app-E05).
//
// La composición es la de las imágenes 37 a 39 de la Propuesta 06: los dos campos con su contador, "A quién le llega",
// la tarjeta del segmento y "Mandar aviso" fijo abajo; la confirmación es el diálogo del sistema.
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import {
  CONTRATO_DE_LOS_AVISOS,
  TOPE_DEL_MENSAJE,
  TOPE_DEL_TITULO,
} from '../../../src/administracion/avisos/api';
import { RUTA_DE_LOS_AVISOS } from '../../../src/administracion/avisos/rutas';
import {
  caracteresDe,
  cuantosCaracteres,
  TEXTOS_DE_LOS_AVISOS as TEXTOS,
} from '../../../src/administracion/avisos/textos';
import { useAvisoNuevo } from '../../../src/administracion/avisos/useAvisoNuevo';
import { useRegresar } from '../../../src/navegacion/regresar';
import { Boton } from '../../../src/piezas/Boton';
import { CampoDeTexto } from '../../../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../../src/tema/theme';

export default function AvisoNuevo() {
  // El regreso a la lista: el del encabezado, y el que sigue a mandar el aviso.
  const regresar = useRegresar(RUTA_DE_LOS_AVISOS);
  const envio = useAvisoNuevo();
  const [titulo, setTitulo] = useState('');
  const [mensaje, setMensaje] = useState('');

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS.tituloDelAvisoNuevo, alRegresar: regresar }}
      acciones={
        <>
          <MensajeDeError texto={envio.aviso} />
          <Boton ocupado={envio.enviando} onPress={() => envio.mandar({ titulo, mensaje }, regresar)}>
            {TEXTOS.mandarAviso}
          </Boton>
        </>
      }
    >
      <CampoConTope
        nombre={CONTRATO_DE_LOS_AVISOS.titulo}
        etiqueta={TEXTOS.etiquetaDelTitulo}
        valor={titulo}
        alCambiar={setTitulo}
        error={envio.errorDe(CONTRATO_DE_LOS_AVISOS.titulo)}
        tope={TOPE_DEL_TITULO}
      />
      <CampoConTope
        nombre={CONTRATO_DE_LOS_AVISOS.mensaje}
        etiqueta={TEXTOS.etiquetaDelTexto}
        valor={mensaje}
        alCambiar={setMensaje}
        error={envio.errorDe(CONTRATO_DE_LOS_AVISOS.mensaje)}
        tope={TOPE_DEL_MENSAJE}
        multilinea
      />
      <View style={estilos.dato}>
        <Text style={[estilos.etiquetaDelDato, letra('cuerpo', 'regular')]}>{TEXTOS.aQuienLeLlega}</Text>
        <Text style={[estilos.valorDelDato, letra('cuerpo', 'regular')]}>{TEXTOS.destinatarios}</Text>
      </View>
      <View style={estilos.tarjeta} testID="aviso-a-un-segmento">
        <View style={estilos.renglon}>
          <Text style={[estilos.etiquetaDelRenglon, letra('cuerpo', 'regular')]}>{TEXTOS.avisoAUnSegmento}</Text>
          <View style={estilos.marca}>
            <Text style={[estilos.textoDeLaMarca, letra('cuerpo', 'semifuerte')]}>{TEXTOS.porConfirmarSegmento}</Text>
          </View>
        </View>
        <Text style={[estilos.menor, letra('cuerpo', 'regular')]}>{TEXTOS.sinSegmentos}</Text>
      </View>
    </Pantalla>
  );
}

// Un campo con su error junto a él y, debajo, cuántos caracteres lleva de su tope (imágenes 37 y 38). El contador no
// detiene lo que se escribe: el tope lo aplica el servidor.
function CampoConTope({
  tope,
  ...campo
}: Parameters<typeof CampoDeTexto>[0] & { tope: number }) {
  return (
    <View style={estilos.campo}>
      <CampoDeTexto {...campo} />
      <Text style={[estilos.contador, letra('cuerpo', 'regular')]} testID={`contador-${campo.nombre}`}>
        {caracteresDe(cuantosCaracteres(campo.valor), tope)}
      </Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  campo: {
    gap: espaciado[1],
  },
  // Números tabulares, para que el contador no baile al cambiar (componentes.md, reglas transversales).
  contador: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    fontVariant: ['tabular-nums'],
  },
  dato: {
    gap: espaciado[1],
  },
  etiquetaDelDato: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  valorDelDato: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
  },
  tarjeta: {
    gap: espaciado[3],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  renglon: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  etiquetaDelRenglon: {
    flex: 1,
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  // La marca "Por confirmar" del resumen del pedido (src/pedido/ResumenDelPedido.tsx), con su borde además del color.
  marca: {
    paddingHorizontal: espaciado[2],
    paddingVertical: espaciado[1],
    borderWidth: grosor.control,
    borderColor: color.estado.atencion,
    borderRadius: radio.chico,
  },
  textoDeLaMarca: {
    color: color.estado.atencion,
    fontSize: tipografia.escala.menor,
  },
  menor: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
