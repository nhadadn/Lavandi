// Las direcciones guardadas del perfil (perfil-usuario-E05): las favoritas de la cuenta, que el servidor da solo de la
// sesión (GET /api/direcciones/favoritas, del cambio direcciones). Una dirección que no es favorita vive solo en su
// pedido (direcciones-E08) y no aparece aquí. Cada una con su nombre, sus señas y, si los tiene, el municipio y las
// referencias, en una tarjeta con una fila por dirección, como la sección "Direcciones guardadas" de la imagen
// a15-perfil de la Propuesta 04.
//
// Del mock no entran el lápiz de cada fila ni "Agregar dirección": la edición de una dirección guardada no existe todavía
// en el cambio direcciones ni en el servidor (el "Y" de perfil-usuario-E05), y el alta vive en el paso de la dirección
// del pedido. No es uno de los seis componentes del design system: se arma con los tokens (ADR-0013), como la lista de
// servicios del perfil de la lavandería. Sin conexión o con el servidor caído, sin escenario propio: los mensajes
// generales de la app.
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { mensajeDeLaFalla } from '../cercanas/textos';
import type { Direccion } from '../direcciones/api';
import { complementoEnTexto, senasEnTexto } from '../direcciones/textos';
import type { EstadoDeLasFavoritas } from '../direcciones/useFavoritas';
import { MensajeDeError } from '../piezas/MensajeDeError';
import { TituloDeSeccion } from '../piezas/TituloDeSeccion';
import { letra } from '../tema/letra';
import { color, espaciado, radio, tipografia } from '../tema/theme';
import { TEXTOS_DEL_PERFIL_DE_USUARIO } from './textos';

export function DireccionesGuardadas({ favoritas }: { favoritas: EstadoDeLasFavoritas }) {
  return (
    <View style={estilos.seccion} testID="direcciones-guardadas">
      <TituloDeSeccion>{TEXTOS_DEL_PERFIL_DE_USUARIO.direccionesGuardadas}</TituloDeSeccion>
      {favoritas.fase === 'cargando' ? (
        <View style={estilos.fila}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>
            {TEXTOS_DEL_PERFIL_DE_USUARIO.cargandoDirecciones}
          </Text>
        </View>
      ) : null}
      {favoritas.fase === 'error' ? <MensajeDeError texto={mensajeDeLaFalla(favoritas.falla)} /> : null}
      {favoritas.fase === 'listas' && favoritas.favoritas.length === 0 ? (
        <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS_DEL_PERFIL_DE_USUARIO.sinDirecciones}</Text>
      ) : null}
      {favoritas.fase === 'listas' && favoritas.favoritas.length > 0 ? (
        <View style={estilos.tarjeta}>
          {favoritas.favoritas.map((direccion, indice) => (
            <FilaDeDireccion key={direccion.id_publico} direccion={direccion} primera={indice === 0} />
          ))}
        </View>
      ) : null}
    </View>
  );
}

// El nombre de la favorita, o sus señas si no tiene, y debajo las señas, el municipio y las referencias: lo mismo que
// la opción de dirección del pedido (direcciones/OpcionDeDireccion.tsx), sin elegirla.
function FilaDeDireccion({ direccion, primera }: { direccion: Direccion; primera: boolean }) {
  const senas = senasEnTexto(direccion);
  const titulo = direccion.alias ?? senas;
  const lineas = [direccion.alias ? senas : '', complementoEnTexto(direccion)].filter(Boolean);
  return (
    <View testID={`direccion-${direccion.id_publico}`} style={[estilos.direccion, primera ? null : estilos.siguiente]}>
      <Text style={[estilos.titulo, letra('cuerpo', 'semifuerte')]}>{titulo}</Text>
      {lineas.map((linea) => (
        <Text key={linea} style={[estilos.linea, letra('cuerpo', 'regular')]}>
          {linea}
        </Text>
      ))}
    </View>
  );
}

const estilos = StyleSheet.create({
  seccion: {
    gap: espaciado[3],
  },
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[2],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  tarjeta: {
    borderWidth: 1,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  direccion: {
    gap: espaciado[1],
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[3],
  },
  siguiente: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: color.semantico.borde,
  },
  titulo: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  linea: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
