// La agenda de una lavandería para el selector de fecha y hora (agenda-E04 y E08): pide al servidor, con la sesión,
// las ventanas del día que se ve, y otra vez cada vez que se toca un día, aunque sea el mismo: así se reintenta si no
// llegó. Los días que se pueden elegir son los de la respuesta, de primer_dia a ultimo_dia.
//
// El primer día que se pide es el que se le da (el de la ventana que ya estaba en el pedido en curso) o, sin él, hoy
// en el teléfono. Si ese día no es de la agenda de la plaza (el teléfono va en otra fecha que la plaza, o la ventana
// guardada ya pasó), se pide primer_dia, que es hoy en la plaza.
import { useCallback, useEffect, useMemo, useState } from 'react';

import type { Falla, Resultado } from '../configuracion/api';
import { useSesion } from '../sesion/ProveedorDeSesion';
import { consultarVentanas, type DiaDeAgenda } from './api';
import { diasEntre, fechaDelTelefono } from './fechas';

/** cargando: se piden las ventanas del día; listo: llegaron; error: no llegaron. */
export type EstadoDelDia = { fase: 'cargando' } | { fase: 'listo'; dia: DiaDeAgenda } | { fase: 'error'; falla: Falla };

export interface Agenda {
  /** El día que se ve, AAAA-MM-DD en la hora de la plaza. */
  fecha: string;
  /** Los días que se pueden elegir, de primer_dia a ultimo_dia; vacía hasta la primera respuesta del servidor. */
  dias: string[];
  estado: EstadoDelDia;
  /** Muestra ese día y pide sus ventanas al servidor. */
  elegirDia: (fecha: string) => void;
}

// Cada toque de un día es una petición nueva, aunque sea el mismo día.
interface PeticionDelDia {
  fecha: string;
}

interface Respuesta {
  peticion: PeticionDelDia;
  resultado: Resultado<DiaDeAgenda>;
}

export function useAgenda(idPublico: string | undefined, fechaInicial: string | null): Agenda {
  const { token } = useSesion();
  const [peticion, setPeticion] = useState<PeticionDelDia>(() => ({ fecha: fechaInicial ?? fechaDelTelefono() }));
  const [respuesta, setRespuesta] = useState<Respuesta | null>(null);
  const [rango, setRango] = useState<{ primerDia: string; ultimoDia: string } | null>(null);

  useEffect(() => {
    // La pantalla solo se ve con sesión (app/_layout.tsx); mientras se lee, se espera.
    if (!token || !idPublico) {
      return;
    }
    let vigente = true;
    consultarVentanas(token, idPublico, peticion.fecha).then((resultado) => {
      if (!vigente) {
        return;
      }
      if (resultado.ok) {
        const { primer_dia: primerDia, ultimo_dia: ultimoDia } = resultado.datos;
        setRango((anterior) =>
          anterior?.primerDia === primerDia && anterior.ultimoDia === ultimoDia ? anterior : { primerDia, ultimoDia },
        );
        if (peticion.fecha < primerDia || peticion.fecha > ultimoDia) {
          setPeticion({ fecha: primerDia });
          return;
        }
      }
      setRespuesta({ peticion, resultado });
    });
    return () => {
      vigente = false;
    };
  }, [token, idPublico, peticion]);

  const elegirDia = useCallback((fecha: string) => setPeticion({ fecha }), []);
  const dias = useMemo(() => (rango ? diasEntre(rango.primerDia, rango.ultimoDia) : []), [rango]);

  // Mientras no llega la respuesta de la petición en curso, el día está cargando.
  let estado: EstadoDelDia = { fase: 'cargando' };
  if (respuesta && respuesta.peticion === peticion) {
    estado = respuesta.resultado.ok
      ? { fase: 'listo', dia: respuesta.resultado.datos }
      : { fase: 'error', falla: respuesta.resultado };
  }

  return { fecha: peticion.fecha, dias, estado, elegirDia };
}
