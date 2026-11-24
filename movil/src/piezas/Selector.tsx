// Un campo para elegir de una lista: el municipio del territorio (direcciones-E17) y la colonia de un código postal
// (direcciones-E19 y E20). Es la pieza que decidió la decisión 29 del acta del 2026-11-20 (design.md de
// openspec/changes/direcciones, Open Questions): se ve como CampoDeTexto y, al tocarlo, abre una hoja (Modal de React
// Native) con un buscador y la lista. Sin dependencias nuevas: Modal, TextInput y FlatList.
//
// - El campo se anuncia como botón, con su etiqueta, lo que muestra (la elegida o el marcador) y si la hoja está abierta.
// - Cada opción es de radio, con su estado, y se elige tocando toda la fila, como las opciones de dirección. La
//   elección se anuncia al lector de pantalla, en cola, para que en iPhone no la corte el cierre de la hoja.
// - El buscador no distingue mayúsculas ni acentos ("torreon" encuentra Torreón) y busca también en el detalle.
// - La otra opción ("No aparece mi colonia") va al final, aunque la búsqueda no encuentre nada, y avisa con lo buscado.
// - La hoja es la misma en las dos plataformas: en iPhone, la hoja nativa (pageSheet), que se cierra deslizándola; en
//   Android, a toda la pantalla, y se cierra con el regreso del sistema. Las dos llaman a onRequestClose.
//
// No es uno de los seis componentes del design system (vault/design-system/componentes.md): se arma con los tokens
// (ADR-0013), como Pestanas e Interruptor. Sin velo de color detrás de la hoja: tokens.json no tiene ese color, y la hoja
// nativa de iPhone ya oscurece lo de atrás.
import { useState } from 'react';
import {
  AccessibilityInfo,
  FlatList,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { letra } from '../tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../tema/theme';

/** Una opción de la lista: su clave, el texto que muestra el campo al elegirla y, si tiene, un detalle debajo. */
export interface OpcionDelSelector {
  clave: string;
  texto: string;
  detalle?: string;
}

interface Propiedades {
  /** El nombre del campo en la API: une el campo con su error (`campo-<nombre>`) y nombra la hoja (`hoja-<nombre>`). */
  nombre: string;
  etiqueta: string;
  /** Lo que muestra el campo: el texto de la elegida o lo que se escribió a mano. Vacío, el marcador. */
  valor: string;
  marcador: string;
  etiquetaDeLaBusqueda: string;
  opciones: readonly OpcionDelSelector[];
  /** La clave de la elegida, para anunciarla elegida en la lista. */
  elegida: string | null;
  alElegir: (opcion: OpcionDelSelector) => void;
  error?: string;
  /** Mientras no hay lista: no se abre y se anuncia deshabilitado. */
  deshabilitado?: boolean;
  /** Una salida que no es de la lista, como escribir la colonia a mano (direcciones-E20). Recibe lo que se buscó. */
  otraOpcion?: { texto: string; alElegir: (busqueda: string) => void };
}

export function Selector({
  nombre,
  etiqueta,
  valor,
  marcador,
  etiquetaDeLaBusqueda,
  opciones,
  elegida,
  alElegir,
  error,
  deshabilitado = false,
  otraOpcion,
}: Propiedades) {
  const [abierta, setAbierta] = useState(false);
  const [busqueda, setBusqueda] = useState('');
  const visibles = filtrar(opciones, busqueda);
  const loQueMuestra = valor || marcador;

  function abrir() {
    setBusqueda('');
    setAbierta(true);
  }

  // Primero se anuncia la elección y luego se avisa: lo que la pantalla anuncie con ella se oye después.
  function elegir(opcion: OpcionDelSelector) {
    setAbierta(false);
    anunciar(`${etiqueta}: ${opcion.texto}`);
    alElegir(opcion);
  }

  function elegirLaOtra() {
    setAbierta(false);
    otraOpcion?.alElegir(busqueda.trim());
  }

  return (
    <View style={estilos.contenedor} testID={`campo-${nombre}`}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'semifuerte')]}>{etiqueta}</Text>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={etiqueta}
        accessibilityValue={{ text: loQueMuestra }}
        accessibilityState={{ expanded: abierta, disabled: deshabilitado }}
        disabled={deshabilitado}
        onPress={abrir}
        style={({ pressed }) => [
          estilos.campo,
          error ? estilos.campoConError : null,
          pressed ? estilos.campoPresionado : null,
          deshabilitado ? estilos.campoDeshabilitado : null,
        ]}
      >
        <Text
          numberOfLines={1}
          style={[estilos.valor, letra('cuerpo', 'regular'), valor ? null : estilos.marcador]}
        >
          {loQueMuestra}
        </Text>
        {/* La flecha es adorno: el campo ya se anuncia como botón. Es un carácter, sin librería de íconos. */}
        <Text style={estilos.flecha} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          ▾
        </Text>
      </Pressable>
      {error ? (
        <Text role="alert" accessibilityLiveRegion="polite" style={[estilos.error, letra('cuerpo', 'regular')]}>
          {error}
        </Text>
      ) : null}
      <Modal
        visible={abierta}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={() => setAbierta(false)}
        testID={`hoja-${nombre}`}
      >
        <SafeAreaView style={estilos.hoja} edges={['top', 'bottom']}>
          <View style={estilos.cabeza}>
            <Text accessibilityRole="header" style={[estilos.titulo, letra('titulares', 'fuerte')]}>
              {etiqueta}
            </Text>
            <Pressable
              accessibilityRole="button"
              onPress={() => setAbierta(false)}
              style={({ pressed }) => [estilos.cerrar, pressed ? estilos.cerrarPresionado : null]}
            >
              <Text style={[estilos.textoDeCerrar, letra('cuerpo', 'semifuerte')]}>Cerrar</Text>
            </Pressable>
          </View>
          <TextInput
            accessibilityLabel={etiquetaDeLaBusqueda}
            placeholder={etiquetaDeLaBusqueda}
            placeholderTextColor={color.semantico.texto_suave}
            value={busqueda}
            onChangeText={setBusqueda}
            autoCorrect={false}
            autoCapitalize="none"
            returnKeyType="search"
            style={[estilos.buscador, letra('cuerpo', 'regular')]}
          />
          <FlatList
            data={visibles}
            keyExtractor={(opcion) => opcion.clave}
            renderItem={({ item }) => (
              <Opcion opcion={item} elegida={item.clave === elegida} alElegir={() => elegir(item)} />
            )}
            ListEmptyComponent={
              <Text style={[estilos.sinResultados, letra('cuerpo', 'regular')]}>Sin resultados.</Text>
            }
            ListFooterComponent={
              otraOpcion ? (
                <Pressable
                  accessibilityRole="button"
                  onPress={elegirLaOtra}
                  style={({ pressed }) => [estilos.otra, pressed ? estilos.opcionPresionada : null]}
                >
                  <Text style={[estilos.textoDeLaOtra, letra('cuerpo', 'semifuerte')]}>{otraOpcion.texto}</Text>
                </Pressable>
              ) : undefined
            }
            keyboardShouldPersistTaps="handled"
            keyboardDismissMode="on-drag"
            automaticallyAdjustKeyboardInsets
            contentContainerStyle={estilos.lista}
          />
        </SafeAreaView>
      </Modal>
    </View>
  );
}

