// A dónde llega la persona al abrir la app (bienvenida-E01 y bienvenida-E02, C-225): con sesión, a la primera pantalla
// del módulo de su cuenta, el inicio del usuario o los pedidos de la lavandería (registro-E33); sin sesión y sin haber
// visto la bienvenida, a la bienvenida; sin sesión y ya vista, al inicio de sesión, que ofrece registrarse o iniciar
// sesión (bienvenida-E04, C-237). Mientras lee el teléfono no muestra nada.
import { Redirect } from 'expo-router';
import { useEffect, useState } from 'react';

import { leerBienvenidaVista } from '../src/bienvenida/vista';
import { INICIO_DEL_MODULO } from '../src/sesion/modulos';
import { useSesion } from '../src/sesion/ProveedorDeSesion';

export default function Arranque() {
  const { estado, modulo } = useSesion();
  const [bienvenidaVista, setBienvenidaVista] = useState<boolean | null>(null);

  useEffect(() => {
    let montado = true;
    leerBienvenidaVista().then((vista) => {
      if (montado) {
        setBienvenidaVista(vista);
      }
    });
    return () => {
      montado = false;
    };
  }, []);

  if (estado === 'con-sesion' && modulo) {
    return <Redirect href={INICIO_DEL_MODULO[modulo]} />;
  }
  if (estado === 'cargando' || bienvenidaVista === null) {
    return null;
  }
  return <Redirect href={bienvenidaVista ? '/inicio-de-sesion' : '/bienvenida'} />;
}
