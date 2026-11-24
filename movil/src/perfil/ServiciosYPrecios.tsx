// Los servicios que ofrece la lavandería, cada uno con su precio en su unidad (perfil-lavanderia-E05 y E09), en una
// tarjeta con una fila por servicio, como en la imagen a05-lavanderia de la Propuesta 04. Del mock no entran la
// descripción de cada servicio ni "Precios pendientes": el servidor entrega el nombre y el precio, y nada más. Colores y
// medidas de theme.ts (ADR-0013); los montos con números tabulares (design system, reglas transversales).
import { StyleSheet, Text, View } from 'react-native';

import { TituloDeSeccion } from '../piezas/TituloDeSeccion';
import { color, espaciado, radio, tipografia } from '../tema/theme';
import type { ServicioDeLavanderia } from './api';
import { precioEnTexto, TEXTOS_DEL_PERFIL } from './textos';

export function ServiciosYPrecios({ servicios }: { servicios: ServicioDeLavanderia[] }) {
  return (
    <View style={estilos.seccion} testID="servicios">
      <TituloDeSeccion>{TEXTOS_DEL_PERFIL.servicios}</TituloDeSeccion>
      <View style={estilos.tarjeta}>
        {servicios.map((servicio, indice) => (
          <View
            key={servicio.codigo}
            testID={`servicio-${servicio.codigo}`}
            style={[estilos.fila, indice > 0 ? estilos.filaSiguiente : null]}
          >
            <Text style={estilos.nombre}>{servicio.nombre}</Text>
            <Text style={estilos.precio}>{precioEnTexto(servicio)}</Text>
          </View>
        ))}
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  seccion: {
    gap: espaciado[3],
  },
  tarjeta: {
    borderWidth: 1,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  fila: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[2],
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[3],
  },
  filaSiguiente: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: color.semantico.borde,
  },
  nombre: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    fontWeight: tipografia.peso.semifuerte,
  },
  precio: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    fontVariant: ['tabular-nums'],
  },
});
