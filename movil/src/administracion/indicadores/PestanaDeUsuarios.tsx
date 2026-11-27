// La pestaña Usuarios de los indicadores (administracion-app-E47; imágenes 44 y 45 de la Propuesta 06), con las cifras
// de GET /api/administracion/indicadores/usuarios:
//
// - F-I03, los usuarios activos de cada periodo, personas distintas, en una línea de tendencia, con la regla de
//   indicadores-E06 ("Línea de tendencia"; NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5). Qué actividad cuenta espera
//   DD-111 (indicadores-E07);
// - F-I04, los que vuelven a pedir por mes de registro en una tabla, con la regla de indicadores-E08 ("Tabla
//   comparativa"): el mes como lo lee el servidor, cuántas cuentas hicieron su primer pedido y qué porcentaje volvió;
// - F-I05, el monto promedio por pedido como indicador destacado con su tendencia frente al periodo anterior de la misma
//   duración, con la regla de indicadores-E09 ("Indicador destacado con tendencia"). Qué monto cuenta espera DD-012
//   (indicadores-E10).
//
// Ninguna cifra sale de aquí: todas las da el servidor, también el texto de cada monto y de la tendencia. Cada uno lleva
// su "Descargar", con el rango de la pantalla (administracion-app-E56; imágenes 44 y 45).
import { IndicadorDestacado } from '../../piezas/IndicadorDestacado';
import { LineaDeTendencia } from '../../piezas/LineaDeTendencia';
import { TablaComparativa } from '../../piezas/TablaComparativa';
import type { RangoAjustado } from '../rango/rango';
import type { IndicadoresDeUsuarios } from './api';
import { NotaDelIndicador, RenglonQueEspera, TarjetaDelIndicador } from './TarjetaDelIndicador';
import {
  DD_DE_LOS_INDICADORES,
  montoALaVista,
  periodoCorto,
  porcentajeEnTexto,
  TEXTOS_DE_LOS_INDICADORES as TEXTOS,
} from './textos';

interface Propiedades {
  indicadores: IndicadoresDeUsuarios;
  /** El rango que usó el servidor: su granularidad para los periodos cortos, y el de la descarga por omisión. */
  rango: RangoAjustado;
}

export function PestanaDeUsuarios({ indicadores, rango }: Propiedades) {
  const { granularidad } = rango;
  const monto = montoALaVista(indicadores.monto_promedio);

  return (
    <>
      <TarjetaDelIndicador
        titulo={TEXTOS.usuariosActivos}
        testID="indicador-usuarios-activos"
        descarga={{ reporte: 'usuarios-activos', rango }}
      >
        <NotaDelIndicador>{TEXTOS.personasDistintas}</NotaDelIndicador>
        <LineaDeTendencia
          titulo={TEXTOS.usuariosActivos}
          puntos={indicadores.usuarios_activos.periodos.map((periodo) => ({
            clave: periodo.inicio,
            etiqueta: periodoCorto(periodo.inicio, granularidad),
            etiquetaCompleta: periodo.periodo,
            valor: periodo.personas,
          }))}
        />
        <RenglonQueEspera
          etiqueta={TEXTOS.queActividadCuenta}
          dds={DD_DE_LOS_INDICADORES.queActividadCuenta}
          testID="espera-que-actividad-cuenta"
        />
      </TarjetaDelIndicador>
      <TarjetaDelIndicador
        titulo={TEXTOS.vuelvenAPedir}
        testID="indicador-vuelven-a-pedir"
        descarga={{ reporte: 'usuarios-que-vuelven-a-pedir', rango }}
      >
        <TablaComparativa
          primeraColumna={TEXTOS.mesDeRegistro}
          columnas={[
            { clave: 'con-primer-pedido', titulo: TEXTOS.conPrimerPedido },
            { clave: 'volvieron', titulo: TEXTOS.volvieron },
          ]}
          filas={indicadores.vuelven_a_pedir.meses.map((mes) => ({
            clave: mes.inicio,
            nombre: mes.mes,
            cifras: { 'con-primer-pedido': String(mes.con_primer_pedido), volvieron: porcentajeEnTexto(mes.porcentaje) },
          }))}
        />
      </TarjetaDelIndicador>
      <TarjetaDelIndicador
        titulo={TEXTOS.montoPromedio}
        testID="indicador-monto-promedio"
        descarga={{ reporte: 'monto-promedio-por-pedido', rango }}
      >
        <IndicadorDestacado
          titulo={TEXTOS.montoPromedio}
          valor={monto.valor}
          detalle={monto.detalle}
          sentido={monto.sentido}
        />
        <RenglonQueEspera
          etiqueta={TEXTOS.queMontoCuenta}
          dds={DD_DE_LOS_INDICADORES.queMontoCuenta}
          testID="espera-que-monto-cuenta"
        />
      </TarjetaDelIndicador>
    </>
  );
}
