// La pestaña Socios de los indicadores (administracion-app-E48 y E53; imágenes 46 y 47 de la Propuesta 06), con las
// cifras de GET /api/administracion/indicadores/socios:
//
// - F-I06, las lavanderías activas frente a registradas, en su lugar y sin cifra: qué lavandería cuenta como activa
//   espera las DD que da el servidor (DD-083; indicadores-E11; administracion-app-E53 y E54);
// - F-I07, la calificación promedio de cada lavandería en un ranking con semáforo, con la regla de indicadores-E12 y E13
//   ("Ranking con semáforo"; NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5): el lugar, la calificación y el color con su
//   texto que da el servidor; la que no tiene calificaciones, sin lugar ni semáforo. Los cortes de EL CLIENTE esperan
//   DD-114 (indicadores-E14);
// - F-I08, las entregas completadas por repartidor en una tabla, con su plaza, con la regla de indicadores-E15 ("Tabla
//   comparativa"), en el orden del servidor, de más a menos entregas.
//
// Ninguna cifra sale de aquí: todas las da el servidor. F-I07 y F-I08 llevan su "Descargar", con el rango de la
// pantalla (administracion-app-E56; imágenes 46 y 47); F-I06, sin cifra, no ofrece descarga (administracion-app-E53).
import { StyleSheet, Text } from 'react-native';

import { RankingConSemaforo } from '../../piezas/RankingConSemaforo';
import { TablaComparativa } from '../../piezas/TablaComparativa';
import { letra } from '../../tema/letra';
import { color, tipografia } from '../../tema/theme';
import type { RangoAjustado } from '../rango/rango';
import type { IndicadoresDeSocios } from './api';
import { IndicadorEnEspera, RenglonQueEspera, TarjetaDelIndicador } from './TarjetaDelIndicador';
import { calificacionEnTexto, DD_DE_LOS_INDICADORES, TEXTOS_DE_LOS_INDICADORES as TEXTOS } from './textos';

interface Propiedades {
  indicadores: IndicadoresDeSocios;
  /** El rango que usó el servidor: el de la descarga por omisión. */
  rango: RangoAjustado;
}

export function PestanaDeSocios({ indicadores, rango }: Propiedades) {
  const { lavanderias } = indicadores.calificacion_por_lavanderia;
  const { repartidores } = indicadores.entregas_por_repartidor;

  return (
    <>
      <IndicadorEnEspera
        titulo={TEXTOS.activasFrenteARegistradas}
        dds={indicadores.activas_frente_a_registradas.en_espera}
        testID="indicador-activas-frente-a-registradas"
      />
      <TarjetaDelIndicador
        titulo={TEXTOS.calificacionPorLavanderia}
        testID="indicador-calificacion-por-lavanderia"
        descarga={{ reporte: 'calificacion-por-lavanderia', rango }}
      >
        <RankingConSemaforo
          lugares={lavanderias.map((lavanderia) => ({
            clave: lavanderia.id_publico,
            lugar: lavanderia.lugar,
            nombre: lavanderia.lavanderia,
            calificacion: lavanderia.promedio === null ? null : calificacionEnTexto(lavanderia.promedio),
            semaforo:
              lavanderia.semaforo === null || lavanderia.semaforo_texto === null
                ? null
                : { color: lavanderia.semaforo, texto: lavanderia.semaforo_texto },
          }))}
        />
        <RenglonQueEspera
          etiqueta={TEXTOS.cortesDelSemaforo}
          dds={DD_DE_LOS_INDICADORES.cortesDelSemaforo}
          testID="espera-cortes-del-semaforo"
        />
      </TarjetaDelIndicador>
      <TarjetaDelIndicador
        titulo={TEXTOS.entregasPorRepartidor}
        testID="indicador-entregas-por-repartidor"
        descarga={{ reporte: 'entregas-por-repartidor', rango }}
      >
        {repartidores.length === 0 ? (
          <Text style={[estilos.vacio, letra('cuerpo', 'regular')]}>{TEXTOS.sinRepartidores}</Text>
        ) : (
          <TablaComparativa
            primeraColumna={TEXTOS.repartidor}
            columnas={[{ clave: 'entregas', titulo: TEXTOS.entregas }]}
            filas={repartidores.map((repartidor) => ({
              clave: repartidor.id_publico,
              nombre: repartidor.repartidor,
              detalle: repartidor.plaza ?? TEXTOS.sinPlaza,
              cifras: { entregas: String(repartidor.entregas) },
            }))}
          />
        )}
      </TarjetaDelIndicador>
    </>
  );
}

const estilos = StyleSheet.create({
  vacio: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
});
