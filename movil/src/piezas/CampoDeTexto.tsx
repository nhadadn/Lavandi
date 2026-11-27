// Campo de texto de formulario, con su etiqueta y su error junto a él (registro-E04). Con varias líneas, para las
// referencias de una dirección (direcciones-E01), crece hacia abajo desde arriba. El código postal de una dirección
// (direcciones-E20) lleva el teclado de números.
// Colores y medidas de theme.ts (ADR-0013); el alto mínimo es el área táctil del design system y el borde, el grosor de
// control. Con el foco, el borde pasa al grosor y al color de foco: "foco visible siempre", contorno de 3 px
// (vault/design-system/componentes.md, reglas transversales; DD-133, punto 5).
import { useState } from 'react';
import { StyleSheet, Text, TextInput, View, type TextInputProps } from 'react-native';

import { letra } from '../tema/letra';
import { tema } from '../tema/theme';

export type TipoDeCampo =
  | 'texto'
  | 'correo'
  | 'contrasena'
  | 'contrasena-nueva'
  | 'fecha'
  | 'codigo-postal'
  | 'precio'
  | 'telefono';

interface Propiedades {
  /** El nombre del campo en la API: une el campo con su error (`campo-<nombre>`). */
  nombre: string;
  etiqueta: string;
  valor: string;
  alCambiar: (texto: string) => void;
  error?: string;
  tipo?: TipoDeCampo;
  /** Varias líneas de texto, como las referencias de una dirección. */
  multilinea?: boolean;
}

// Lo que cada tipo le pide al teclado y al autocompletado del sistema.
const POR_TIPO: Record<TipoDeCampo, TextInputProps> = {
  texto: {},
  correo: {
    keyboardType: 'email-address',
    autoCapitalize: 'none',
    autoCorrect: false,
    autoComplete: 'email',
    textContentType: 'emailAddress',
  },
  contrasena: {
    secureTextEntry: true,
    autoCapitalize: 'none',
    autoCorrect: false,
    autoComplete: 'current-password',
    textContentType: 'password',
  },
  'contrasena-nueva': {
    secureTextEntry: true,
    autoCapitalize: 'none',
    autoCorrect: false,
    autoComplete: 'new-password',
    textContentType: 'newPassword',
  },
  // Una fecha DD/MM/AAAA, como el filtro de "Mis pedidos" (historial-E03): el teclado de números de las dos plataformas,
  // que no trae la diagonal; la pone quien usa el campo. La muestra del formato va en el texto suave.
  fecha: {
    keyboardType: 'number-pad',
    maxLength: 'DD/MM/AAAA'.length,
    placeholder: 'DD/MM/AAAA',
    placeholderTextColor: tema.color.semantico.texto_suave,
  },
  // El código postal de México son cinco números (direcciones-E20). El teclado de números de las dos plataformas, y el
  // autocompletado del sistema con el de la persona.
  'codigo-postal': {
    keyboardType: 'number-pad',
    maxLength: 5,
    autoComplete: 'postal-code',
    textContentType: 'postalCode',
  },
  // Un precio en pesos con decimales, como el precio por kilo de cada servicio de la lavandería (alta-lavanderias-E05):
  // el teclado de números con el punto decimal de las dos plataformas.
  precio: {
    keyboardType: 'decimal-pad',
    autoCorrect: false,
  },
  // El teléfono del repartidor (repartidor-E01): el teclado de teléfono de las dos plataformas y el autocompletado del
  // sistema con el de la persona. Se guarda como se escribe; su formato lo revisa el servidor.
  telefono: {
    keyboardType: 'phone-pad',
    autoComplete: 'tel',
    textContentType: 'telephoneNumber',
  },
};

export function CampoDeTexto({
  nombre,
  etiqueta,
  valor,
  alCambiar,
  error,
  tipo = 'texto',
  multilinea = false,
}: Propiedades) {
  const [enfocado, setEnfocado] = useState(false);

  return (
    <View style={estilos.contenedor} testID={`campo-${nombre}`}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'semifuerte')]}>{etiqueta}</Text>
      <TextInput
        {...POR_TIPO[tipo]}
        accessibilityLabel={etiqueta}
        value={valor}
        onChangeText={alCambiar}
        onFocus={() => setEnfocado(true)}
        onBlur={() => setEnfocado(false)}
        multiline={multilinea}
        style={[
          estilos.entrada,
          multilinea ? estilos.multilinea : null,
          letra('cuerpo', 'regular'),
          error ? estilos.entradaConError : null,
          enfocado ? estilos.entradaConFoco : null,
        ]}
      />
      {error ? (
        <Text role="alert" accessibilityLiveRegion="polite" style={[estilos.error, letra('cuerpo', 'regular')]}>
          {error}
        </Text>
      ) : null}
    </View>
  );
}

const { color, espaciado, radio, tipografia, accesibilidad, grosor } = tema;

// Cuántas líneas se ven de un campo de varias líneas antes de escribir.
const LINEAS_A_LA_VISTA = 3;

// Lo que crece el borde con el foco. Crece hacia adentro, así que el relleno se achica lo mismo y lo que se escribe no
// se mueve.
const CRECE_CON_EL_FOCO = grosor.foco - grosor.control;

const estilos = StyleSheet.create({
  contenedor: {
    gap: espaciado[1],
  },
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  // Como el .entrada de los mocks (Propuesta 04, C-230).
  entrada: {
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
  multilinea: {
    minHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal * LINEAS_A_LA_VISTA + espaciado[2] * 2,
    textAlignVertical: 'top',
  },
  entradaConError: {
    borderColor: color.estado.error,
  },
  // Mientras escribe, el foco se ve aunque el campo tenga error: el error sigue escrito debajo y su color vuelve al
  // salir.
  entradaConFoco: {
    borderWidth: grosor.foco,
    borderColor: color.semantico.foco,
    paddingHorizontal: espaciado[3] - CRECE_CON_EL_FOCO,
    paddingVertical: espaciado[2] - CRECE_CON_EL_FOCO,
  },
  error: {
    color: color.estado.error,
    fontSize: tipografia.escala.menor,
  },
});
