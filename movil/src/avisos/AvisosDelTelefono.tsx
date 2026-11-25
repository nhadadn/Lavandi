// Los avisos del teléfono mientras haya una sesión del usuario (avisos-E03; ADR-0008). Va en la raíz de la app
// (app/_layout.tsx), dentro de la sesión, y no pinta nada: con la sesión de una cuenta de usuario y los avisos
// disponibles, deja el teléfono registrado en la api (src/avisos/telefono.ts); al cerrarse la sesión, deja de hacerlo.
//
// Solo la cuenta de usuario: si la lavandería y el repartidor reciben avisos es DD-054 (avisos-E19 y E20, bloqueados), y
// la api solo registra teléfonos de cuentas de usuario.
import { useEffect } from 'react';

import { useSesion } from '../sesion/ProveedorDeSesion';
import { avisosDisponibles } from './firebase';
import { vigilarElTelefono } from './telefono';

export function AvisosDelTelefono(): null {
  const { estado, token, modulo } = useSesion();

  useEffect(() => {
    if (estado !== 'con-sesion' || modulo !== 'usuario' || !token || !avisosDisponibles()) {
      return;
    }
    return vigilarElTelefono(token);
  }, [estado, token, modulo]);

  return null;
}
