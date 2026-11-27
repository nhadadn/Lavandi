// "Cerrar sesión" con la confirmación de la app (registro-E13 y registro-E33): antes de cerrar nada pregunta con el
// diálogo del sistema de cada teléfono, como "Eliminar mi cuenta" (src/perfil-usuario/BorrarCuenta.tsx): "¿Cerrar
// sesión?", "Cancelar" y "Cerrar sesión". El design system no tiene un diálogo propio y el del sistema no necesita uno.
//
// Al confirmar, borra la sesión del teléfono, pide al servidor revocar el token sin esperarlo y abre el inicio de
// sesión como primera pantalla (C-237; DD-131, punto 3). Lo usan los módulos de la lavandería, del repartidor y de la
// administración (administracion-app-E04); el inicio del usuario conserva el suyo, sin la confirmación, mientras su
// perfil no lo tenga (app/inicio.tsx).
import { useState } from 'react';
import { Alert } from 'react-native';

import { useAbrirComoPrimera } from '../navegacion/regresar';
import { Boton } from '../piezas/Boton';
import { useSesion } from './ProveedorDeSesion';

export const TEXTOS_DEL_CIERRE = {
  cerrarSesion: 'Cerrar sesión',
  confirmar: '¿Cerrar sesión?',
  cancelar: 'Cancelar',
} as const;

export function CerrarSesion() {
  const abrirComoPrimera = useAbrirComoPrimera();
  const { cerrar } = useSesion();
  const [cerrando, setCerrando] = useState(false);

  function pedirConfirmacion() {
    Alert.alert(TEXTOS_DEL_CIERRE.confirmar, undefined, [
      { text: TEXTOS_DEL_CIERRE.cancelar, style: 'cancel' },
      { text: TEXTOS_DEL_CIERRE.cerrarSesion, style: 'destructive', onPress: () => void cerrarSesion() },
    ]);
  }

  async function cerrarSesion() {
    setCerrando(true);
    try {
      await cerrar();
    } catch {
      // El teléfono no pudo borrar el token, pero la app ya quedó sin sesión y el servidor lo revoca: se sale igual.
    }
    // Se vacía la pila: "atrás" ya no regresa a las pantallas de la sesión cerrada.
    abrirComoPrimera('/inicio-de-sesion');
  }

  return (
    <Boton variante="secundario" ocupado={cerrando} onPress={pedirConfirmacion}>
      {TEXTOS_DEL_CIERRE.cerrarSesion}
    </Boton>
  );
}
