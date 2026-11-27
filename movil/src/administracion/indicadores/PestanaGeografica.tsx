// La pestaña Geográfico de los indicadores (administracion-app-E50; imágenes 49, 50 y 69 de la Propuesta 06), con las
// cifras de GET /api/administracion/indicadores/geografico:
//
// - "Ver el mapa por", Colonia o Zona de servicio, como el panel (MapaDeCalor::AGRUPACIONES); cambiarla vuelve a
//   consultar la pestaña con esa agrupación;
// - F-I11, el mapa de calor por colonia o por zona ("Mapa interactivo de calor"; NSV-2026-001-v8.pdf:22:ANEXO A, Sección
//   A.5), con la regla de indicadores-E19 y E20 (MapaDeCalor.tsx), y debajo una tabla con las mismas cifras, en el orden
//   del servidor, de más a menos pedidos: por colonia, cada una con la letra de su punto, su nombre y su municipio; por
//   zona, cada una con su plaza;
// - en una compilación de Android sin la llave del mapa, el mapa no se monta y la tarjeta lo dice; la tabla sigue
//   (imagen 50; src/cercanas/mapa.ts). En iPhone no pasa.
//
// Ninguna cifra sale de aquí: todas las da el servidor, también el color de cada figura. La tarjeta lleva su
// "Descargar", con el rango de la pantalla: los pedidos por colonia o por zona, según la agrupación elegida
// (administracion-app-E56; imágenes 49, 50 y 69).
import { StyleSheet, Text, View } from 'react-native';

import { hayMapaNativo } from '../../cercanas/mapa';
import { Pestanas } from '../../piezas/Pestanas';
import { TablaComparativa, type FilaDeLaTabla } from '../../piezas/TablaComparativa';
import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import type { CodigoDelReporte } from '../descargas/api';
import type { RangoAjustado } from '../rango/rango';
import { AGRUPACIONES, type Agrupacion, type MapaDeCalor as ElMapaDeCalor } from './api';
import { MapaDeCalor } from './MapaDeCalor';
import { TarjetaDelIndicador } from './TarjetaDelIndicador';
import { coloniaEnTexto, letraDeLaColonia, TEXTOS_DE_LOS_INDICADORES as TEXTOS } from './textos';

const PESTANAS = AGRUPACIONES.map((clave) => ({ clave, texto: TEXTOS.agrupaciones[clave] }));

/** El reporte de F-I11 en cada agrupación: uno por colonia y otro por zona, como los dos botones del panel. */
const REPORTE_DEL_MAPA: Record<Agrupacion, CodigoDelReporte> = {
  colonia: 'pedidos-por-colonia',
  zona: 'pedidos-por-zona',
};

/** "Ver el mapa por", con Colonia y Zona de servicio (imágenes 49 y 69). */
export function SelectorDeLaAgrupacion({
  agrupacion,
  alElegir,
}: {
  agrupacion: Agrupacion;
  alElegir: (agrupacion: Agrupacion) => void;
}) {
  return (
    <View style={estilos.selector}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'semifuerte')]}>{TEXTOS.verElMapaPor}</Text>
      <Pestanas pestanas={PESTANAS} elegida={agrupacion} alElegir={alElegir} />
    </View>
  );
}

/** Las filas de la tabla bajo el mapa, con las mismas cifras de cada figura. */
function filasDe(mapa: ElMapaDeCalor): FilaDeLaTabla[] {
  if (mapa.agrupacion === 'colonia') {
    return mapa.colonias.map((colonia, indice) => ({
      clave: `colonia-${indice}`,
      nombre: `${letraDeLaColonia(indice)} · ${coloniaEnTexto(colonia)}`,
      cifras: { pedidos: String(colonia.pedidos) },
    }));
  }
  return mapa.zonas.map((zona, indice) => ({
    clave: `zona-${indice}`,
    nombre: zona.zona,
    detalle: zona.plaza,
    cifras: { pedidos: String(zona.pedidos) },
  }));
}

interface Propiedades {
  mapa: ElMapaDeCalor;
  /** El rango que usó el servidor: el de la descarga por omisión. */
  rango: RangoAjustado;
}

export function PestanaGeografica({ mapa, rango }: Propiedades) {
  const filas = filasDe(mapa);

  return (
    <TarjetaDelIndicador
      titulo={TEXTOS.pedidosPor[mapa.agrupacion]}
      testID="indicador-mapa-de-calor"
      descarga={{ reporte: REPORTE_DEL_MAPA[mapa.agrupacion], rango }}
    >
      {hayMapaNativo() ? (
        <MapaDeCalor mapa={mapa} />
      ) : (
        <View style={estilos.sinMapa} testID="mapa-no-disponible">
          <Text style={[estilos.parrafo, letra('cuerpo', 'regular')]}>{TEXTOS.mapaNoDisponible}</Text>
        </View>
      )}
      {filas.length === 0 ? (
        <Text style={[estilos.parrafo, letra('cuerpo', 'regular')]}>{TEXTOS.sinPedidosEnElRango}</Text>
      ) : (
        <TablaComparativa
          primeraColumna={TEXTOS.columnaDelMapa[mapa.agrupacion]}
          columnas={[{ clave: 'pedidos', titulo: TEXTOS.pedidos }]}
          filas={filas}
          testID="tabla-del-mapa"
        />
      )}
    </TarjetaDelIndicador>
  );
}

const estilos = StyleSheet.create({
  selector: {
    gap: espaciado[1],
  },
  // La etiqueta de CampoDeTexto, como "Ver por" de la hoja del rango.
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  // El aviso en lugar del mapa, en su propia tarjeta (imagen 50).
  sinMapa: {
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  parrafo: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
    lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
  },
});
