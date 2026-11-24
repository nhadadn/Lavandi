// Las estrellas de la calificación del servicio (historial-E06 a E08): cinco, de 1 a 5, como un grupo de radio, y se
// elige una tocándola; las anteriores se ven llenas. No hay estrella 0 ni 6: la escala es la del contrato
// (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14). Debajo, cuántas se eligieron ("4 de 5") o "Toca una
// estrella.", y el error del servidor si rechazó las estrellas (422).
//
// No es uno de los seis componentes del design system (vault/design-system/componentes.md): el mock A14 dice "Ninguno de
// los seis". Se arma con los tokens (ADR-0013), como el mock: cada estrella mide al menos el área táctil mínima, y la
// llena usa el color de atención, porque la capa semántica no tiene un amarillo de relleno y no se inventa uno. Las
// estrellas son caracteres, como en las reseñas del perfil de la lavandería, porque la app no tiene una librería de
// íconos; su trazo se revisa en un teléfono real. El número elegido va también en texto: el color nunca solo.
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { accesibilidad, color, espaciado, tipografia } from '../tema/theme';
import { ESTRELLAS_DE_LA_CALIFICACION } from './api';
import { estrellaParaLeer, estrellasElegidas, TEXTOS_DE_LA_CALIFICACION } from './textos';

interface Propiedades {
  /** El nombre del campo en la API: une el grupo con su error (`campo-<nombre>`). */
  nombre: string;
  /** La pregunta que nombra al grupo. */
  etiqueta: string;
  /** Las estrellas elegidas, o null mientras no se elige. */
  elegidas: number | null;
  alElegir: (estrellas: number) => void;
  error?: string;
}

export function SelectorDeEstrellas({ nombre, etiqueta, elegidas, alElegir, error }: Propiedades) {
  return (
    <View style={estilos.contenedor} testID={`campo-${nombre}`}>
      <Text style={[estilos.etiqueta, letra('titulares', 'fuerte')]}>{etiqueta}</Text>
      <View accessibilityRole="radiogroup" accessibilityLabel={etiqueta} style={estilos.estrellas} testID="estrellas">
        {ESTRELLAS_DE_LA_CALIFICACION.map((numero) => {
          const llena = elegidas !== null && numero <= elegidas;
          return (
            <Pressable
              key={numero}
              accessibilityRole="radio"
              accessibilityLabel={estrellaParaLeer(numero)}
              accessibilityState={{ checked: numero === elegidas }}
              onPress={() => alElegir(numero)}
              style={estilos.estrella}
            >
              <Text style={[estilos.caracter, llena ? estilos.llena : estilos.vacia]}>{llena ? '★' : '☆'}</Text>
            </Pressable>
          );
        })}
      </View>
      <Text style={[estilos.leyenda, letra('cuerpo', 'regular')]}>
        {elegidas === null ? TEXTOS_DE_LA_CALIFICACION.tocaUnaEstrella : estrellasElegidas(elegidas)}
      </Text>
      {error ? (
        <Text role="alert" accessibilityLiveRegion="polite" style={[estilos.error, letra('cuerpo', 'regular')]}>
          {error}
        </Text>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  contenedor: {
    gap: espaciado[2],
  },
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  estrellas: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: espaciado[1],
  },
  estrella: {
    minWidth: accesibilidad.area_tactil_minima_dp,
    minHeight: accesibilidad.area_tactil_minima_dp,
    alignItems: 'center',
    justifyContent: 'center',
  },
  caracter: {
    fontSize: tipografia.escala.titulo,
  },
  llena: {
    color: color.estado.atencion,
  },
  vacia: {
    color: color.semantico.borde_fuerte,
  },
  leyenda: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  error: {
    color: color.estado.error,
    fontSize: tipografia.escala.menor,
  },
});
