// La foto de perfil, arriba de "Mi perfil" (perfil-usuario-E03 y E04; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3,
// Módulo A #15, "foto"). La persona toma una foto con la cámara o elige una de su galería; la app la comprime en el
// teléfono con los parámetros de configuración y la sube en cuanto la tiene, sin otro botón (ADR-0006). Es la misma
// preparación de la foto de la lavandería (src/alta-lavanderias/foto.ts): la cámara pide su permiso, la galería no, y
// cerrar sin foto no cambia nada.
//
// - Con el 200, el perfil se reemplaza con el que devolvió el servidor: la foto se ve desde su URL firmada, no desde el
//   teléfono, y dice "Guardamos tu foto.". La URL vence; al volver a abrir el perfil llega otra.
// - Si la API la rechaza (422, perfil-usuario-E04), su error va junto a la foto y la que había sigue a la vista. Lo mismo
//   sin conexión, con los mensajes generales de la app.
// - Mientras se prepara y sube, los dos botones quedan apagados: no se sube otra encima.
//
// La composición sigue la imagen a15-perfil-foto de la Propuesta 04: la foto en un círculo, arriba y al centro, y los
// dos botones "Tomar foto" y "Elegir de la galería". Los botones van a la vista, como en el registro de la lavandería, y
// no en una hoja que se abre aparte: el design system no tiene hoja ni diálogo (design.md del cambio, "Pantallas sin
// componente"), y así no hace falta uno. Sin foto, el círculo queda vacío y el lector de pantalla lo salta; del mock no
// entran las iniciales. Colores y medidas de theme.ts (ADR-0013).
import { useRef, useState } from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';

import { elegirFoto, tomarFoto, type ResultadoDeLaFoto } from '../alta-lavanderias/foto';
import type { FotoFirmada } from '../configuracion/lectura';
import { Boton } from '../piezas/Boton';
import { MensajeDeError } from '../piezas/MensajeDeError';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { letra } from '../tema/letra';
import { color, espaciado, radio, tipografia } from '../tema/theme';
import { subirFoto, type PerfilDeUsuario } from './api';
import { mensajeDeLaFoto, TEXTOS_DEL_PERFIL_DE_USUARIO as TEXTOS } from './textos';

// El lado del círculo, el del mock a15-perfil, con las medidas de tokens.json.
const LADO_DE_LA_FOTO = espaciado[8] + espaciado[5];

type Origen = 'camara' | 'galeria';

interface Propiedades {
  foto: FotoFirmada | null;
  /** Recibe el perfil que devolvió el servidor, con la foto que guardó. */
  alGuardar: (perfil: PerfilDeUsuario) => void;
}

export function FotoDePerfil({ foto, alGuardar }: Propiedades) {
  const { token } = useSesion();
  const [trabajando, setTrabajando] = useState<Origen | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [guardada, setGuardada] = useState(false);
  // Para no empezar otra mientras una sigue, aunque los dos toques lleguen antes de que se apaguen los botones.
  const enCurso = useRef(false);

  async function ponerFoto(origen: Origen, obtener: () => Promise<ResultadoDeLaFoto>) {
    if (enCurso.current || !token) {
      return;
    }
    enCurso.current = true;
    setTrabajando(origen);
    setError(null);
    setGuardada(false);
    try {
      const preparada = await obtener();
      if (!preparada.ok) {
        if (preparada.motivo === 'sin-permiso') {
          setError(TEXTOS.sinPermisoDeLaCamara);
        } else if (preparada.motivo === 'no-se-pudo') {
          setError(TEXTOS.fotoNoPreparada);
        }
        return;
      }
      const resultado = await subirFoto(token, preparada.foto);
      if (!resultado.ok) {
        setError(mensajeDeLaFoto(resultado));
        return;
      }
      setGuardada(true);
      alGuardar(resultado.datos);
    } finally {
      enCurso.current = false;
      setTrabajando(null);
    }
  }

  return (
    <View style={estilos.seccion} testID="campo-foto">
      <View
        style={estilos.circulo}
        accessibilityElementsHidden={!foto}
        importantForAccessibility={foto ? 'auto' : 'no-hide-descendants'}
      >
        {foto ? (
          <Image
            accessible
            accessibilityRole="image"
            accessibilityLabel={TEXTOS.tuFotoDePerfil}
            source={{ uri: foto.url }}
            resizeMode="cover"
            style={estilos.foto}
          />
        ) : null}
      </View>
      <Boton
        variante="secundario"
        ocupado={trabajando === 'camara'}
        deshabilitado={trabajando !== null}
        onPress={() => void ponerFoto('camara', tomarFoto)}
      >
        {TEXTOS.tomarFoto}
      </Boton>
      <Boton
        variante="secundario"
        ocupado={trabajando === 'galeria'}
        deshabilitado={trabajando !== null}
        onPress={() => void ponerFoto('galeria', elegirFoto)}
      >
        {TEXTOS.elegirDeLaGaleria}
      </Boton>
      <MensajeDeError texto={error ?? undefined} />
      {guardada ? (
        <Text accessibilityLiveRegion="polite" style={[estilos.confirmacion, letra('cuerpo', 'regular')]}>
          {TEXTOS.fotoGuardada}
        </Text>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  seccion: {
    gap: espaciado[3],
  },
  circulo: {
    alignSelf: 'center',
    width: LADO_DE_LA_FOTO,
    height: LADO_DE_LA_FOTO,
    borderRadius: radio.pildora,
    overflow: 'hidden',
    backgroundColor: color.semantico.superficie_hundida,
  },
  foto: {
    width: '100%',
    height: '100%',
  },
  confirmacion: {
    color: color.estado.exito,
    fontSize: tipografia.escala.menor,
  },
});
