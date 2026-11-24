// La tarjeta de lavandería, componente 1 del design system (vault/design-system/componentes.md): el nombre, la distancia
// y la calificación (cercanas-E12) y "Ver perfil". Se toca toda la tarjeta, que abre el perfil. Con la composición de la
// tarjeta del tablero "Inicio" de la Propuesta 5 corregida (C-233). Colores y medidas de theme.ts (ADR-0013).
//
// Lo que no lleva todavía: el estado de disponibilidad (Abierta, Sin cupo hoy, Cerrada) es cercanas-E13, que espera a
// DD-083 y DD-078; el precio "desde X por kilo" y la foto esperan a DD-010 y DD-135 (punto 2). Sin esos estados, la
// acción es siempre "Ver perfil".
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { accesibilidad, color, espaciado, radio, tipografia } from '../tema/theme';

interface Propiedades {
  nombre: string;
  /** La distancia y la calificación, ya en texto. */
  detalle: string;
  alTocar: () => void;
  testID?: string;
}

export function TarjetaDeLavanderia({ nombre, detalle, alTocar, testID }: Propiedades) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={alTocar}
      testID={testID}
      style={({ pressed }) => [estilos.tarjeta, pressed && estilos.presionada]}
    >
      {({ pressed }) => (
        <>
          <Text style={estilos.nombre}>{nombre}</Text>
          <Text style={estilos.detalle}>{detalle}</Text>
          <View style={estilos.pie}>
            <Text style={[estilos.accion, pressed && estilos.accionPresionada]}>Ver perfil</Text>
          </View>
        </>
      )}
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  tarjeta: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    gap: espaciado[1],
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[3],
    borderWidth: 1,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  presionada: {
    backgroundColor: color.semantico.superficie_hundida,
  },
  nombre: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
    fontWeight: tipografia.peso.fuerte,
  },
  detalle: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  pie: {
    alignItems: 'flex-end',
  },
  accion: {
    color: color.semantico.accion,
    fontSize: tipografia.escala.cuerpo,
    fontWeight: tipografia.peso.fuerte,
  },
  // Sobre la superficie hundida, el color de acción queda debajo de 4.5:1; el de acción presionado sí llega, como en el
  // botón de texto (Boton.tsx y __tests__/tema/tema.test.ts).
  accionPresionada: {
    color: color.semantico.accion_hover,
  },
});
