// La hoja "Rango y granularidad" de los reportes de la administración (design.md, sección 4; imagen 42 de la Propuesta
// 06): la abre "Cambiar" de la tarjeta del rango (TarjetaDelRango.tsx). Trae las dos fechas, "Desde" y "Hasta", en dos
// CampoDeTexto con el teclado de números, sin librería de calendario; "Ver por" con Día, Semana o Mes, con la pieza
// Pestanas; cómo se ajusta el rango con esa granularidad; y "Aplicar", fijo abajo. "Cerrar" la cierra sin cambiar nada.
//
// - Se abre con el rango elegido, o con el que usó el servidor si todavía no se eligió ninguno.
// - Los días del rango ajustado los da el servidor (App\Indicadores\Rango): van en la frase del ajuste solo mientras la
//   hoja muestra el rango que el servidor ya ajustó; si se cambia algo, la frase dice cómo se ajusta, sin días.
// - "Aplicar" solo aplica días vacíos, para que el servidor ponga los suyos, o fechas DD/MM/AAAA que existen: una a
//   medias o que no existe, como el 31/02, lo dice junto a su campo y la hoja sigue abierta (rango.ts).
// - La hoja es la del Selector (src/piezas/Selector.tsx), con el mismo Modal: en iPhone, la hoja nativa (pageSheet), que
//   se cierra deslizándola; en Android, a toda la pantalla, y se cierra con el regreso del sistema.
// - Los ingresos la abren sin "Este mes" ni "Mes anterior", que administracion-app-E37 no nombra; los indicadores le
//   suman sus atajos y su aviso "Vale para las cinco categorías." con la tarea 18.4. Cada atajo es un enlace bajo las
//   dos fechas que las llena con sus días al tocarlo, como si se escribieran: "Aplicar" los manda.
//
// No es uno de los seis componentes del design system: se arma con las piezas y los tokens (ADR-0013).
import { useState } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Boton } from '../../piezas/Boton';
import { CampoDeTexto } from '../../piezas/CampoDeTexto';
import { Enlace } from '../../piezas/Enlace';
import { Pestanas } from '../../piezas/Pestanas';
import { letra } from '../../tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../../tema/theme';
import {
  conDiagonales,
  GRANULARIDADES,
  mismoRango,
  sePuedeAplicar,
  type Granularidad,
  type RangoElegido,
} from './rango';
import { comoSeAjusta, TEXTOS_DEL_RANGO as TEXTOS } from './textos';

interface Propiedades {
  abierta: boolean;
  /** Con lo que se abren los campos: el rango elegido, o el que usó el servidor si todavía no se eligió ninguno. */
  elegido: RangoElegido;
  /** Los días DD/MM/AAAA del rango que el servidor ajustó para `elegido`; null si todavía no llegan. */
  ajustado: { desde: string; hasta: string } | null;
  /** Una línea bajo el título, en el tono suave. */
  aviso?: string;
  /** Los enlaces bajo las dos fechas que las llenan, cada uno con sus días DD/MM/AAAA al tocarlo. */
  atajos?: readonly AtajoDelRango[];
  alAplicar: (rango: RangoElegido) => void;
  alCerrar: () => void;
}

/** Un atajo de la hoja: su texto y los dos días que pone en los campos, calculados al tocarlo. */
export interface AtajoDelRango {
  texto: string;
  dias: () => { desde: string; hasta: string };
}

const PESTANAS = GRANULARIDADES.map((clave) => ({ clave, texto: TEXTOS.granularidades[clave] }));

type Campo = 'desde' | 'hasta';

const CAMPOS: readonly Campo[] = ['desde', 'hasta'];

export function HojaDelRango({ abierta, alCerrar, ...contenido }: Propiedades) {
  return (
    <Modal
      visible={abierta}
      animationType="slide"
      presentationStyle="pageSheet"
      onRequestClose={alCerrar}
      testID="hoja-del-rango"
    >
      {/* Se monta al abrir: cada vez empieza con el rango elegido. */}
      {abierta ? <ContenidoDeLaHoja {...contenido} alCerrar={alCerrar} /> : null}
    </Modal>
  );
}

