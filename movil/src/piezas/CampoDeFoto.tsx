// Un campo de foto de formulario: la persona toma una foto con la cámara o, si el campo lo ofrece, la elige de su
// galería; la app la prepara en el teléfono con los parámetros de configuración (src/alta-lavanderias/foto.ts, ADR-0006)
// y la muestra, y el formulario la manda al enviar. Lo usan el registro del repartidor, con su foto de perfil
// (repartidor-E01), y la entrega, con la foto de evidencia, que solo se toma con la cámara (repartidor-E23). Es la misma
// preparación de la foto de la lavandería y de la foto de perfil del usuario:
//
// - la cámara pide su permiso antes de abrirse; sin él, no se abre y el campo lo dice;
// - la galería no pide permiso: el selector del sistema solo entrega la foto que la persona elige;
// - cerrar la cámara o la galería sin foto no cambia nada;
// - mientras se prepara una, los botones quedan apagados: no se prepara otra encima.
//
// El error del servidor, o el de la foto en el teléfono, va al final del campo. No es uno de los seis componentes del
// design system: se arma con las piezas y los tokens (ADR-0013), como el registro de la lavandería. La foto de perfil va
// en un círculo, como la imagen a15-perfil-foto; la de evidencia, a todo lo ancho, en la proporción de la cámara.
import { useState } from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';

import { elegirFoto, tomarFoto, type ResultadoDeLaFoto } from '../alta-lavanderias/foto';
import { letra } from '../tema/letra';
import { color, espaciado, radio, tipografia } from '../tema/theme';
import { Boton } from './Boton';

/** Los textos del campo, de la pantalla que lo usa. */
export interface TextosDelCampoDeFoto {
  /** La etiqueta del campo, como la de CampoDeTexto. */
  titulo: string;
  /** Lo que lee el lector de pantalla de la foto que ya está. */
  laFoto: string;
  tomarFoto: string;
  elegirDeLaGaleria?: string;
  sinPermisoDeLaCamara: string;
  fotoNoPreparada: string;
}

interface Propiedades {
  /** El nombre del campo en la API: une el campo con su error (`campo-<nombre>`). */
  nombre: string;
  textos: TextosDelCampoDeFoto;
  /** La dirección de la foto ya preparada, en el teléfono; null si no hay. */
  foto: string | null;
  alCambiar: (uri: string) => void;
  /** El error del servidor para la foto. */
  error?: string;
  /** Solo la cámara, como la evidencia de entrega; por omisión, también la galería. */
  soloCamara?: boolean;
  forma?: 'circulo' | 'ancho';
  /** Mientras el formulario se manda: no se cambia la foto. */
  deshabilitado?: boolean;
}

type Origen = 'camara' | 'galeria';

// El lado del círculo, el de la foto de perfil del usuario (src/perfil-usuario/FotoDePerfil.tsx).
const LADO_DEL_CIRCULO = espaciado[8] + espaciado[5];

// La foto ancha se ve en la proporción de la cámara de los teléfonos, como la de la lavandería.
const PROPORCION_DE_LA_CAMARA = 4 / 3;

export function CampoDeFoto({
  nombre,
  textos,
  foto,
  alCambiar,
  error,
  soloCamara = false,
  forma = 'ancho',
  deshabilitado = false,
}: Propiedades) {
  const [preparando, setPreparando] = useState<Origen | null>(null);
  const [errorDeLaFoto, setErrorDeLaFoto] = useState<string | null>(null);

  async function ponerFoto(origen: Origen, obtener: () => Promise<ResultadoDeLaFoto>) {
    if (preparando) {
      return;
    }
    setPreparando(origen);
    const resultado = await obtener();
    setPreparando(null);
    if (resultado.ok) {
      setErrorDeLaFoto(null);
      alCambiar(resultado.foto.uri);
    } else if (resultado.motivo === 'sin-permiso') {
      setErrorDeLaFoto(textos.sinPermisoDeLaCamara);
    } else if (resultado.motivo === 'no-se-pudo') {
      setErrorDeLaFoto(textos.fotoNoPreparada);
    }
  }

  const aviso = errorDeLaFoto ?? error;
  const apagado = deshabilitado || preparando !== null;

  return (
    <View style={estilos.campo} testID={`campo-${nombre}`}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'semifuerte')]}>{textos.titulo}</Text>
      {foto ? (
        <View style={forma === 'circulo' ? estilos.circulo : estilos.ancha}>
          <Image
            accessible
            accessibilityRole="image"
            accessibilityLabel={textos.laFoto}
            source={{ uri: foto }}
            resizeMode="cover"
            style={estilos.foto}
          />
        </View>
      ) : null}
      <Boton
        variante="secundario"
        ocupado={preparando === 'camara'}
        deshabilitado={apagado}
        onPress={() => void ponerFoto('camara', tomarFoto)}
      >
        {textos.tomarFoto}
      </Boton>
      {!soloCamara && textos.elegirDeLaGaleria ? (
        <Boton
          variante="secundario"
          ocupado={preparando === 'galeria'}
          deshabilitado={apagado}
          onPress={() => void ponerFoto('galeria', elegirFoto)}
        >
          {textos.elegirDeLaGaleria}
        </Boton>
      ) : null}
      {aviso ? (
        <Text role="alert" accessibilityLiveRegion="polite" style={[estilos.aviso, letra('cuerpo', 'regular')]}>
          {aviso}
        </Text>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  campo: {
    gap: espaciado[3],
  },
  // La etiqueta de CampoDeTexto.
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  circulo: {
    alignSelf: 'center',
    width: LADO_DEL_CIRCULO,
    height: LADO_DEL_CIRCULO,
    borderRadius: radio.pildora,
    overflow: 'hidden',
    backgroundColor: color.semantico.superficie_hundida,
  },
  ancha: {
    width: '100%',
    aspectRatio: PROPORCION_DE_LA_CAMARA,
    borderRadius: radio.grande,
    overflow: 'hidden',
    backgroundColor: color.semantico.superficie_hundida,
  },
  foto: {
    width: '100%',
    height: '100%',
  },
  aviso: {
    color: color.estado.error,
    fontSize: tipografia.escala.menor,
  },
});
