// La pieza que pide, espera y guarda la descarga de un reporte en CSV y en Excel (NSV-2026-001-v8.pdf:22:ANEXO A,
// Sección A.5, "Descarga de reportes en formato CSV y Excel para cualquier rango de fechas"; administracion-app-E56, E57,
// E58 y E60; tarea 20.1; design.md, sección 4, "Descargas en CSV y Excel"). La ponen la pantalla de indicadores (un
// reporte por indicador con cifra), la de ingresos y la de conciliación después de conciliar, cada una debajo de su
// propio "Descargar" (TEXTOS_DE_LAS_DESCARGAS.descargar), con el rango y la granularidad de la pantalla. La pone también
// la tarjeta de una descarga que no se pudo generar en la pantalla Descargas, para pedirla otra vez (alPedir).
//
// Lo que hace, como en las imágenes 51, 52, 53 y 70 de la Propuesta 06 de los mocks:
// 1. Pide confirmar el rango: "Desde" y "Hasta" en dos CampoDeTexto, DD/MM/AAAA, con los de la pantalla por omisión, y
//    "Ver por" con Día, Semana o Mes si el reporte la lleva (la conciliación no). "Pedir la descarga" manda
//    POST /api/administracion/descargas; "Cancelar" le dice a la pantalla que la cierre. Una fecha a medias o que no
//    existe se dice junto a su campo antes de mandarla. El rango al revés lo rechaza el servidor (422, exportacion-E06):
//    su mensaje va junto al campo y no se crea ninguna descarga (administracion-app-E60).
//    La pantalla que da alPedir recibe ahí la descarga pedida y la pieza no sigue: así la pantalla Descargas la pone al
//    principio de su lista, que la espera y la guarda.
// 2. Arriba dice el rango que cubre el archivo, como lo da el servidor, ya ajustado a periodos completos. Mientras se
//    genera, lo dice y consulta su estado cada 5 s, solo con la pantalla visible y la app al frente
//    (useEsperaDeLaDescarga). Si la consulta responde que ya no existe (404), deja de consultar, lo dice y ofrece
//    pedirla otra vez.
// 3. Lista, ofrece "Guardar .csv" y "Guardar .xlsx", y dice dónde quedó el archivo (useGuardarElArchivo). Con la cola en
//    la misma petición, la descarga ya llega lista y no se consulta.
// 4. Si no se pudo generar, lo dice y ofrece pedirla otra vez, con el mismo rango.
//
// No trae el "Descargar" que la abre ni la línea que la separa de la gráfica: son de cada pantalla, que los pone donde
// los dibuja su imagen (el encabezado de la tarjeta del indicador, o un botón bajo los ingresos o el reporte). No es uno
// de los seis componentes del design system: se arma con las piezas y los tokens (ADR-0013).
import { useCallback, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { mensajeGeneral } from '../../acceso/mensajes';
import { conDiagonales, fechaDeTexto } from '../../historial/fechas';
import { Boton } from '../../piezas/Boton';
import { CampoDeTexto } from '../../piezas/CampoDeTexto';
import { MensajeDeError } from '../../piezas/MensajeDeError';
import { Pestanas } from '../../piezas/Pestanas';
import { letra } from '../../tema/letra';
import { color, espaciado, tipografia } from '../../tema/theme';
import { useApiDeLaAdministracion } from '../api';
import {
  CAMPOS_DE_LA_DESCARGA,
  GRANULARIDADES,
  pedirLaDescarga,
  type CodigoDelReporte,
  type Descarga,
  type Granularidad,
  type PeticionDeDescarga,
} from './api';
import { BotonesParaGuardar } from './BotonesParaGuardar';
import {
  fechaEnElCampo,
  mensajeDeLaFalla,
  rangoEnTexto,
  seGuardo,
  TEXTOS_DE_LAS_DESCARGAS as TEXTOS,
} from './textos';
import { useEsperaDeLaDescarga } from './useEsperaDeLaDescarga';
import { useGuardarElArchivo } from './useGuardarElArchivo';

interface Propiedades {
  /** El reporte que se descarga: uno de los doce códigos del contrato. */
  reporte: CodigoDelReporte;
  /**
   * El rango de la pantalla, AAAA-MM-DD, los dos días incluidos: el que se ofrece por omisión. Con null no hay rango que
   * ofrecer, como en la descarga que no se pudo generar y cuyo rango no dio el servidor: el campo empieza vacío.
   */
  desde: string | null;
  hasta: string | null;
  /** La granularidad de la pantalla. Sin ella, el reporte no la lleva, como la conciliación, y no se pregunta. */
  granularidad?: Granularidad;
  /** "Cancelar" antes de pedirla: la pantalla cierra la pieza. */
  alCancelar: () => void;
  /** Si la pantalla la da, recibe la descarga recién pedida, y la pieza no la espera ni la guarda: lo hace la pantalla. */
  alPedir?: (descarga: Descarga) => void;
}

type Campo = (typeof CAMPOS_DE_LA_DESCARGA)[number];

const PESTANAS_DE_LA_GRANULARIDAD = GRANULARIDADES.map((clave) => ({ clave, texto: TEXTOS.granularidades[clave] }));

export function PedirDescarga({ reporte, desde, hasta, granularidad, alCancelar, alPedir }: Propiedades) {
  const pedir = useApiDeLaAdministracion();
  const [escrito, setEscrito] = useState({
    desde: desde ? fechaEnElCampo(desde) : '',
    hasta: hasta ? fechaEnElCampo(hasta) : '',
  });
  const [elegida, setElegida] = useState<Granularidad | undefined>(granularidad);
  const [errores, setErrores] = useState<Partial<Record<Campo, string>>>({});
  const [mensaje, setMensaje] = useState<string | undefined>(undefined);
  const [enviando, setEnviando] = useState(false);
  // La descarga que se pidió, con lo que se mandó para pedirla otra vez.
  const [pedida, setPedida] = useState<{ peticion: PeticionDeDescarga; descarga: Descarga } | null>(null);

  const alLlegar = useCallback((descarga: Descarga) => {
    setPedida((anterior) =>
      anterior && anterior.descarga.id_publico === descarga.id_publico ? { ...anterior, descarga } : anterior,
    );
  }, []);
  const fallaDeLaEspera = useEsperaDeLaDescarga(pedida?.descarga ?? null, alLlegar);
  const archivo = useGuardarElArchivo(alLlegar);

  async function mandar(peticion: PeticionDeDescarga, camposVisibles: readonly string[]) {
    setEnviando(true);
    setMensaje(undefined);
    const resultado = await pedirLaDescarga(pedir, peticion);
    setEnviando(false);
    if (resultado.ok) {
      setErrores({});
      if (alPedir) {
        alPedir(resultado.datos.descarga);
        return;
      }
      setPedida({ peticion, descarga: resultado.datos.descarga });
      return;
    }
    // Con un 401, el cliente del módulo ya cerró la sesión (administracion-app-E05).
    if (resultado.motivo === 'sin-sesion') {
      return;
    }
    if (resultado.motivo === 'invalido') {
      setErrores(
        Object.fromEntries(
          camposVisibles.map((campo) => [campo, resultado.errores[campo]?.[0]]).filter(([, error]) => error),
        ),
      );
      setMensaje(mensajeGeneral(resultado, camposVisibles));
      return;
    }
    setMensaje(mensajeDeLaFalla(resultado));
  }

  function pedirla() {
    const fechas = { desde: fechaDeTexto(escrito.desde), hasta: fechaDeTexto(escrito.hasta) };
    setErrores({
      desde: fechas.desde === null ? TEXTOS.fechaInvalida : undefined,
      hasta: fechas.hasta === null ? TEXTOS.fechaInvalida : undefined,
    });
    if (fechas.desde === null || fechas.hasta === null) {
      return;
    }
    const camposVisibles = elegida ? CAMPOS_DE_LA_DESCARGA : CAMPOS_DE_LA_DESCARGA.filter((campo) => campo !== 'granularidad');
    void mandar(
      { reporte, desde: fechas.desde, hasta: fechas.hasta, ...(elegida ? { granularidad: elegida } : {}) },
      camposVisibles,
    );
  }

  if (pedida === null) {
    return (
      <View style={estilos.pieza} testID="pedir-descarga">
        <Text style={[estilos.fuerte, letra('cuerpo', 'semifuerte')]}>{TEXTOS.descargarEnCsvYEnExcel}</Text>
        <View style={estilos.fila}>
          {(['desde', 'hasta'] as const).map((campo) => (
            <View key={campo} style={estilos.crece}>
              <CampoDeTexto
                nombre={campo}
                etiqueta={TEXTOS[campo]}
                tipo="fecha"
                valor={escrito[campo]}
                alCambiar={(texto) => setEscrito((anterior) => ({ ...anterior, [campo]: conDiagonales(texto) }))}
                error={errores[campo]}
              />
            </View>
          ))}
        </View>
        {elegida ? (
          <View style={estilos.campo}>
            <Text style={[estilos.etiqueta, letra('cuerpo', 'semifuerte')]}>{TEXTOS.verPor}</Text>
            <Pestanas pestanas={PESTANAS_DE_LA_GRANULARIDAD} elegida={elegida} alElegir={setElegida} />
            <MensajeDeError texto={errores.granularidad} />
          </View>
        ) : null}
        <MensajeDeError texto={mensaje} />
        <View style={estilos.fila}>
          <View style={estilos.crece}>
            <Boton ocupado={enviando} onPress={pedirla}>
              {TEXTOS.pedirLaDescarga}
            </Boton>
          </View>
          <View style={estilos.crece}>
            <Boton variante="secundario" deshabilitado={enviando} onPress={alCancelar}>
              {TEXTOS.cancelar}
            </Boton>
          </View>
        </View>
      </View>
    );
  }

  const { peticion, descarga } = pedida;
  const guardado = archivo.guardado?.descarga.id_publico === descarga.id_publico ? archivo.guardado : null;
  const falla = fallaDeLaEspera ? mensajeDeLaFalla(fallaDeLaEspera) : (archivo.fallaDe(descarga) ?? mensaje);
  // La consulta respondió 404: la descarga ya no existe y no se espera más (useEsperaDeLaDescarga).
  const yaNoExiste = fallaDeLaEspera?.motivo === 'no-existe';
  const pedirlaOtraVez = (
    <Boton variante="secundario" ocupado={enviando} onPress={() => void mandar(peticion, [])}>
      {TEXTOS.pedirlaOtraVez}
    </Boton>
  );
  return (
    <View style={estilos.pieza} testID="pedir-descarga">
      <Text style={[estilos.fuerte, letra('cuerpo', 'semifuerte')]}>
        {rangoEnTexto(descarga.desde ?? peticion.desde, descarga.hasta ?? peticion.hasta, peticion.granularidad)}
      </Text>
      {descarga.estado === 'generandose' && yaNoExiste ? pedirlaOtraVez : null}
      {descarga.estado === 'generandose' && !yaNoExiste ? (
        <>
          <View style={estilos.generandose}>
            <ActivityIndicator color={color.semantico.accion} />
            <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{TEXTOS.seEstaGenerando}</Text>
          </View>
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.seRevisa}</Text>
        </>
      ) : null}
      {descarga.estado === 'lista' ? (
        <>
          <BotonesParaGuardar descarga={descarga} archivo={archivo} />
          {guardado ? (
            <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{seGuardo(guardado.formato, guardado.carpeta)}</Text>
          ) : null}
        </>
      ) : null}
      {descarga.estado === 'no_se_pudo' ? (
        <>
          <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{TEXTOS.noSePudoGenerar}</Text>
          {pedirlaOtraVez}
        </>
      ) : null}
      <MensajeDeError texto={falla} />
    </View>
  );
}

const estilos = StyleSheet.create({
  pieza: {
    gap: espaciado[3],
  },
  fila: {
    flexDirection: 'row',
    gap: espaciado[2],
  },
  crece: {
    flex: 1,
  },
  campo: {
    gap: espaciado[1],
  },
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  fuerte: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  generandose: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
  },
  texto: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
