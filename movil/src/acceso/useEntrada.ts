// Lo que comparten el registro y el inicio de sesión: mandar la petición, guardar la sesión y entrar al módulo de la
// cuenta, o quedarse con los errores a la vista (registro-E04, E09 y E19). Cada cuenta entra a su módulo por su rol: la
// de usuario al inicio, la de lavandería a sus pedidos y la de repartidor a los suyos, o a la espera de su alta; la que
// no usa la app, como la de administración, no entra y la pantalla muestra el aviso de la sesión (registro-E33).
//
// El registro del repartidor también abre su sesión al registrarse (repartidor-E01) y muestra más campos: los suyos se
// le pasan a useEntrada, para que sus errores vayan junto a cada uno y no se repitan arriba del botón.
import { useState } from 'react';

import { useAbrirComoPrimera } from '../navegacion/regresar';
import { INICIO_DEL_MODULO } from '../sesion/modulos';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { iniciarSesionConGoogle, type Falla, type Resultado, type SesionNueva } from './api';
import { pedirIdTokenDeGoogle } from './google';
import { errorDeCampo, MENSAJES, mensajeGeneral } from './mensajes';

// Los campos que el registro y el inicio de sesión muestran: sus errores van junto a ellos; los demás, arriba del botón.
const CAMPOS = ['correo', 'password'] as const;

/** campos: los que la pantalla muestra, cada uno con su error junto a él; por omisión, el correo y la contraseña. */
export function useEntrada(campos: readonly string[] = CAMPOS) {
  const abrirComoPrimera = useAbrirComoPrimera();
  const { iniciar, aviso, olvidarAviso } = useSesion();
  const [falla, setFalla] = useState<Falla>();
  const [errorGeneral, setErrorGeneral] = useState<string>();
  const [enviando, setEnviando] = useState(false);

  function limpiar() {
    setFalla(undefined);
    setErrorGeneral(undefined);
    olvidarAviso();
  }

  /** Manda la petición; con la sesión nueva la guarda y entra al módulo de la cuenta. */
  async function entrarCon(pedir: () => Promise<Resultado<SesionNueva>>) {
    limpiar();
    setEnviando(true);
    const resultado = await pedir();
    if (resultado.ok) {
      const modulo = await iniciar(resultado.datos.token, resultado.datos.usuario.rol);
      if (modulo) {
        // Se vacía la pila: "atrás" ya no regresa al formulario con la sesión abierta.
        abrirComoPrimera(INICIO_DEL_MODULO[modulo]);
        return;
      }
      // La cuenta no usa la app: la sesión no se abrió y la pantalla muestra el aviso.
      setEnviando(false);
      return;
    }
    setFalla(resultado);
    setErrorGeneral(mensajeGeneral(resultado, campos));
    setEnviando(false);
  }

  /** "Continuar con Google": si la persona cierra la ventana, no pasa nada ni se muestra error (registro-E19). */
  async function entrarConGoogle() {
    limpiar();
    const google = await pedirIdTokenDeGoogle();
    if (google.tipo === 'cancelado') {
      return;
    }
    if (google.tipo === 'error') {
      setErrorGeneral(MENSAJES.google);
      return;
    }
    await entrarCon(() => iniciarSesionConGoogle(google.idToken));
  }

  return {
    enviando,
    errorGeneral: errorGeneral ?? aviso ?? undefined,
    errorDe: (campo: string) => errorDeCampo(falla, campo),
    entrarCon,
    entrarConGoogle,
  };
}
