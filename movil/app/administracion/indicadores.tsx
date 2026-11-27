// Indicadores, el panel de indicadores del negocio dentro de la app (NSV-2026-001-v8.pdf:3:Cláusula I, entregable 4,
// "Incluido en app"; NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5; administracion-app-E45 a E53): se abre con
// "Indicadores" en el inicio del módulo (administracion-app-E03). Trae los indicadores del A.5 en una pestaña por
// categoría, Operaciones, Usuarios, Socios, Financiero y Geográfico, con la pieza Pestanas, que se desliza de lado
// (ADR-0022, decisión 14). Las cifras, el rango ajustado y sus textos los da GET /api/administracion/indicadores/
// {categoria}, con las mismas clases que el panel (ADR-0022, decisión 9): la app no calcula ninguno.
//
// - Arriba, el rango y la granularidad plegados en la tarjeta del rango (la misma de los ingresos), con el rango que usó
//   el servidor, cada cuánto se ponen al día las cifras y "Cambiar", que abre la hoja "Rango y granularidad" con las dos
//   fechas, "Este mes", "Mes anterior" y Día, Semana o Mes. Vale para las cinco pestañas. Sin rango elegido, el del
//   servidor: el mes en curso hasta hoy, por día (indicadores-E24).
// - Se consulta solo la pestaña que se ve, con el intervalo que trae la respuesta (30 s por omisión), solo con la
//   pantalla visible y la app al frente (administracion-app-E51; src/administracion/indicadores/useIndicadores.ts).
// - Cada indicador con cifra lleva "Descargar", un reporte por indicador (F-I01 a F-I05, F-I07 a F-I09, y F-I11 por
//   colonia o por zona, según la agrupación elegida), con el rango y la granularidad que usó el servidor por omisión
//   (administracion-app-E56, E57 y E60; tarea 20.1; src/administracion/indicadores/TarjetaDelIndicador.tsx).
// - F-I06 y F-I10 dejan su lugar, con su nombre y que esperan una respuesta, sin cifra ni descarga
//   (administracion-app-E53 y E63).
//
// La composición y los textos son los de las imágenes 41 a 53, 69 y 70 de la Propuesta 06. Si una consulta no llega,
// sin escenario propio: lo último que llegó, con los mensajes generales de la app e "Intentar de nuevo". Con un 401, el
// cliente de la administración ya volvió al inicio de sesión (administracion-app-E05).
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { CATEGORIAS, type IndicadoresDeLaCategoria } from '../../src/administracion/indicadores/api';
import { diasDeEsteMes, diasDelMesAnterior } from '../../src/administracion/indicadores/atajos';
import { PestanaDeOperaciones } from '../../src/administracion/indicadores/PestanaDeOperaciones';
import { PestanaDeSocios } from '../../src/administracion/indicadores/PestanaDeSocios';
import { PestanaDeUsuarios } from '../../src/administracion/indicadores/PestanaDeUsuarios';
import { PestanaFinanciera } from '../../src/administracion/indicadores/PestanaFinanciera';
import { PestanaGeografica, SelectorDeLaAgrupacion } from '../../src/administracion/indicadores/PestanaGeografica';
import {
  cadaCuantoSeActualizan,
  mensajeDeLosIndicadores,
  TEXTOS_DE_LOS_INDICADORES as TEXTOS,
} from '../../src/administracion/indicadores/textos';
import { useIndicadores, type LosIndicadores } from '../../src/administracion/indicadores/useIndicadores';
import { HojaDelRango, type AtajoDelRango } from '../../src/administracion/rango/HojaDelRango';
import { diaALaVista, elegidoDelServidor } from '../../src/administracion/rango/rango';
import { TarjetaDelRango } from '../../src/administracion/rango/TarjetaDelRango';
import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../src/administracion/rutas';
import { useRegresar } from '../../src/navegacion/regresar';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Pestanas } from '../../src/piezas/Pestanas';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

const PESTANAS = CATEGORIAS.map((clave) => ({ clave, texto: TEXTOS.categorias[clave] }));

