// El selector de fecha y hora en ventanas de una hora, componente 2 del design system (vault/design-system/
// componentes.md; NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7). Arriba, los días que se pueden elegir,
// como botones; debajo, el día elegido y sus ventanas en dos columnas. Sus estados:
//
// - disponible: con borde, se puede elegir;
// - seleccionada: con el fondo de acción y la palomita, y se anuncia como elegida: el color no es la única señal
//   (agenda-E08; design system, reglas transversales);
// - día sin servicio: un mensaje en lugar de la rejilla, nunca una rejilla vacía, con "Ver el siguiente día" si lo hay
//   (agenda-E04). Un día con horario al que ya no le quedan ventanas se dice igual, con su propio mensaje.
//
// El estado "sin cupo" (tachada, visible y sin poder elegirse) espera a agenda-E05 (DD-099). Cada día y cada ventana
// miden al menos el área táctil mínima. La composición es la del tablero "Fecha y hora (2 de 4)" de la Propuesta 5
// corregida (C-233) y la imagen a07-fecha-hora de la Propuesta 04; del mock no entran la caja de la entrega (DD-099) ni
// la leyenda del cupo. Colores y medidas de theme.ts (ADR-0013).
//
// La palomita es el carácter "✓" con la escala tipográfica, como la flecha del encabezado: la app no tiene una librería
// de íconos. Su trazo depende de la letra del sistema y se revisa en un teléfono real.
import type { ReactNode } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import type { DiaDeAgenda, Ventana } from '../agenda/api';
import { diaDelMes } from '../agenda/fechas';
import { diaCorto, fechaDelSelector, fechaEnTexto, TEXTOS_DE_LA_AGENDA, ventanaEnTexto } from '../agenda/textos';
import { letra } from '../tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../tema/theme';
import { Boton } from './Boton';

interface Propiedades {
  /** Los días que se pueden elegir, AAAA-MM-DD, de primer_dia (hoy en la plaza) a ultimo_dia. */
  dias: readonly string[];
  diaElegido: string;
  alElegirDia: (fecha: string) => void;
  /** Lo que dio el servidor para el día elegido; null mientras carga o si no llegó (la pantalla dice por qué). */
  dia: DiaDeAgenda | null;
  cargando: boolean;
  /** El inicio de la ventana elegida, que la distingue de las de otros días; null si no hay. */
  inicioElegido: string | null;
  alElegirVentana: (ventana: Ventana) => void;
}

export function SelectorDeFechaYHora({
  dias,
  diaElegido,
  alElegirDia,
  dia,
  cargando,
  inicioElegido,
  alElegirVentana,
}: Propiedades) {
  const posicion = dias.indexOf(diaElegido);
  // El día después del elegido, si es de la agenda: después de ultimo_dia no hay a dónde pasar.
  const siguiente = posicion === -1 ? undefined : dias[posicion + 1];

  let cuerpo: ReactNode = null;
  if (cargando) {
    cuerpo = (
      <View style={estilos.cargando}>
        <ActivityIndicator color={color.semantico.accion} />
        <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS_DE_LA_AGENDA.cargando}</Text>
      </View>
    );
  } else if (dia && dia.ventanas.length === 0) {
    cuerpo = (
      <View style={estilos.sinVentanas} testID="sin-ventanas">
        <Text style={[estilos.suave, estilos.centrado, letra('cuerpo', 'regular')]}>
          {dia.dia_sin_servicio ? TEXTOS_DE_LA_AGENDA.diaSinServicio : TEXTOS_DE_LA_AGENDA.sinVentanas}
        </Text>
        {siguiente ? (
          <Boton variante="secundario" onPress={() => alElegirDia(siguiente)}>
            {TEXTOS_DE_LA_AGENDA.verElSiguienteDia}
          </Boton>
        ) : null}
      </View>
    );
  } else if (dia) {
    cuerpo = (
      <View
        accessibilityRole="radiogroup"
        accessibilityLabel={TEXTOS_DE_LA_AGENDA.ventanas}
        style={estilos.rejilla}
        testID="ventanas"
      >
        {enPares(dia.ventanas).map(([una, otra]) => (
          <View key={una.inicio} style={estilos.fila} testID="fila-de-ventanas">
            <OpcionDeVentana ventana={una} elegida={una.inicio === inicioElegido} alElegir={alElegirVentana} />
            {otra ? (
              <OpcionDeVentana ventana={otra} elegida={otra.inicio === inicioElegido} alElegir={alElegirVentana} />
            ) : (
              <View style={estilos.celdaVacia} />
            )}
          </View>
        ))}
      </View>
    );
  }

  return (
    <View style={estilos.selector} testID="selector-de-fecha-y-hora">
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        accessibilityLabel={TEXTOS_DE_LA_AGENDA.dias}
        contentContainerStyle={estilos.dias}
        testID="dias"
      >
        {dias.map((fecha) => (
          <OpcionDeDia key={fecha} fecha={fecha} elegido={fecha === diaElegido} alElegir={alElegirDia} />
        ))}
      </ScrollView>
      <Text accessibilityLiveRegion="polite" style={[estilos.diaElegido, letra('titulares', 'fuerte')]}>
        {fechaDelSelector(diaElegido, dias[0] ?? null)}
      </Text>
      {cuerpo}
    </View>
  );
}

