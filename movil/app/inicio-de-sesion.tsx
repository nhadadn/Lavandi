// Pantalla de inicio de sesión (registro-E09, E19, E20 y E26): correo y contraseña, "Continuar con Google" si el
// entorno trae el cliente de Google y la app no corre en Expo Go (base-E11), y "¿Olvidaste tu contraseña?". En iPhone,
// "Continuar con Apple" es la tarea 4.3, bloqueada por DD-069; en Android no se ofrece (ADR-0005).
//
// Es también la oferta de registrarse o iniciar sesión, con "¿No tienes cuenta? Regístrate" (bienvenida-E04, C-237):
// sin sesión, la app llega aquí al terminar las informativas, al abrirse con la bienvenida ya vista y al cerrar la
// sesión. Abierto como primera pantalla es la raíz sin sesión y no tiene "Regresar"; si se llegó desde otra, vuelve a
// ella. "Regístrate" abre el registro encima, para que el regreso de Android y el gesto de iPhone vuelvan aquí (C-236).
//
// El aspecto es el de la imagen a02-inicio-sesion de la Propuesta 04 de los mocks (tarea 2.3, C-230): encabezado
// con regreso, el logotipo arriba y al centro (su archivo es de nombre neutro: C-237), el saludo con el nombre de la
// configuración, los campos, "¿Olvidaste tu contraseña?" a la derecha, el botón, "o" y Google abajo, con la "G" de Google
// en lugar de la de relleno del mock (DD-133, punto 3). Del mock no entra el botón para ver la contraseña, que no tiene
// escenario.
//
// Una cuenta de administración no recibe sesión en la app (registro-E33): la pantalla muestra el aviso de que entra por
// el panel y, si la configuración trae la dirección del panel (EXPO_PUBLIC_PANEL_URL), el botón "Abrir el panel", que lo
// abre en la vista de navegador del sistema dentro de la app (administracion-E25; src/acceso/BotonDelPanel.tsx).
import { useRouter } from 'expo-router';
import { useState } from 'react';

import { iniciarSesion } from '../src/acceso/api';
import { BotonDelPanel } from '../src/acceso/BotonDelPanel';
import { googleDisponible } from '../src/acceso/google';
import { useEntrada } from '../src/acceso/useEntrada';
import { nombreDeLaApp } from '../src/configuracion/entorno';
import { useRegresarSiHayAnterior } from '../src/navegacion/regresar';
import { Boton } from '../src/piezas/Boton';
import { BotonDeGoogle } from '../src/piezas/BotonDeGoogle';
import { CampoDeTexto } from '../src/piezas/CampoDeTexto';
import { Enlace } from '../src/piezas/Enlace';
import { Logotipo } from '../src/piezas/Logotipo';
import { MensajeDeError } from '../src/piezas/MensajeDeError';
import { Pantalla } from '../src/piezas/Pantalla';
import { Presentacion } from '../src/piezas/Presentacion';
import { Separador } from '../src/piezas/Separador';

export default function InicioDeSesion() {
  const router = useRouter();
  const regresar = useRegresarSiHayAnterior();
  const { enviando, errorGeneral, sinAccesoALaApp, errorDe, entrarCon, entrarConGoogle } = useEntrada();
  const [correo, setCorreo] = useState('');
  const [password, setPassword] = useState('');

  return (
    <Pantalla desplazable encabezado={{ titulo: 'Iniciar sesión', alRegresar: regresar }}>
      <Logotipo />
      <Presentacion titulo="Hola de nuevo" texto={`Entra a ${nombreDeLaApp()}.`} />
      <CampoDeTexto
        nombre="correo"
        etiqueta="Correo electrónico"
        tipo="correo"
        valor={correo}
        alCambiar={setCorreo}
        error={errorDe('correo')}
      />
      <CampoDeTexto
        nombre="password"
        etiqueta="Contraseña"
        tipo="contrasena"
        valor={password}
        alCambiar={setPassword}
        error={errorDe('password')}
      />
      <Enlace texto="¿Olvidaste tu contraseña?" alinear="fin" alTocar={() => router.push('/recuperar-contrasena')} />
      <MensajeDeError texto={errorGeneral} />
      <BotonDelPanel conElAviso={sinAccesoALaApp} />
      <Boton ocupado={enviando} onPress={() => entrarCon(() => iniciarSesion({ correo, password }))}>
        Iniciar sesión
      </Boton>
      {googleDisponible() ? (
        <>
          <Separador texto="o" />
          <BotonDeGoogle onPress={entrarConGoogle} />
        </>
      ) : null}
      <Enlace
        pregunta="¿No tienes cuenta?"
        texto="Regístrate"
        alinear="centro"
        alTocar={() => router.push('/registro')}
      />
    </Pantalla>
  );
}
