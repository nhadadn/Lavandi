// La pestaña Operaciones de los indicadores (administracion-app-E46; imágenes 41 y 43 de la Propuesta 06), con las
// cifras de GET /api/administracion/indicadores/operaciones:
//
// - F-I01, los pedidos totales de cada periodo en barras y, en otra tarjeta, en una tabla que pone cada periodo junto
//   al anterior, con la regla de indicadores-E01 a E03 ("Gráfica de barras y tabla comparativa"; NSV-2026-001-v8.pdf:22:
//   ANEXO A, Sección A.5);
// - F-I02, los pedidos por estado en una dona, activos y completados, con su total al centro, con la regla de
//   indicadores-E04 ("Gráfica de dona"). Los cancelados esperan DD-016 (indicadores-E05; administracion-app-E55).
//
// Cada gráfica trae sus cifras en texto (las piezas de src/piezas/). Ninguna cifra sale de aquí: todas las da el
// servidor. Cada indicador lleva su "Descargar", con el rango de la pantalla (administracion-app-E56): F-I01 en la
// tarjeta de las barras, como en la imagen 41, y F-I02 en la de la dona.
import { Dona } from '../../piezas/Dona';
import { GraficaDeBarras } from '../../piezas/GraficaDeBarras';
import { TablaComparativa } from '../../piezas/TablaComparativa';
import { color } from '../../tema/theme';
import type { RangoAjustado } from '../rango/rango';
import type { IndicadoresDeOperaciones } from './api';
import { RenglonQueEspera, TarjetaDelIndicador } from './TarjetaDelIndicador';
import { centroDeLaDona, DD_DE_LOS_INDICADORES, periodoCorto, TEXTOS_DE_LOS_INDICADORES as TEXTOS } from './textos';

interface Propiedades {
  indicadores: IndicadoresDeOperaciones;
  /** El rango que usó el servidor: su granularidad para los periodos cortos, y el de la descarga por omisión. */
  rango: RangoAjustado;
}

export function PestanaDeOperaciones({ indicadores, rango }: Propiedades) {
  const { granularidad } = rango;
  const { periodos } = indicadores.pedidos_totales;
  const { activos, completados } = indicadores.pedidos_por_estado;

  return (
    <>
      <TarjetaDelIndicador
        titulo={TEXTOS.pedidosTotales}
        testID="indicador-pedidos-totales"
        descarga={{ reporte: 'pedidos-totales', rango }}
      >
        <GraficaDeBarras
          titulo={TEXTOS.pedidosTotales}
          barras={periodos.map((periodo) => ({
            clave: periodo.inicio,
            etiqueta: periodoCorto(periodo.inicio, granularidad),
            etiquetaCompleta: periodo.periodo,
            valor: periodo.pedidos,
          }))}
        />
      </TarjetaDelIndicador>
      <TarjetaDelIndicador titulo={TEXTOS.pedidosTotalesJuntoAlAnterior} testID="indicador-pedidos-totales-comparados">
        <TablaComparativa
          primeraColumna={TEXTOS.periodo}
          columnas={[
            { clave: 'pedidos', titulo: TEXTOS.pedidos },
            { clave: 'anterior', titulo: TEXTOS.anterior },
          ]}
          filas={periodos.map((periodo) => ({
            clave: periodo.inicio,
            nombre: periodo.periodo,
            cifras: { pedidos: String(periodo.pedidos), anterior: String(periodo.pedidos_anterior) },
          }))}
        />
      </TarjetaDelIndicador>
      <TarjetaDelIndicador
        titulo={TEXTOS.pedidosPorEstado}
        testID="indicador-pedidos-por-estado"
        descarga={{ reporte: 'pedidos-por-estado', rango }}
      >
        <Dona
          titulo={TEXTOS.pedidosPorEstado}
          centro={centroDeLaDona(activos + completados)}
          porciones={[
            // Los colores de la imagen 43: activos en el de acción y completados en el de éxito.
            { clave: 'activos', etiqueta: TEXTOS.activos, valor: activos, color: color.semantico.accion },
            { clave: 'completados', etiqueta: TEXTOS.completados, valor: completados, color: color.estado.exito },
          ]}
        />
        <RenglonQueEspera
          etiqueta={TEXTOS.cancelados}
          dds={DD_DE_LOS_INDICADORES.cancelados}
          testID="espera-cancelados"
        />
      </TarjetaDelIndicador>
    </>
  );
}
