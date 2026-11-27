// La tarjeta de un indicador del A.5 en la app (imágenes 41 a 50 y 69 de la Propuesta 06): su nombre arriba, como
// encabezado, y debajo su gráfica o su tabla.
//
// Un indicador con cifra lleva "Descargar" a la derecha de su nombre, con su reporte y el rango y la granularidad de la
// pantalla (administracion-app-E56; tarea 20.1). Al tocarlo, "Descargar" se va del encabezado y, bajo la gráfica y una
// línea, se abre la pieza de la descarga (src/administracion/descargas/PedirDescarga.tsx) con ese rango por omisión;
// "Cancelar" la cierra. Ahí mismo espera el archivo, ofrece guardarlo y, si no se pudo, pedirlo otra vez
// (administracion-app-E57 y E60), como en las imágenes 51 a 53 y 70.
//
// Lo que espera una DD va en ámbar con la marca del módulo (src/administracion/MarcaPorConfirmar.tsx), "Por confirmar"
// seguida de su número, sin cifra (design.md, sección 5):
//
// - RenglonQueEspera: bajo la gráfica de un indicador que ya tiene cifra, lo que todavía no decide su regla
//   ("Cancelados", "Cortes del semáforo"…), con su marca a la derecha (administracion-app-E55);
// - IndicadorEnEspera: la tarjeta de un indicador sin cifra (F-I06 y F-I10), con su nombre, que espera una respuesta y
//   su marca; sin gráfica, sin cifra y sin descarga (administracion-app-E53).
//
// No son de los seis componentes del design system: se arman con los tokens (ADR-0013), como las tarjetas de las
// demás pantallas del módulo.
import { useState, type ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { Enlace } from '../../piezas/Enlace';
import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import type { CodigoDelReporte } from '../descargas/api';
import { PedirDescarga } from '../descargas/PedirDescarga';
import { TEXTOS_DE_LAS_DESCARGAS } from '../descargas/textos';
import { MarcaPorConfirmar, porConfirmar } from '../MarcaPorConfirmar';
import type { RangoAjustado } from '../rango/rango';
import { descargarElIndicador, TEXTOS_DE_LOS_INDICADORES as TEXTOS } from './textos';

/** Lo que descarga el "Descargar" de un indicador con cifra: su reporte, con el rango que usó la pantalla. */
export interface DescargaDelIndicador {
  /** El reporte del indicador: uno de los doce códigos de la descarga. */
  reporte: CodigoDelReporte;
  /** El rango y la granularidad de la pantalla, ya ajustados por el servidor: los de la pieza por omisión. */
  rango: RangoAjustado;
}

interface Propiedades {
  titulo: string;
  children: ReactNode;
  testID?: string;
  /** Sin ella, la tarjeta no ofrece descarga, como la de un indicador sin cifra (administracion-app-E53 y E63). */
  descarga?: DescargaDelIndicador;
}

export function TarjetaDelIndicador({ titulo, children, testID, descarga }: Propiedades) {
  const [descargando, setDescargando] = useState(false);
  return (
    <View style={estilos.tarjeta} testID={testID}>
      <View style={estilos.cabecera}>
        <Text accessibilityRole="header" style={[estilos.titulo, letra('titulares', 'fuerte')]}>
          {titulo}
        </Text>
        {descarga && !descargando ? (
          <Enlace
            texto={TEXTOS_DE_LAS_DESCARGAS.descargar}
            paraLeer={descargarElIndicador(titulo)}
            alTocar={() => setDescargando(true)}
          />
        ) : null}
      </View>
      {children}
      {descarga && descargando ? (
        <>
          <View style={estilos.linea} />
          <PedirDescarga
            reporte={descarga.reporte}
            desde={descarga.rango.desde}
            hasta={descarga.rango.hasta}
            granularidad={descarga.rango.granularidad}
            alCancelar={() => setDescargando(false)}
          />
        </>
      ) : null}
    </View>
  );
}

/** Un párrafo de la tarjeta, en el tono suave: lo que cuenta el indicador ("Personas distintas."). */
export function NotaDelIndicador({ children }: { children: string }) {
  return <Text style={[estilos.nota, letra('cuerpo', 'regular')]}>{children}</Text>;
}

/** Lo que espera una DD bajo la gráfica: su nombre a la izquierda y la marca a la derecha, en un solo anuncio. */
export function RenglonQueEspera({ etiqueta, dds, testID }: { etiqueta: string; dds: readonly string[]; testID: string }) {
  return (
    <View accessible accessibilityLabel={`${etiqueta}: ${porConfirmar(dds)}`} testID={testID} style={estilos.renglon}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <MarcaPorConfirmar dds={dds} />
    </View>
  );
}

/** Un indicador cuya cifra espera una respuesta: su nombre, que la espera y su marca, sin cifra (administracion-app-E53). */
export function IndicadorEnEspera({ titulo, dds, testID }: { titulo: string; dds: readonly string[]; testID: string }) {
  return (
    <TarjetaDelIndicador titulo={titulo} testID={testID}>
      <NotaDelIndicador>{TEXTOS.sinCifra}</NotaDelIndicador>
      <View style={estilos.sola}>
        <MarcaPorConfirmar dds={dds} />
      </View>
    </TarjetaDelIndicador>
  );
}

// Las medidas de las maquetas (.tarj, .cab, .tcard, .linea, .par y .renglon).
const estilos = StyleSheet.create({
  tarjeta: {
    gap: espaciado[3],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  // El nombre a la izquierda y "Descargar" a la derecha (.cab).
  cabecera: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  titulo: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.titulo_menor,
    lineHeight: tipografia.escala.titulo_menor * tipografia.interlineado.apretado,
    letterSpacing: tipografia.escala.titulo_menor * tipografia.espaciado_letra.titular,
  },
  // La línea entre la gráfica y la pieza de la descarga (.linea; imágenes 51 a 53).
  linea: {
    height: grosor.control,
    backgroundColor: color.semantico.borde,
  },
  nota: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    lineHeight: tipografia.escala.menor * tipografia.interlineado.normal,
  },
  renglon: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  etiqueta: {
    flex: 1,
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  // La marca sola, al principio de su renglón y de su ancho (imágenes 46 y 48).
  sola: {
    flexDirection: 'row',
  },
});
