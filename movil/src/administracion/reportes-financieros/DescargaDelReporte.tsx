// "Descargar" de un reporte financiero, los ingresos o la conciliación (F-D06; NSV-2026-001-v8.pdf:22:ANEXO A, Sección
// A.5, "Descarga de reportes en formato CSV y Excel para cualquier rango de fechas"; administracion-app-E56, E57 y E60;
// tarea 20.1; design.md, sección 4, "Descargas en CSV y Excel"). Es el botón secundario de las imágenes 31 (bajo la
// tabla de los ingresos) y 33 (junto a "Otro periodo", después de conciliar).
//
// Al tocarlo, en su lugar se abre la pieza de la descarga (src/administracion/descargas/PedirDescarga.tsx) en una
// tarjeta, con el rango y la granularidad de la pantalla por omisión; "Cancelar" la cierra y vuelve el botón. Ahí mismo
// espera el archivo, ofrece guardarlo y, si no se pudo, pedirlo otra vez. El botón que va junto a "Descargar" en el
// mismo renglón, como "Otro periodo", se queda debajo de la pieza. Las imágenes 51 a 53 dibujan la pieza abierta en un
// indicador; en estas dos pantallas va en la tarjeta de las demás del módulo.
//
// No es uno de los seis componentes del design system: se arma con las piezas y los tokens (ADR-0013).
import { useState, type ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { Boton } from '../../piezas/Boton';
import { color, espaciado, grosor, radio } from '../../tema/theme';
import type { CodigoDelReporte, Granularidad } from '../descargas/api';
import { PedirDescarga } from '../descargas/PedirDescarga';
import { TEXTOS_DE_LAS_DESCARGAS } from '../descargas/textos';

interface Propiedades {
  /** El reporte de la pantalla: uno de los doce códigos de la descarga. */
  reporte: CodigoDelReporte;
  /** El rango de la pantalla, AAAA-MM-DD, los dos días incluidos, como lo usó el servidor: el de la pieza. */
  desde: string;
  hasta: string;
  /** La granularidad de la pantalla; sin ella, el reporte no la lleva, como la conciliación. */
  granularidad?: Granularidad;
  /** El botón que va en el mismo renglón que "Descargar", como "Otro periodo" en la conciliación (imagen 33). */
  junto?: ReactNode;
}

export function DescargaDelReporte({ reporte, desde, hasta, granularidad, junto }: Propiedades) {
  const [descargando, setDescargando] = useState(false);

  if (descargando) {
    return (
      <>
        <View style={estilos.tarjeta} testID="descarga-del-reporte">
          <PedirDescarga
            reporte={reporte}
            desde={desde}
            hasta={hasta}
            granularidad={granularidad}
            alCancelar={() => setDescargando(false)}
          />
        </View>
        {junto}
      </>
    );
  }

  const descargar = (
    <Boton variante="secundario" onPress={() => setDescargando(true)}>
      {TEXTOS_DE_LAS_DESCARGAS.descargar}
    </Boton>
  );
  if (!junto) {
    return descargar;
  }
  return (
    <View style={estilos.fila}>
      <View style={estilos.crece}>{descargar}</View>
      <View style={estilos.crece}>{junto}</View>
    </View>
  );
}

const estilos = StyleSheet.create({
  // Como las tarjetas del módulo (.tarj).
  tarjeta: {
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  // Los dos botones en un renglón, del mismo ancho (.botones-fila).
  fila: {
    flexDirection: 'row',
    gap: espaciado[2],
  },
  crece: {
    flex: 1,
  },
});
