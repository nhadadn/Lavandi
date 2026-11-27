// Una descarga en la pantalla Descargas (administracion-app-E59), como en la imagen 54 de la Propuesta 06: arriba, su
// reporte y la insignia cuadrada de su estado, con su nombre escrito (el color nunca va solo: design system, reglas
// transversales); debajo, en renglones separados, el rango que cubre su archivo y cuándo se pidió; si está lista, sus
// botones para guardar (administracion-app-E58). La que se genera dice que se revisa cada 5 s y consulta la suya mientras
// la pantalla está abierta (administracion-app-E57); si la consulta responde que ya no existe, deja de decirlo.
//
// La que no se pudo generar lo dice en su insignia y ofrece "Pedirla otra vez" (administracion-app-E57), con el mismo
// reporte y el mismo rango; la nueva entra al principio de la lista (alPedirOtra):
// - la de la conciliación, que no lleva granularidad, se pide en cuanto se toca, como en la imagen 54;
// - las demás abren la pieza de la descarga con su rango, para elegir "Ver por" antes de pedirla, con la del panel por
//   omisión: la descarga no trae su granularidad, porque el servidor no la guarda (ADR-0022, decisión 15);
// - si el servidor no dio su rango (desde y hasta en null: no lo pudo leer del nombre del archivo), la pieza se abre con
//   los dos campos vacíos, para escribirlo, también en la conciliación.
//
// Por lo mismo, el renglón del rango no dice la granularidad, como "por semana" en la imagen 54: espera la respuesta que
// anota design.md, sección 4, "Descargas en CSV y Excel".
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { Boton } from '../../piezas/Boton';
import { InsigniaDeEstado } from '../../piezas/InsigniaDeEstado';
import { MensajeDeError } from '../../piezas/MensajeDeError';
import { letra } from '../../tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import { useApiDeLaAdministracion } from '../api';
import {
  GRANULARIDAD_POR_OMISION,
  pedirLaDescarga,
  REPORTE_SIN_GRANULARIDAD,
  type Descarga,
  type EstadoDeLaDescarga,
} from './api';
import { BotonesParaGuardar } from './BotonesParaGuardar';
import { PedirDescarga } from './PedirDescarga';
import {
  mensajeDeLaFalla,
  nombreDeLaDescarga,
  pedidaEnTexto,
  rangoEnTexto,
  TEXTOS_DE_LAS_DESCARGAS as TEXTOS,
} from './textos';
import { useEsperaDeLaDescarga } from './useEsperaDeLaDescarga';
import type { GuardadoDeArchivos } from './useGuardarElArchivo';

/** El color de cada estado en su insignia, como en la imagen 54: información, éxito y error. */
const TONO: Record<EstadoDeLaDescarga, string> = {
  generandose: color.estado.informacion,
  lista: color.estado.exito,
  no_se_pudo: color.estado.error,
};

interface Propiedades {
  descarga: Descarga;
  /** Pone en la lista la descarga como la dio el servidor; debe ser estable (useCallback). */
  alCambiar: (descarga: Descarga) => void;
  /** Pone al principio de la lista la que se pidió otra vez. */
  alPedirOtra: (descarga: Descarga) => void;
  archivo: GuardadoDeArchivos;
}

export function TarjetaDeDescarga({ descarga, alCambiar, alPedirOtra, archivo }: Propiedades) {
  const pedir = useApiDeLaAdministracion();
  const fallaDeLaEspera = useEsperaDeLaDescarga(descarga, alCambiar);
  // La pieza de la descarga, abierta para pedirla otra vez con otra granularidad.
  const [eligiendo, setEligiendo] = useState(false);
  const [pidiendo, setPidiendo] = useState(false);
  const [mensaje, setMensaje] = useState<string | undefined>(undefined);
  const { reporte, desde, hasta } = descarga;
  // La consulta respondió 404: la descarga ya no existe y no se revisa más (useEsperaDeLaDescarga).
  const yaNoExiste = fallaDeLaEspera?.motivo === 'no-existe';
  const falla = fallaDeLaEspera ? mensajeDeLaFalla(fallaDeLaEspera) : (archivo.fallaDe(descarga) ?? mensaje);

  async function pedirlaOtraVez() {
    // Con granularidad que elegir, o sin el rango que dio el servidor, se abre la pieza de la descarga.
    if (reporte !== REPORTE_SIN_GRANULARIDAD || !desde || !hasta) {
      setEligiendo(true);
      return;
    }
    setPidiendo(true);
    setMensaje(undefined);
    const resultado = await pedirLaDescarga(pedir, { reporte, desde, hasta });
    setPidiendo(false);
    if (resultado.ok) {
      alPedirOtra(resultado.datos.descarga);
      return;
    }
    // Con un 401, el cliente del módulo ya cerró la sesión (administracion-app-E05).
    if (resultado.motivo !== 'sin-sesion') {
      setMensaje(mensajeDeLaFalla(resultado));
    }
  }

  return (
    <View style={estilos.tarjeta} testID={`descarga-${descarga.id_publico}`}>
      <View style={estilos.cabecera}>
        <Text style={[estilos.nombre, letra('cuerpo', 'semifuerte')]}>{nombreDeLaDescarga(descarga)}</Text>
        <InsigniaDeEstado
          codigo={descarga.estado}
          nombre={TEXTOS.estados[descarga.estado]}
          forma="cuadrada"
          tono={TONO[descarga.estado]}
          testID="estado-de-la-descarga"
        />
      </View>
      {desde && hasta ? (
        <Text style={[estilos.rango, letra('cuerpo', 'regular')]}>{rangoEnTexto(desde, hasta)}</Text>
      ) : null}
      {descarga.pedida_en ? (
        <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{pedidaEnTexto(descarga.pedida_en)}</Text>
      ) : null}
      {descarga.estado === 'generandose' && !yaNoExiste ? (
        <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.seRevisa}</Text>
      ) : null}
      {descarga.estado === 'lista' ? <BotonesParaGuardar descarga={descarga} archivo={archivo} /> : null}
      {descarga.estado === 'no_se_pudo' ? (
        eligiendo ? (
          <PedirDescarga
            reporte={reporte}
            desde={desde}
            hasta={hasta}
            granularidad={reporte === REPORTE_SIN_GRANULARIDAD ? undefined : GRANULARIDAD_POR_OMISION}
            alCancelar={() => setEligiendo(false)}
            alPedir={(nueva) => {
              setEligiendo(false);
              alPedirOtra(nueva);
            }}
          />
        ) : (
          <View style={estilos.boton}>
            <Boton variante="secundario" ocupado={pidiendo} onPress={() => void pedirlaOtraVez()}>
              {TEXTOS.pedirlaOtraVez}
            </Boton>
          </View>
        )
      ) : null}
      <MensajeDeError texto={falla} />
    </View>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    gap: espaciado[2],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  cabecera: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  nombre: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  rango: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  // "Pedirla otra vez" mide lo que su texto, como en la imagen 54.
  boton: {
    alignSelf: 'flex-start',
  },
});