// Las ventanas de dos en dos, una fila por par; la última puede quedar sola.
function enPares(ventanas: readonly Ventana[]): [Ventana, Ventana | undefined][] {
  const pares: [Ventana, Ventana | undefined][] = [];
  for (let indice = 0; indice < ventanas.length; indice += 2) {
    pares.push([ventanas[indice], ventanas[indice + 1]]);
  }
  return pares;
}

function OpcionDeDia({
  fecha,
  elegido,
  alElegir,
}: {
  fecha: string;
  elegido: boolean;
  alElegir: (fecha: string) => void;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={fechaEnTexto(fecha)}
      accessibilityState={{ selected: elegido }}
      onPress={() => alElegir(fecha)}
      style={({ pressed }) => [
        estilos.opcionDeDia,
        elegido ? estilos.elegida : null,
        pressed ? (elegido ? estilos.elegidaPresionada : estilos.presionada) : null,
      ]}
    >
      <Text style={[estilos.diaCorto, letra('cuerpo', 'medio'), elegido ? estilos.textoElegido : null]}>
        {diaCorto(fecha)}
      </Text>
      <Text style={[estilos.numeroDelDia, letra('cuerpo', 'fuerte'), elegido ? estilos.textoElegido : null]}>
        {diaDelMes(fecha)}
      </Text>
    </Pressable>
  );
}

function OpcionDeVentana({
  ventana,
  elegida,
  alElegir,
}: {
  ventana: Ventana;
  elegida: boolean;
  alElegir: (ventana: Ventana) => void;
}) {
  const texto = ventanaEnTexto(ventana);
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityLabel={texto}
      accessibilityState={{ checked: elegida }}
      onPress={() => alElegir(ventana)}
      style={({ pressed }) => [
        estilos.ventana,
        elegida ? estilos.elegida : null,
        pressed ? (elegida ? estilos.elegidaPresionada : estilos.presionada) : null,
      ]}
    >
      {elegida ? (
        <Text
          testID="palomita"
          accessibilityElementsHidden
          importantForAccessibility="no-hide-descendants"
          style={[estilos.textoDeVentana, estilos.textoElegido, letra('cuerpo', 'fuerte')]}
        >
          ✓
        </Text>
      ) : null}
      <Text
        style={[
          estilos.textoDeVentana,
          letra('cuerpo', elegida ? 'fuerte' : 'medio'),
          elegida ? estilos.textoElegido : null,
        ]}
      >
        {texto}
      </Text>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  selector: {
    gap: espaciado[4],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  dias: {
    gap: espaciado[2],
  },
  opcionDeDia: {
    minWidth: accesibilidad.area_tactil_minima_dp,
    minHeight: accesibilidad.area_tactil_minima_dp,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: espaciado[3],
    paddingVertical: espaciado[2],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.medio,
    backgroundColor: color.semantico.superficie,
  },
  diaCorto: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  numeroDelDia: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
    fontVariant: ['tabular-nums'],
  },
  diaElegido: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
    lineHeight: tipografia.escala.cuerpo_grande * tipografia.interlineado.apretado,
    textAlign: 'center',
  },
  cargando: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[5],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  centrado: {
    textAlign: 'center',
  },
  // Como el .sin-servicio de los mocks: un recuadro punteado con el mensaje.
  sinVentanas: {
    gap: espaciado[3],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderStyle: 'dashed',
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.medio,
  },
  rejilla: {
    gap: espaciado[3],
  },
  fila: {
    flexDirection: 'row',
    gap: espaciado[3],
  },
  celdaVacia: {
    flex: 1,
  },
  ventana: {
    flex: 1,
    minHeight: accesibilidad.area_tactil_minima_dp,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espaciado[2],
    paddingHorizontal: espaciado[1],
    paddingVertical: espaciado[3],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.medio,
    backgroundColor: color.semantico.superficie,
  },
  textoDeVentana: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    fontVariant: ['tabular-nums'],
  },
  presionada: {
    backgroundColor: color.semantico.fondo,
  },
  elegida: {
    borderColor: color.semantico.accion,
    backgroundColor: color.semantico.accion,
  },
  elegidaPresionada: {
    borderColor: color.semantico.accion_hover,
    backgroundColor: color.semantico.accion_hover,
  },
  textoElegido: {
    color: color.semantico.accion_texto,
  },
});
