// Las reseñas de la lavandería (perfil-lavanderia-E09): cada una con sus estrellas, su fecha y su comentario si lo
// tiene, sin nada de quien calificó (perfil-lavanderia-E08; DD-096). Se ven las tres más recientes y, si hay más,
// "Ver todas las reseñas" muestra las demás en la misma pantalla (DD-135, punto 3). Del mock a05-lavanderia no entran el
// nombre de quien reseña (DD-136, punto 11) ni la fecha relativa ("hace 2 semanas"): la fecha va completa. Las estrellas
// son caracteres, porque la app no tiene una librería de íconos; su trazo se revisa en un teléfono real. Colores y
// medidas de theme.ts (ADR-0013).
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { Enlace } from '../piezas/Enlace';
import { TituloDeSeccion } from '../piezas/TituloDeSeccion';
import { color, espaciado, radio, tipografia } from '../tema/theme';
import type { Resena } from './api';
import { estrellasALaVista, estrellasParaLeer, fechaDeLaResena, TEXTOS_DEL_PERFIL } from './textos';

// DD-135, punto 3: cuántas se ven antes de "ver todas".
const RESENAS_A_LA_VISTA = 3;

export function Resenas({ resenas }: { resenas: Resena[] }) {
  const [todas, setTodas] = useState(false);
  // De la más reciente a la más antigua, aunque el servidor ya las entregue así.
  const ordenadas = [...resenas].sort((una, otra) => Date.parse(otra.fecha) - Date.parse(una.fecha));
  const hayMas = ordenadas.length > RESENAS_A_LA_VISTA;
  const visibles = todas ? ordenadas : ordenadas.slice(0, RESENAS_A_LA_VISTA);

  return (
    <View style={estilos.seccion} testID="resenas">
      <TituloDeSeccion>{TEXTOS_DEL_PERFIL.resenas}</TituloDeSeccion>
      {visibles.map((resena, indice) => (
        <View key={`${resena.fecha}-${indice}`} style={estilos.tarjeta} testID="resena">
          <View style={estilos.cabeza}>
            <Text accessible accessibilityLabel={estrellasParaLeer(resena.estrellas)} style={estilos.estrellas}>
              {estrellasALaVista(resena.estrellas)}
            </Text>
            <Text style={estilos.fecha}>{fechaDeLaResena(resena)}</Text>
          </View>
          {resena.comentario ? <Text style={estilos.comentario}>{resena.comentario}</Text> : null}
        </View>
      ))}
      {hayMas && !todas ? <Enlace texto={TEXTOS_DEL_PERFIL.verTodas} alTocar={() => setTodas(true)} /> : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  seccion: {
    gap: espaciado[3],
  },
  tarjeta: {
    gap: espaciado[2],
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[3],
    borderWidth: 1,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  cabeza: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[2],
  },
  estrellas: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
    letterSpacing: tipografia.escala.cuerpo_grande * tipografia.espaciado_letra.versalita,
  },
  fecha: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  comentario: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
  },
});
