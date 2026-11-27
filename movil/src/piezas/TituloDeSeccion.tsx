// El título de una sección dentro de una pantalla, como "Servicios y precios" y "Reseñas" del perfil de la lavandería
// en las imágenes de la Propuesta 04 (a05-lavanderia). Se anuncia como encabezado. No es uno de los seis componentes
// del design system: se arma con los tokens (ADR-0013), como las demás piezas.
import { StyleSheet, Text } from 'react-native';

import { color, tipografia } from '../tema/theme';

export function TituloDeSeccion({ children }: { children: string }) {
  return (
    <Text accessibilityRole="header" style={estilos.titulo}>
      {children}
    </Text>
  );
}

const estilos = StyleSheet.create({
  titulo: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.titulo_menor,
    fontWeight: tipografia.peso.fuerte,
    lineHeight: tipografia.escala.titulo_menor * tipografia.interlineado.apretado,
    letterSpacing: tipografia.escala.titulo_menor * tipografia.espaciado_letra.titular,
  },
});
