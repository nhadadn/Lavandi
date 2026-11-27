// Ingresos, los ingresos por periodo de los reportes financieros (F-D06; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3,
// Módulo D #6, "Ingresos por periodo, comisiones y conciliación con Conekta"; administracion-app-E37): se abre con
// "Ingresos" en el inicio del módulo (administracion-app-E03). Trae, para el rango que se elija y por día, semana o mes,
// el total de los pagos que confirmó la pasarela, cuántos son y el ingreso de cada periodo, como los da
// GET /api/administracion/ingresos, con la regla y las cifras del panel (administracion-E17; ADR-0022, decisión 9). Sin
// rango elegido, el que da el servidor: el mes en curso hasta hoy, por día. No se pone al día sola, como en el panel.
//
// La composición es la de la imagen 31 de la Propuesta 06: el rango plegado en su tarjeta con "Cambiar", que abre la
// hoja del rango sin "Este mes" ni "Mes anterior" (design.md, sección 4); debajo, las cifras del periodo, con el lugar
// de los reembolsos y ajustes (administracion-app-E39; DD-012 y DD-016) y el de las comisiones del periodo
// (administracion-app-E40; DD-020) en ámbar, sin cifra ni descarga; la tabla de cada periodo; y "Descargar", el reporte
// de los ingresos con el rango y la granularidad de la pantalla por omisión (administracion-app-E56, E57 y E60; tarea
// 20.1; src/administracion/reportes-financieros/DescargaDelReporte.tsx).
//
// Si los ingresos no llegan, sin escenario propio: los mensajes generales de la app e "Intentar de nuevo". Con un 401,
// el cliente de la administración ya volvió al inicio de sesión (administracion-app-E05).
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { HojaDelRango } from '../../src/administracion/rango/HojaDelRango';
import { diaALaVista, elegidoDelServidor } from '../../src/administracion/rango/rango';
import { TarjetaDelRango } from '../../src/administracion/rango/TarjetaDelRango';
import type { IngresosDelPeriodo, PeriodoDeLosIngresos } from '../../src/administracion/reportes-financieros/api';
import { DescargaDelReporte } from '../../src/administracion/reportes-financieros/DescargaDelReporte';
import { TarjetaDeRenglones } from '../../src/administracion/reportes-financieros/TarjetaDeRenglones';
import {
  DD_DE_LOS_REPORTES,
  mensajeDeLosReportes,
  TEXTOS_DE_LOS_INGRESOS as TEXTOS,
} from '../../src/administracion/reportes-financieros/textos';
import { useIngresos } from '../../src/administracion/reportes-financieros/useIngresos';
import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../src/administracion/rutas';
import { useRegresar } from '../../src/navegacion/regresar';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { letra } from '../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../src/tema/theme';

export default function Ingresos() {
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const { cifras, falla, elegido, aplicar, consultarDeNuevo } = useIngresos();
  const [hojaAbierta, setHojaAbierta] = useState(false);

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.titulo, alRegresar: regresar }}>
      {cifras === null && falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargando}</Text>
        </View>
      ) : null}
      {falla ? (
        <>
          <MensajeDeError texto={mensajeDeLosReportes(falla)} />
          <Boton variante="secundario" onPress={consultarDeNuevo}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}
      {cifras ? (
        <>
          <TarjetaDelRango descripcion={cifras.ingresos.rango.descripcion} alCambiar={() => setHojaAbierta(true)} />
          <CifrasDelPeriodo ingresos={cifras.ingresos} />
          <TablaDeLosPeriodos periodos={cifras.ingresos.periodos} />
          <DescargaDelReporte
            reporte="ingresos-por-periodo"
            desde={cifras.ingresos.rango.desde}
            hasta={cifras.ingresos.rango.hasta}
            granularidad={cifras.ingresos.rango.granularidad}
          />
          {/*
            Aquí, debajo de los ingresos y con el mismo rango, van las comisiones del periodo, por socio y en total, cuando
            DD-020 fije su regla (administracion-app-E40; tarea 22.6). Mientras tanto, solo su lugar en las cifras.
          */}
          <HojaDelRango
            abierta={hojaAbierta}
            // Sin rango elegido, la hoja se abre con el que usó el servidor.
            elegido={elegido ?? elegidoDelServidor(cifras.ingresos.rango)}
            ajustado={
              cifras.pedidosCon === elegido
                ? {
                    desde: diaALaVista(cifras.ingresos.rango.desde),
                    hasta: diaALaVista(cifras.ingresos.rango.hasta),
                  }
                : null
            }
            alAplicar={(rango) => {
              setHojaAbierta(false);
              aplicar(rango);
            }}
            alCerrar={() => setHojaAbierta(false)}
          />
        </>
      ) : null}
    </Pantalla>
  );
}