function ContenidoDeLaHoja({ elegido, ajustado, aviso, atajos = [], alAplicar, alCerrar }: Omit<Propiedades, 'abierta'>) {
  const [escrito, setEscrito] = useState<RangoElegido>(elegido);
  const [errores, setErrores] = useState<Partial<Record<Campo, string>>>({});
  const ajuste = comoSeAjusta(escrito.granularidad, mismoRango(escrito, elegido) ? ajustado : null);

  function escribir(campo: Campo, texto: string) {
    setEscrito((anterior) => ({ ...anterior, [campo]: conDiagonales(texto) }));
  }

  function aplicar() {
    const conError = (campo: Campo) => (sePuedeAplicar(escrito[campo]) ? undefined : TEXTOS.fechaInvalida);
    const nuevos = { desde: conError('desde'), hasta: conError('hasta') };
    setErrores(nuevos);
    if (!nuevos.desde && !nuevos.hasta) {
      alAplicar(escrito);
    }
  }

  function usarAtajo({ dias }: AtajoDelRango) {
    setEscrito((anterior) => ({ ...anterior, ...dias() }));
  }

  function elegirGranularidad(granularidad: Granularidad) {
    setEscrito((anterior) => ({ ...anterior, granularidad }));
  }

  return (
    <SafeAreaView style={estilos.hoja} edges={['top', 'bottom']}>
      <View style={estilos.cabeza}>
        <Text accessibilityRole="header" style={[estilos.titulo, letra('titulares', 'fuerte')]}>
          {TEXTOS.titulo}
        </Text>
        <Pressable
          accessibilityRole="button"
          onPress={alCerrar}
          style={({ pressed }) => [estilos.cerrar, pressed ? estilos.cerrarPresionado : null]}
        >
          <Text style={[estilos.textoDeCerrar, letra('cuerpo', 'semifuerte')]}>{TEXTOS.cerrar}</Text>
        </Pressable>
      </View>
      <ScrollView style={estilos.ocupa} contentContainerStyle={estilos.contenido} keyboardShouldPersistTaps="handled">
        {aviso ? <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{aviso}</Text> : null}
        <View style={estilos.fechas}>
          {CAMPOS.map((campo) => (
            <View key={campo} style={estilos.fecha}>
              <CampoDeTexto
                nombre={campo}
                etiqueta={TEXTOS[campo]}
                tipo="fecha"
                valor={escrito[campo]}
                alCambiar={(texto) => escribir(campo, texto)}
                error={errores[campo]}
              />
            </View>
          ))}
        </View>
        {atajos.length > 0 ? (
          <View style={estilos.atajos}>
            {atajos.map((atajo) => (
              <Enlace key={atajo.texto} texto={atajo.texto} alTocar={() => usarAtajo(atajo)} />
            ))}
          </View>
        ) : null}
        <View style={estilos.verPor}>
          <Text style={[estilos.etiqueta, letra('cuerpo', 'semifuerte')]}>{TEXTOS.verPor}</Text>
          <Pestanas pestanas={PESTANAS} elegida={escrito.granularidad} alElegir={elegirGranularidad} />
        </View>
        {ajuste ? <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{ajuste}</Text> : null}
      </ScrollView>
      <View style={estilos.acciones}>
        <Boton onPress={aplicar}>{TEXTOS.aplicar}</Boton>
      </View>
    </SafeAreaView>
  );
}

// La hoja, la cabeza y "Cerrar" son los del Selector; el pie, el área de acciones de Pantalla.
const estilos = StyleSheet.create({
  hoja: {
    flex: 1,
    backgroundColor: color.semantico.fondo,
  },
  cabeza: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
    paddingHorizontal: espaciado[4],
    paddingTop: espaciado[3],
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
  ocupa: {
    flex: 1,
  },
  contenido: {
    gap: espaciado[4],
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[4],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    lineHeight: tipografia.escala.menor * tipografia.interlineado.normal,
  },
  fechas: {
    flexDirection: 'row',
    gap: espaciado[3],
  },
  fecha: {
    flex: 1,
  },
  // Los atajos en una fila que se parte si no cabe, con 20 entre uno y otro (imagen 42: .fila-enl).
  atajos: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    columnGap: espaciado[4] + espaciado[1],
  },
  verPor: {
    gap: espaciado[1],
  },
  // La etiqueta de CampoDeTexto.
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  acciones: {
    paddingHorizontal: espaciado[4],
    paddingTop: espaciado[3],
    paddingBottom: espaciado[4],
    borderTopWidth: grosor.control,
    borderTopColor: color.semantico.borde,
  },
});