// Los atajos de la hoja del rango (imagen 42), con el hoy del teléfono al tocarlos.
const ATAJOS: readonly AtajoDelRango[] = [
  { texto: TEXTOS.esteMes, dias: () => diasDeEsteMes(new Date()) },
  { texto: TEXTOS.mesAnterior, dias: () => diasDelMesAnterior(new Date()) },
];

export default function Indicadores() {
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const indicadores = useIndicadores();
  const { categoria, elegirCategoria, elegido, aplicar, agrupacion, elegirAgrupacion, ultima, falla } = indicadores;
  const [hojaAbierta, setHojaAbierta] = useState(false);
  const respuesta = ultima?.indicadores ?? null;

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.titulo, alRegresar: regresar }}>
      {respuesta ? (
        <TarjetaDelRango
          descripcion={respuesta.rango.descripcion}
          nota={cadaCuantoSeActualizan(respuesta.refresco)}
          alCambiar={() => setHojaAbierta(true)}
        />
      ) : null}
      <Pestanas
        pestanas={PESTANAS}
        elegida={categoria}
        alElegir={elegirCategoria}
        sangrado={espaciado[4]}
        conSeparadores
      />
      {categoria === 'geografico' ? (
        <SelectorDeLaAgrupacion agrupacion={agrupacion} alElegir={elegirAgrupacion} />
      ) : null}
      {falla ? (
        <>
          <MensajeDeError texto={mensajeDeLosIndicadores(falla)} />
          <Boton variante="secundario" onPress={indicadores.consultarDeNuevo}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}
      <ContenidoDeLaPestana indicadores={indicadores} />
      {respuesta ? (
        <HojaDelRango
          abierta={hojaAbierta}
          // Sin rango elegido, la hoja se abre con el que usó el servidor.
          elegido={elegido ?? elegidoDelServidor(respuesta.rango)}
          ajustado={
            ultima?.pedidaCon.elegido === elegido
              ? { desde: diaALaVista(respuesta.rango.desde), hasta: diaALaVista(respuesta.rango.hasta) }
              : null
          }
          aviso={TEXTOS.valeParaLasCinco}
          atajos={ATAJOS}
          alAplicar={(rango) => {
            setHojaAbierta(false);
            aplicar(rango);
          }}
          alCerrar={() => setHojaAbierta(false)}
        />
      ) : null}
    </Pantalla>
  );
}

/**
 * Los indicadores de la pestaña que se ve, si lo último que llegó es de ella (y, en la geográfica, de la agrupación
 * elegida); si no, mientras llega, que se están cargando.
 */
function ContenidoDeLaPestana({ indicadores }: { indicadores: LosIndicadores }) {
  const { categoria, agrupacion, ultima, falla } = indicadores;
  const respuesta = ultima?.indicadores;
  const esDeLaPestana =
    respuesta !== undefined &&
    respuesta.categoria === categoria &&
    (respuesta.categoria !== 'geografico' || respuesta.indicadores.mapa_de_calor.agrupacion === agrupacion);

  if (!esDeLaPestana) {
    return falla ? null : (
      <View style={estilos.cargando}>
        <ActivityIndicator color={color.semantico.accion} />
        <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargando}</Text>
      </View>
    );
  }
  return <Pestana respuesta={respuesta} />;
}

// Cada pestaña recibe el rango que usó el servidor: el de sus periodos y el que ofrece su "Descargar" por omisión.
function Pestana({ respuesta }: { respuesta: IndicadoresDeLaCategoria }) {
  const { rango } = respuesta;
  switch (respuesta.categoria) {
    case 'operaciones':
      return <PestanaDeOperaciones indicadores={respuesta.indicadores} rango={rango} />;
    case 'usuarios':
      return <PestanaDeUsuarios indicadores={respuesta.indicadores} rango={rango} />;
    case 'socios':
      return <PestanaDeSocios indicadores={respuesta.indicadores} rango={rango} />;
    case 'financiero':
      return <PestanaFinanciera indicadores={respuesta.indicadores} rango={rango} />;
    case 'geografico':
      return <PestanaGeografica mapa={respuesta.indicadores.mapa_de_calor} rango={rango} />;
  }
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
});