// El total, los pagos confirmados y, debajo, los dos lugares que esperan su DD.
function CifrasDelPeriodo({ ingresos }: { ingresos: IngresosDelPeriodo }) {
  return (
    <TarjetaDeRenglones
      testID="cifras-del-periodo"
      renglones={[
        {
          clave: 'ingresos-del-periodo',
          etiqueta: TEXTOS.ingresosDelPeriodo,
          valor: ingresos.total.ingresos_texto,
          destacado: true,
        },
        { clave: 'pagos-confirmados', etiqueta: TEXTOS.pagosConfirmados, valor: String(ingresos.total.pagos) },
        {
          clave: 'reembolsos-y-ajustes',
          etiqueta: TEXTOS.reembolsosYAjustes,
          esperaA: DD_DE_LOS_REPORTES.reembolsosYAjustes,
        },
        {
          clave: 'comisiones-del-periodo',
          etiqueta: TEXTOS.comisionesDelPeriodo,
          esperaA: DD_DE_LOS_REPORTES.comisionesDelPeriodo,
        },
      ]}
    />
  );
}

// La tabla de cada periodo del rango, en orden, también los que no tienen pagos: el periodo como lo lee el servidor y su
// ingreso. Cada fila se lee en un solo anuncio: "Semana del 26/10/2026: $500.00".
function TablaDeLosPeriodos({ periodos }: { periodos: readonly PeriodoDeLosIngresos[] }) {
  return (
    <View style={estilos.tabla} testID="tabla-de-los-periodos">
      <View style={[estilos.fila, estilos.cabeza]}>
        <Text style={[estilos.columna, estilos.encabezadoDeColumna, letra('cuerpo', 'semifuerte')]}>
          {TEXTOS.periodo}
        </Text>
        <Text style={[estilos.encabezadoDeColumna, letra('cuerpo', 'semifuerte')]}>{TEXTOS.ingresos}</Text>
      </View>
      {periodos.map((periodo, indice) => (
        <View
          key={periodo.inicio}
          accessible
          accessibilityLabel={`${periodo.periodo}: ${periodo.ingresos_texto}`}
          testID={`periodo-${periodo.inicio}`}
          style={[estilos.fila, indice > 0 ? estilos.conLinea : null]}
        >
          <Text style={[estilos.columna, estilos.celda, letra('cuerpo', 'regular')]}>{periodo.periodo}</Text>
          <Text style={[estilos.celda, estilos.monto, letra('cuerpo', 'regular')]}>{periodo.ingresos_texto}</Text>
        </View>
      ))}
    </View>
  );
}

const estilos = StyleSheet.create({
  cargando: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[5],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  // Como la tarjeta de las cifras.
  tabla: {
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[2],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[3],
  },
  cabeza: {
    borderBottomWidth: grosor.control,
    borderBottomColor: color.semantico.borde,
  },
  conLinea: {
    borderTopWidth: grosor.control,
    borderTopColor: color.semantico.borde,
  },
  columna: {
    flex: 1,
  },
  encabezadoDeColumna: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  celda: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  // Los números tabulares, para que los montos no bailen (design system, reglas transversales).
  monto: {
    fontVariant: ['tabular-nums'],
  },
});
