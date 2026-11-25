// Lo que comparten el registro y el inicio de sesión: mandar la petición, guardar la sesión y entrar al módulo de la
// cuenta, o quedarse con los errores a la vista (registro-E04, E09 y E19). Cada cuenta entra a su módulo por su rol: la
// de usuario al inicio, la de lavandería a sus pedidos y la de repartidor a los suyos, o a la espera de su alta; la que
// no usa la app, como la de administración, no entra y la pantalla muestra el aviso (registro-E33), con el botón que
// abre el panel si la configuración trae su dirección (administracion-E25). El aviso sale igual si el servidor no le da
// token (403) que si se lo da y la sesión no se abre.
//
// El registro del repartidor también abre su sesión al registrarse (repartidor-E01) y muestra más campos: los suyos se
// le pasan a useEntrada, para que sus errores vayan junto a cada uno y no se repitan arriba del botón.
import { useState } from 'react';

import { useAbrirComoPrimera } from '../navegacion/regresar';
import { INICIO_DEL_MODULO } from '../sesion/modulos';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { iniciarSesionConGoogle, type Falla, type ResultadoDeEntrada, type SesionNueva } from './api';
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
  // El servidor respondió 403 al pedir la sesión: la cuenta no tiene acceso a la app (registro-E33).
  const [sinAcceso, setSinAcceso] = useState(false);

  function limpiar() {
    setFalla(undefined);
    setErrorGeneral(undefined);
    setSinAcceso(false);
    olvidarAviso();
  }

  /** Manda la petición; con la sesión nueva la guarda y entra al módulo de la cuenta. */
  async function entrarCon(pedir: () => Promise<ResultadoDeEntrada<SesionNueva>>) {
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
    if (resultado.motivo === 'sin-acceso') {
      // Sin token, nada que guardar ni que revocar: la pantalla muestra el aviso.
      setSinAcceso(true);
      setErrorGeneral(MENSAJES.sinAccesoALaApp);
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
    /**
     * Si la pantalla muestra el aviso de la cuenta que no usa la app, para ofrecerle el panel (administracion-E25): por
     * el 403, o por el aviso de la sesión, que solo dice eso (ProveedorDeSesion).
     */
    sinAccesoALaApp: sinAcceso || aviso !== null,
    errorDe: (campo: string) => errorDeCampo(falla, campo),
    entrarCon,
    entrarConGoogle,
  };
}
