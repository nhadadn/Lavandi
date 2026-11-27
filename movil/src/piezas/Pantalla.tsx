// El marco de una pantalla: fondo y márgenes del design system, dentro del área segura del teléfono. Con la composición
// de las imágenes de la Propuesta 04 (C-230): el encabezado con regreso y título arriba, si la pantalla lo lleva; el
// contenido con el margen del mock; y, si los hay, los botones fijos abajo, a todo lo ancho.
// Las de formulario son desplazables, para que el teclado no tape los campos ni el botón (registro-E04).
import type { ReactNode } from 'react';
import { ScrollView, StyleSheet, View, type ViewStyle } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { color, espaciado } from '../tema/theme';
import { Encabezado } from './Encabezado';

interface Props {
  children: ReactNode;
  testID?: string;
  /** Alineación vertical del contenido. */
  justificar?: ViewStyle['justifyContent'];
  /** Si el contenido se desplaza: las pantallas con campos de texto. */
  desplazable?: boolean;
  /** El encabezado con el título y, si hay a dónde, "Regresar": registro, inicio de sesión y recuperación. */
  encabezado?: { titulo: string; alRegresar?: () => void };
  /** Los botones fijos abajo, fuera del contenido: la bienvenida y las informativas. */
  acciones?: ReactNode;
}

export function Pantalla({
  children,
  testID,
  justificar = 'flex-start',
  desplazable = false,
  encabezado,
  acciones,
}: Props) {
  const contenido = (
    <View
      style={[
        estilos.contenido,
        encabezado ? estilos.bajoElEncabezado : null,
        desplazable ? estilos.crece : estilos.ocupa,
        { justifyContent: justificar },
      ]}
      testID="contenido"
    >
      {children}
    </View>
  );

  return (
    <SafeAreaView style={estilos.marco} testID={testID}>
      {encabezado ? <Encabezado titulo={encabezado.titulo} alRegresar={encabezado.alRegresar} /> : null}
      {desplazable ? (
        <ScrollView style={estilos.ocupa} contentContainerStyle={estilos.crece} keyboardShouldPersistTaps="handled">
          {contenido}
        </ScrollView>
      ) : (
        contenido
      )}
      {acciones ? (
        <View style={estilos.acciones} testID="acciones">
          {acciones}
        </View>
      ) : null}
    </SafeAreaView>
  );
}

const estilos = StyleSheet.create({
  marco: {
    flex: 1,
    backgroundColor: color.semantico.fondo,
  },
  ocupa: {
    flex: 1,
  },
  crece: {
    flexGrow: 1,
  },
  contenido: {
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[5],
    gap: espaciado[4],
  },
  bajoElEncabezado: {
    paddingTop: espaciado[3],
  },
  acciones: {
    paddingHorizontal: espaciado[4],
    paddingTop: espaciado[3],
    paddingBottom: espaciado[4],
    gap: espaciado[2],
  },
});
