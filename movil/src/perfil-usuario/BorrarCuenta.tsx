// "Eliminar mi cuenta" al final de "Mi perfil" (perfil-usuario-E15; ADR-0005: "Borrar la cuenta se puede desde la app,
// como piden las dos tiendas"). Antes de borrar nada pide confirmación con el diálogo del sistema de cada teléfono, como
// la imagen a15-perfil-eliminar de la Propuesta 04: "¿Eliminar tu cuenta?", "No se puede deshacer.", "Cancelar" y
// "Eliminar". El design system no tiene un diálogo propio y el del sistema no necesita uno.
//
// Al confirmar, DELETE /api/cuenta con la sesión. Con el 204, la sesión se borra del teléfono como en "Cerrar sesión"
// (registro-E13; su pedido de revocar el token ya revocado responde 401 y no se espera) y la app abre la bienvenida como
// primera pantalla: "atrás" ya no vuelve a las pantallas de la cuenta borrada. Si el borrado no sale, sin escenario
// propio: los mensajes generales de la app, y la sesión sigue como estaba. Qué pasa con los datos de la cuenta es
// perfil-usuario-E16, bloqueado por DD-109.
import { useState } from 'react';
import { Alert, StyleSheet, View } from 'react-native';

import { MENSAJES } from '../acceso/mensajes';
import type { Falla } from '../configuracion/api';
import { useAbrirComoPrimera } from '../navegacion/regresar';
import { Boton } from '../piezas/Boton';
import { MensajeDeError } from '../piezas/MensajeDeError';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { espaciado } from '../tema/theme';
import { borrarCuenta } from './api';
import { TEXTOS_DEL_PERFIL_DE_USUARIO as TEXTOS } from './textos';

export function BorrarCuenta() {
  const { token, cerrar } = useSesion();
  const abrirComoPrimera = useAbrirComoPrimera();
  const [borrando, setBorrando] = useState(false);
  const [falla, setFalla] = useState<Falla | null>(null);

  function pedirConfirmacion() {
    Alert.alert(TEXTOS.confirmarEliminar, TEXTOS.noSePuedeDeshacer, [
      { text: TEXTOS.cancelar, style: 'cancel' },
      { text: TEXTOS.eliminar, style: 'destructive', onPress: () => void borrar() },
    ]);
  }

  async function borrar() {
    if (!token) {
      return;
    }
    setBorrando(true);
    setFalla(null);
    const resultado = await borrarCuenta(token);
    if (!resultado.ok) {
      setBorrando(false);
      setFalla(resultado);
      return;
    }
    try {
      await cerrar();
    } catch {
      // El teléfono no pudo borrar el token, pero la app ya quedó sin sesión y el servidor ya lo revocó: se sale igual.
    }
    abrirComoPrimera('/bienvenida');
  }

  return (
    <View style={estilos.seccion}>
      <MensajeDeError texto={falla ? mensajeDelBorrado(falla) : undefined} />
      <Boton variante="secundario" ocupado={borrando} onPress={pedirConfirmacion}>
        {TEXTOS.eliminarMiCuenta}
      </Boton>
    </View>
  );
}

function mensajeDelBorrado(falla: Falla): string {
  return falla.motivo === 'sin-conexion' ? MENSAJES.sinConexion : MENSAJES.servidor;
}

const estilos = StyleSheet.create({
  seccion: {
    gap: espaciado[2],
    paddingTop: espaciado[4],
  },
});
