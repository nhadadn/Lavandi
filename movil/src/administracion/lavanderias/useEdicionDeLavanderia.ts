// La edición de una lavandería por la administración (administracion-app-E14), con las piezas del perfil y horarios del
// socio (src/alta-lavanderias/formulario.ts): el formulario de lo que se leyó, lo que cambió desde entonces y cada error
// del servidor en su campo. Al abrir pide la ficha (GET /api/administracion/lavanderias/{id_publico}), que trae los mismos
// datos que ve y edita su socio.
//
// "Guardar cambios" manda solo lo que cambió, como el socio: la dirección completa si cambió alguna de sus partes, y los
// servicios, las ventanas y las fotos completos si cambió alguno; con una foto nueva, en multipart por POST con
// _method=PATCH. Con la lista de fotos va la marca de las fotos que se leyeron al abrir la pantalla, la de la ficha, y
// el servidor la rechaza si su socio las cambió mientras tanto, como el panel (administracion-app-E14; design.md del
// cambio, F-D01). La app no valida nada por su cuenta: cada error del servidor va junto a su campo y nada cambia, y la
// pantalla se queda. Si se guardó, guardar() responde true y la pantalla regresa a la ficha, que vuelve a pedir la
// lavandería al verse y muestra los datos nuevos: en las imágenes 12, 60 y 61 de la Propuesta 06, "Guardar cambios" lleva
// a la ficha (07); en la 13, con un error, la pantalla se queda. Mientras guarda, el botón se bloquea: un doble toque no
// manda dos veces. Un 401 lo atiende el cliente del módulo, que ya llevó al inicio de sesión.
import { useCallback, useEffect, useRef, useState, type SetStateAction } from 'react';

import { MENSAJES } from '../../acceso/mensajes';
import {
  cambiosEntre,
  erroresDelEnvio,
  formularioDe,
  type ErroresDelFormulario,
  type Formulario,
} from '../../alta-lavanderias/formulario';
import { useApiDeLaAdministracion, type FallaDeLaAdministracion } from '../api';
import { consultarLavanderia, editarLavanderia, type LavanderiaDeLaAdministracion } from './api';

const SIN_ERRORES: ErroresDelFormulario = { campos: {} };

/**
 * El formulario como se abrió y como está ahora, con el máximo de fotos que dio el servidor y la marca de las fotos que
 * se leyeron al abrir.
 */
export interface Edicion {
  original: Formulario;
  actual: Formulario;
  maximoDeFotos: number;
  fotosLeidas: string;
}

function edicionDe(lavanderia: LavanderiaDeLaAdministracion): Edicion {
  const formulario = formularioDe(lavanderia);
  return {
    original: formulario,
    actual: formulario,
    maximoDeFotos: lavanderia.maximo_de_fotos,
    fotosLeidas: lavanderia.fotos_leidas,
  };
}

export function useEdicionDeLavanderia(idPublico: string) {
  const pedir = useApiDeLaAdministracion();
  const [edicion, setEdicion] = useState<Edicion | null>(null);
  const [fallaDeLaLectura, setFallaDeLaLectura] = useState<FallaDeLaAdministracion | null>(null);
  const [errores, setErrores] = useState<ErroresDelFormulario>(SIN_ERRORES);
  const [guardando, setGuardando] = useState(false);
  // Cada intento nuevo vuelve a pedir la lavandería.
  const [intento, setIntento] = useState(0);
  // Ataja el segundo toque antes de que el botón se bloquee.
  const enCamino = useRef(false);
  // Después de un 401 la pantalla ya no está: lo que llega tarde no se pinta.
  const montada = useRef(true);
  useEffect(() => {
    montada.current = true;
    return () => {
      montada.current = false;
    };
  }, []);

  // La lavandería como está guardada, al abrir la pantalla.
  useEffect(() => {
    let vigente = true;
    consultarLavanderia(pedir, idPublico).then((resultado) => {
      if (!vigente || (!resultado.ok && resultado.motivo === 'sin-sesion')) {
        return;
      }
      if (resultado.ok) {
        setEdicion(edicionDe(resultado.datos));
      } else {
        setFallaDeLaLectura(resultado);
      }
    });
    return () => {
      vigente = false;
    };
  }, [pedir, idPublico, intento]);

  const reintentar = useCallback(() => {
    setFallaDeLaLectura(null);
    setIntento((anterior) => anterior + 1);
  }, []);

  // Lo que la persona cambia.
  const cambiar = useCallback((cambio: SetStateAction<Formulario>) => {
    setEdicion((anterior) =>
      anterior ? { ...anterior, actual: typeof cambio === 'function' ? cambio(anterior.actual) : cambio } : anterior,
    );
  }, []);

  /** Manda lo que cambió; true si el servidor lo guardó y la pantalla ya puede regresar a la ficha. */
  const guardar = useCallback(async (): Promise<boolean> => {
    if (!edicion || enCamino.current) {
      return false;
    }
    enCamino.current = true;
    setGuardando(true);
    setErrores(SIN_ERRORES);
    const cambios = cambiosEntre(edicion.original, edicion.actual);
    const resultado = await editarLavanderia(pedir, idPublico, cambios, edicion.fotosLeidas);
    enCamino.current = false;
    // Si la persona ya regresó, o la sesión venció, no hay pantalla de la cual regresar.
    if (!montada.current || (!resultado.ok && resultado.motivo === 'sin-sesion')) {
      return false;
    }
    if (resultado.ok) {
      // El botón sigue ocupado mientras la pantalla regresa: no se manda otra vez.
      return true;
    }
    setGuardando(false);
    // La lavandería que ya no existe no es de un campo: su mensaje va arriba del botón, como el de una falla general.
    setErrores(
      resultado.motivo === 'no-existe'
        ? { campos: {}, general: resultado.mensaje || MENSAJES.servidor }
        : erroresDelEnvio(resultado, cambios),
    );
    return false;
  }, [edicion, pedir, idPublico]);

  return { edicion, fallaDeLaLectura, errores, guardando, cambiar, guardar, reintentar };
}
