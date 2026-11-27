// Avisos masivos, la lista de los avisos enviados (F-D07; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7;
// administracion-app-E43): se abre con "Avisos masivos" en el inicio del módulo (administracion-app-E03). Trae todos
// los enviados, los mandara la cuenta de administración que fuera, como GET /api/administracion/avisos-masivos, del más
// reciente al más antiguo, cada uno con su título, su texto, cuándo y quién lo mandó, y a cuántos teléfonos salió, o
// "En cola" mientras el trabajo de la cola no los cuenta. Se pide al abrir la pantalla y al volver a ella, así el aviso
// que se acaba de mandar sale arriba. "Escribir aviso" abre la pantalla del aviso nuevo.
//
// Quién lo mandó: la API dice solo si fue la cuenta de la sesión ("lo mandaste tú") u otra cuenta de administración,
// porque ninguna respuesta trae el correo de otra cuenta de administración (delta de autorizacion-E14). Por eso, donde
// la imagen 36 escribe el correo, aquí va quién en palabras.
//
// Si la lista no llega, sin escenario propio: los mensajes generales de la app e "Intentar de nuevo", como la imagen 05
// de la Propuesta 06. Con un 401, el cliente de la administración ya volvió al inicio de sesión
// (administracion-app-E05).
//
// La composición es la de las imágenes 36 y 40 de la Propuesta 06: el encabezado con el regreso al inicio del módulo,
// "Escribir aviso" arriba y una tarjeta por aviso; sin avisos, la tarjeta con el texto del panel.
import { useRouter } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import type { AvisoMasivo } from '../../../src/administracion/avisos/api';
import { RUTA_DEL_AVISO_NUEVO } from '../../../src/administracion/avisos/rutas';
import {
  cuandoYQuien,
  mensajeDeLosAvisos,
  salioA,
  TEXTOS_DE_LOS_AVISOS as TEXTOS,
} from '../../../src/administracion/avisos/textos';
import { useAvisos } from '../../../src/administracion/avisos/useAvisos';
import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../../src/administracion/rutas';
import { useRegresar } from '../../../src/navegacion/regresar';
import { Boton } from '../../../src/piezas/Boton';
import { InsigniaDeEstado } from '../../../src/piezas/InsigniaDeEstado';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../../src/tema/theme';

export default function AvisosMasivos() {
  const router = useRouter();
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const { avisos, falla, consultarDeNuevo } = useAvisos();

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.titulo, alRegresar: regresar }}>
      <Boton onPress={() => router.push(RUTA_DEL_AVISO_NUEVO)}>{TEXTOS.escribirAviso}</Boton>
      {avisos === null && falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargando}</Text>
        </View>
      ) : null}
      {falla ? (
        <>
          <MensajeDeError texto={mensajeDeLosAvisos(falla)} />
          <Boton variante="secundario" onPress={consultarDeNuevo}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}
      {avisos?.length === 0 ? (
        <View style={estilos.tarjeta} testID="sin-avisos">
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.sinAvisos}</Text>
        </View>
      ) : null}
      {avisos?.map((aviso, indice) => (
        <TarjetaDelAviso key={`${aviso.enviado_en}-${indice}`} aviso={aviso} />
      ))}
    </Pantalla>
  );
}

// Un aviso enviado (imagen 36): su título, su texto, cuándo y quién lo mandó, y a cuántos teléfonos salió, o la
// insignia "En cola" en el color de información, con su nombre escrito (el color nunca va solo).
function TarjetaDelAviso({ aviso }: { aviso: AvisoMasivo }) {
  return (
    <View style={[estilos.tarjeta, estilos.aviso]} testID="aviso">
      <Text style={[estilos.titulo, letra('cuerpo', 'semifuerte')]}>{aviso.titulo}</Text>
      <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{aviso.mensaje}</Text>
      <Text style={[estilos.cuando, letra('cuerpo', 'regular')]}>
        {cuandoYQuien(aviso.enviado_en, aviso.enviado_por_esta_cuenta)}
      </Text>
      {aviso.destinatarios === null ? (
        <View style={estilos.insignia}>
          <InsigniaDeEstado
            codigo="en_cola"
            nombre={TEXTOS.enCola}
            forma="cuadrada"
            tono={color.estado.informacion}
          />
        </View>
      ) : (
        <Text style={[estilos.salio, letra('cuerpo', 'semifuerte')]}>{salioA(aviso.destinatarios)}</Text>
      )}
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
    lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
  },
  // La tarjeta de las imágenes 36 y 40.
  tarjeta: {
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  aviso: {
    gap: espaciado[2],
  },
  titulo: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
    lineHeight: tipografia.escala.cuerpo_grande * tipografia.interlineado.apretado,
  },
  texto: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
  },
  // Números tabulares, como el .num de la imagen 36.
  cuando: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    fontVariant: ['tabular-nums'],
  },
  salio: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  // La insignia, de su ancho y no de todo el renglón.
  insignia: {
    alignSelf: 'flex-start',
  },
});