// Una fila de la lista: el círculo de radio, el texto y el detalle. Su nombre accesible es el texto con el detalle.
function Opcion({
  opcion,
  elegida,
  alElegir,
}: {
  opcion: OpcionDelSelector;
  elegida: boolean;
  alElegir: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ checked: elegida }}
      accessibilityLabel={[opcion.texto, opcion.detalle].filter(Boolean).join(', ')}
      onPress={alElegir}
      style={({ pressed }) => [estilos.opcion, pressed ? estilos.opcionPresionada : null]}
    >
      <View style={[estilos.circulo, elegida ? estilos.circuloElegido : null]}>
        {elegida ? <View style={estilos.punto} /> : null}
      </View>
      <View style={estilos.textos}>
        <Text style={[estilos.textoDeLaOpcion, letra('cuerpo', elegida ? 'semifuerte' : 'regular')]}>
          {opcion.texto}
        </Text>
        {opcion.detalle ? (
          <Text style={[estilos.detalle, letra('cuerpo', 'regular')]}>{opcion.detalle}</Text>
        ) : null}
      </View>
    </Pressable>
  );
}

/** Anuncia al lector de pantalla, en cola: en iPhone no corta lo que dice al cerrarse la hoja. */
export function anunciar(texto: string): void {
  AccessibilityInfo.announceForAccessibilityWithOptions(texto, { queue: true });
}

