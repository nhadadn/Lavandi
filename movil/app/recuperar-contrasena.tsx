// "¿Olvidaste tu contraseña?" (registro-E26): manda el correo al servidor y le dice a la persona que revise su buzón.
// El enlace del correo abre una página web del servidor (ADR-0018), y la respuesta es la misma exista o no la cuenta.
//
// El aspecto es el de la imagen a02-recuperar de la Propuesta 04 de los mocks (tarea 2.3, C-230): encabezado con
// regreso, título grande a la izquierda, el texto, el correo y el botón a todo lo ancho; el regreso queda en el
// encabezado. Después de enviar, "Volver a iniciar sesión" es el botón principal, como en mocks/usuario/a02-recuperar.html.
// De ese paso no entran el aviso de que el enlace vence ni "No me llegó, enviar otra vez": no están en las imágenes y el
// segundo es un comportamiento que el escenario no pide.
import { useState } from 'react';

import { pedirRecuperacion, type Falla } from '../src/acceso/api';
import { errorDeCampo, mensajeGeneral } from '../src/acceso/mensajes';
import { useRegresar } from '../src/navegacion/regresar';
import { Boton } from '../src/piezas/Boton';
import { CampoDeTexto } from '../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../src/piezas/MensajeDeError';
import { Pantalla } from '../src/piezas/Pantalla';
import { Presentacion } from '../src/piezas/Presentacion';

const ENCABEZADO = 'Recuperar contraseña';

export default function RecuperarContrasena() {
  const volver = useRegresar('/inicio-de-sesion');
  const [correo, setCorreo] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [falla, setFalla] = useState<Falla>();
  const [enviadoA, setEnviadoA] = useState<string>();

  async function enviar() {
    setFalla(undefined);
    setEnviando(true);
    const resultado = await pedirRecuperacion(correo);
    setEnviando(false);
    if (resultado.ok) {
      setEnviadoA(correo);
    } else {
      setFalla(resultado);
    }
  }

  if (enviadoA !== undefined) {
    return (
      <Pantalla encabezado={{ titulo: ENCABEZADO, alRegresar: volver }}>
        <Presentacion
          titulo="Revisa tu correo"
          texto={`Si hay una cuenta con ${enviadoA}, te llegará un enlace para crear una contraseña nueva.`}
        />
        <Boton onPress={volver}>Volver a iniciar sesión</Boton>
      </Pantalla>
    );
  }

  return (
    <Pantalla desplazable encabezado={{ titulo: ENCABEZADO, alRegresar: volver }}>
      <Presentacion
        titulo="¿Olvidaste tu contraseña?"
        texto="Escribe tu correo. Te enviamos un enlace para crear una contraseña nueva."
        alineacion="inicio"
      />
      <CampoDeTexto
        nombre="correo"
        etiqueta="Correo electrónico"
        tipo="correo"
        valor={correo}
        alCambiar={setCorreo}
        error={errorDeCampo(falla, 'correo')}
      />
      <MensajeDeError texto={falla ? mensajeGeneral(falla, ['correo']) : undefined} />
      <Boton ocupado={enviando} onPress={enviar}>
        Enviar enlace
      </Boton>
    </Pantalla>
  );
}
