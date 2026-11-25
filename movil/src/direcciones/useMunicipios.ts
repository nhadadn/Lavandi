// Los municipios del territorio para la dirección nueva (direcciones-E17): la lista de GET /api/catalogos/municipios, que
// se pide una vez por sesión de la app (design.md de openspec/changes/direcciones, "Qué cambia en el formulario de la
// app"). Es la misma para todas las cuentas: es el territorio del contrato (NSV-2026-001-v8.pdf:4:Cláusula I). Vive en
// la memoria de la app, como el pedido en curso; al cerrarla se pide otra vez.
//
// Solo se guarda una lista que llegó con municipios. Una vacía (sin catálogo cargado en el servidor) o una que no llegó
// no se guarda: la pantalla lo dice, deja intentarlo de nuevo, y la siguiente dirección nueva la vuelve a pedir.
import { useEffect, useState } from 'react';

import type { Resultado } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarMunicipios, type Municipio } from './api';

/** cargando: se pide al servidor; listos: llegó con municipios; no-disponibles: vacía o no llegó. */
export type EstadoDeLosMunicipios =
  | { fase: 'cargando' }
  | { fase: 'listos'; municipios: Municipio[] }
  | { fase: 'no-disponibles' };

let guardados: Municipio[] | null = null;
// Si dos pantallas la piden a la vez, esperan la misma petición.
let enCamino: Promise<Resultado<Municipio[]>> | null = null;

function obtenerMunicipios(token: string): Promise<Resultado<Municipio[]>> {
  if (guardados) {
    return Promise.resolve({ ok: true, datos: guardados });
  }
  if (!enCamino) {
    enCamino = consultarMunicipios(token).then((resultado) => {
      enCamino = null;
      if (resultado.ok && resultado.datos.length > 0) {
        guardados = resultado.datos;
      }
      return resultado;
    });
  }
  return enCamino;
}

/** Olvida la lista de la sesión. Para las pruebas, como vaciarPedidoEnCurso. */
export function olvidarMunicipios(): void {
  guardados = null;
  enCamino = null;
}

/** La lista de municipios y cómo pedirla otra vez si no llegó. */
export function useMunicipios(): { estado: EstadoDeLosMunicipios; reintentar: () => void } {
  const { token } = useSesion();
  const [estado, setEstado] = useState<EstadoDeLosMunicipios>(() =>
    guardados ? { fase: 'listos', municipios: guardados } : { fase: 'cargando' },
  );
  // Cada intento nuevo vuelve a correr la petición.
  const [intento, setIntento] = useState(0);

  useEffect(() => {
    // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token) {
      return;
    }
    let vigente = true;
    obtenerMunicipios(token).then((resultado) => {
      if (vigente) {
        setEstado(
          resultado.ok && resultado.datos.length > 0
            ? { fase: 'listos', municipios: resultado.datos }
            : { fase: 'no-disponibles' },
        );
      }
    });
    return () => {
      vigente = false;
    };
  }, [token, intento]);

  function reintentar() {
    setEstado({ fase: 'cargando' });
    setIntento((anterior) => anterior + 1);
  }

  return { estado, reintentar };
}