/** Las opciones cuyo texto o detalle contienen lo buscado, sin distinguir mayúsculas ni acentos. */
function filtrar(opciones: readonly OpcionDelSelector[], busqueda: string): readonly OpcionDelSelector[] {
  const buscado = sinAcentos(busqueda.trim());
  if (!buscado) {
    return opciones;
  }
  return opciones.filter((opcion) => sinAcentos(`${opcion.texto} ${opcion.detalle ?? ''}`).includes(buscado));
}

// Las vocales con acento o diéresis y la eñe, en su letra simple. Sin String.prototype.normalize, para no depender de
// cómo lo trae el motor de JavaScript de cada plataforma.
const LETRAS_SIMPLES: Record<string, string> = { á: 'a', é: 'e', í: 'i', ó: 'o', ú: 'u', ü: 'u', ñ: 'n' };

function sinAcentos(texto: string): string {
  return texto.toLowerCase().replace(/[áéíóúüñ]/g, (letraConAcento) => LETRAS_SIMPLES[letraConAcento]);
}

// La caja del campo y del buscador es la de CampoDeTexto; el círculo de radio, el de OpcionDeDireccion.
const estilos = StyleSheet.create({
  contenedor: {
    gap: espaciado[1],
  },
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  // La caja de CampoDeTexto.
  campo: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[2],
    paddingHorizontal: espaciado[3],
    paddingVertical: espaciado[2],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.medio,
    backgroundColor: color.semantico.superficie,
  },
  campoConError: {
    borderColor: color.estado.error,
  },
  campoPresionado: {
    backgroundColor: color.semantico.fondo,
  },
  campoDeshabilitado: {
    backgroundColor: color.semantico.superficie_hundida,
    borderColor: color.semantico.borde,
  },
  valor: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  marcador: {
    color: color.semantico.texto_suave,
  },
  flecha: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  error: {
    color: color.estado.error,
    fontSize: tipografia.escala.menor,
  },
  hoja: {
    flex: 1,
    gap: espaciado[3],
    paddingHorizontal: espaciado[4],
    paddingTop: espaciado[3],
    backgroundColor: color.semantico.fondo,
  },
  cabeza: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
  },
  titulo: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  cerrar: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    minWidth: accesibilidad.area_tactil_minima_dp,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: espaciado[2],
    borderRadius: radio.medio,
  },
  cerrarPresionado: {
    backgroundColor: color.semantico.superficie_hundida,
  },
  textoDeCerrar: {
    color: color.semantico.accion,
    fontSize: tipografia.escala.cuerpo,
  },
  buscador: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    paddingHorizontal: espaciado[3],
    paddingVertical: espaciado[2],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.medio,
    backgroundColor: color.semantico.superficie,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  lista: {
    paddingBottom: espaciado[4],
  },
  opcion: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[3],
    borderBottomWidth: grosor.control,
    borderBottomColor: color.semantico.borde,
  },
  opcionPresionada: {
    backgroundColor: color.semantico.superficie_hundida,
  },
  circulo: {
    width: espaciado[5],
    height: espaciado[5],
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: grosor.control,
    borderColor: color.semantico.borde_fuerte,
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.superficie,
  },
  circuloElegido: {
    borderColor: color.semantico.accion,
  },
  punto: {
    width: espaciado[3],
    height: espaciado[3],
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.accion,
  },
  textos: {
    flex: 1,
    gap: espaciado[1],
  },
  textoDeLaOpcion: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  detalle: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  sinResultados: {
    paddingVertical: espaciado[4],
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  otra: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    justifyContent: 'center',
    paddingVertical: espaciado[3],
  },
  textoDeLaOtra: {
    color: color.semantico.accion,
    fontSize: tipografia.escala.cuerpo,
    textDecorationLine: 'underline',
  },
});
