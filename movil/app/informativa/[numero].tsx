// Las tres pantallas informativas, en orden (bienvenida-E03), con su título, texto e ilustración del archivo de
// contenido (bienvenida-E05). Al avanzar desde la tercera, la app recuerda que ya las vio (bienvenida-E02) y abre el
// inicio de sesión, que ofrece registrarse o iniciar sesión (bienvenida-E04, C-237), como primera pantalla: "atrás" ya
// no vuelve a las informativas (DD-131, punto 3, por delegación, C-236). Una ruta por informativa, para que el regreso
// de Android vuelva a la anterior. Sin "Saltar": no tiene cita (C-225).
//
// El aspecto es el de las imágenes a01-informativas de la Propuesta 04 de los mocks (tarea 1.3, C-230): la ilustración,
// el título y el texto al centro, y abajo los puntos de avance y "Siguiente", a todo lo ancho. Del mock no entran el
// encabezado con "Saltar", la marca "Texto provisional" ni, en la tercera, "Crear cuenta" y "Ya tengo cuenta": aquí la
// tercera sigue al inicio de sesión, como dice bienvenida-E04.
import { Redirect, useLocalSearchParams, useRouter } from 'expo-router';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { contenidoDeBienvenida } from '../../src/contenido/bienvenida';
import { useAbrirComoPrimera } from '../../src/navegacion/regresar';
import { Boton } from '../../src/piezas/Boton';
import { Ilustracion } from '../../src/piezas/Ilustracion';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Presentacion } from '../../src/piezas/Presentacion';
import { PuntosDeAvance } from '../../src/piezas/PuntosDeAvance';

export default function Informativa() {
  const router = useRouter();
  const abrirComoPrimera = useAbrirComoPrimera();
  const { numero } = useLocalSearchParams<{ numero: string }>();
  const { informativas, botones } = contenidoDeBienvenida;
  const posicion = Number(numero);
  const informativa = Number.isInteger(posicion) ? informativas[posicion - 1] : undefined;

  // Un número que no corresponde a ninguna informativa vuelve al arranque, que decide a dónde llevar a la persona.
  if (!informativa) {
    return <Redirect href="/" />;
  }

  async function avanzar() {
    if (posicion < informativas.length) {
      router.push(`/informativa/${posicion + 1}`);
      return;
    }
    await marcarBienvenidaVista();
    abrirComoPrimera('/inicio-de-sesion');
  }

  return (
    <Pantalla
      testID={`informativa-${posicion}`}
      justificar="center"
      acciones={
        <>
          <PuntosDeAvance total={informativas.length} actual={posicion} />
          <Boton onPress={avanzar}>{botones.siguiente}</Boton>
        </>
      }
    >
      <Ilustracion fuente={informativa.ilustracion} />
      <Presentacion titulo={informativa.titulo} texto={informativa.texto} />
    </Pantalla>
  );
}
