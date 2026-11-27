// La ficha de un repartidor para su pantalla (administracion-app-E19 a E22): el repartidor, las plazas del alta y sus
// acciones de estado.
//
// - La ficha se pide al abrir la pantalla y al volver a ella, como la lista (useLectura).
// - Las plazas del alta (GET plazas) solo se piden si el servidor dice que se puede dar de alta (administracion-app-E20).
// - Cada acción pide antes su confirmación, con el diálogo del sistema (src/administracion/confirmar.ts); con "Cancelar"
//   no manda nada. Al confirmarla, la manda y pone en la ficha al repartidor que devolvió el servidor, con su estado
//   nuevo y las transiciones que ese estado permite.
// - Con un 409, otra sesión ya lo cambió: el mensaje del servidor va arriba y la ficha se vuelve a pedir
//   (administracion-app-E12). Con un 422 del alta, su error va junto a la plaza. Lo demás, arriba, con los mensajes de la
//   app. Con un 401, el cliente ya volvió al inicio de sesión.
// - Mientras una acción va, los botones quedan ocupados: no sale otra encima.
//
// La app no decide qué se puede: muestra los botones de "transiciones" y el servidor aplica la regla (ADR-0022,
// decisión 9).
import { useMemo, useRef, useState } from 'react';

import {
  useApiDeLaAdministracion,
  type FallaDeLaAdministracion,
  type PedirALaAdministracion,
  type ResultadoDeLaAdministracion,
} from '../api';
import { confirmar } from '../confirmar';
import {
  consultarPlazas,
  consultarRepartidor,
  CONTRATO_DE_LOS_REPARTIDORES,
  darDeAltaAlRepartidor,
  darDeBajaAlRepartidor,
  levantarLaSuspensionDelRepartidor,
  suspenderAlRepartidor,
  type PlazaDelAlta,
  type RepartidorDeLaAdministracion,
} from './api';
import {
  errorDelCampo,
  mensajeDeLosRepartidores,
  queHaceElAlta,
  TEXTOS_DE_LOS_REPARTIDORES as TEXTOS,
} from './textos';
import { useLectura, type Lectura } from './useLectura';

/** Las acciones de estado de la ficha. */
export type AccionDeEstado = 'alta' | 'suspension' | 'levantamiento' | 'baja';

export interface FichaDelRepartidor {
  ficha: Lectura<RepartidorDeLaAdministracion>;
  plazas: Lectura<PlazaDelAlta[]>;
  /** La acción que se está mandando; null si ninguna. */
  enviando: AccionDeEstado | null;
  /** El mensaje de la última acción que no salió, si no va junto a la plaza. */
  aviso: string | undefined;
  /** El error del servidor para la plaza del alta. */
  errorDeLaPlaza: string | undefined;
  /** Piden la confirmación y, si la persona confirma, mandan la acción. */
  darDeAlta(plaza: string): void;
  suspender(): void;
  levantarLaSuspension(): void;
  darDeBaja(): void;
}

type Respuesta = ResultadoDeLaAdministracion<RepartidorDeLaAdministracion>;

export function useFichaDelRepartidor(idPublico: string | undefined): FichaDelRepartidor {
  const pedir = useApiDeLaAdministracion();
  const ficha = useLectura(
    useMemo(
      () => (idPublico ? (cliente: PedirALaAdministracion) => consultarRepartidor(cliente, idPublico) : null),
      [idPublico],
    ),
  );
  const conAlta = ficha.datos?.transiciones.includes('dar_de_alta') === true;
  const plazas = useLectura(conAlta ? consultarPlazas : null);
  const [enviando, setEnviando] = useState<AccionDeEstado | null>(null);
  const [falla, setFalla] = useState<FallaDeLaAdministracion | null>(null);
  // Para no mandar otra mientras una sigue, aunque los dos toques lleguen antes de que se apaguen los botones.
  const enCurso = useRef(false);

  async function mandar(accion: AccionDeEstado, peticion: () => Promise<Respuesta>) {
    if (enCurso.current) {
      return;
    }
    enCurso.current = true;
    setEnviando(accion);
    setFalla(null);
    const resultado = await peticion();
    enCurso.current = false;
    setEnviando(null);
    if (resultado.ok) {
      ficha.poner(resultado.datos);
      return;
    }
    setFalla(resultado);
    if (resultado.motivo === 'conflicto') {
      ficha.consultarDeNuevo();
    }
  }

  // La confirmación de una acción sobre el repartidor de la ficha.
  function pedirConfirmacion(
    accion: AccionDeEstado,
    dialogo: { titulo: string; texto: string; boton: string },
    peticion: (idDelRepartidor: string) => Promise<Respuesta>,
  ) {
    const repartidor = ficha.datos;
    if (!repartidor || enCurso.current) {
      return;
    }
    confirmar({
      titulo: dialogo.titulo,
      texto: dialogo.texto,
      accion: dialogo.boton,
      alConfirmar: () => void mandar(accion, () => peticion(repartidor.id_publico)),
    });
  }

  const camposVisibles = [CONTRATO_DE_LOS_REPARTIDORES.plaza];

  return {
    ficha,
    plazas,
    enviando,
    aviso: falla ? mensajeDeLosRepartidores(falla, camposVisibles) : undefined,
    errorDeLaPlaza: errorDelCampo(falla, CONTRATO_DE_LOS_REPARTIDORES.plaza),
    darDeAlta: (plaza) =>
      pedirConfirmacion(
        'alta',
        { titulo: TEXTOS.confirmarElAlta, texto: queHaceElAlta(plaza), boton: TEXTOS.darDeAlta },
        (id) => darDeAltaAlRepartidor(pedir, id, plaza),
      ),
    suspender: () =>
      pedirConfirmacion(
        'suspension',
        { titulo: TEXTOS.confirmarLaSuspension, texto: TEXTOS.queHaceLaSuspension, boton: TEXTOS.suspender },
        (id) => suspenderAlRepartidor(pedir, id),
      ),
    levantarLaSuspension: () =>
      pedirConfirmacion(
        'levantamiento',
        {
          titulo: TEXTOS.confirmarElLevantamiento,
          texto: TEXTOS.queHaceElLevantamiento,
          boton: TEXTOS.levantarLaSuspension,
        },
        (id) => levantarLaSuspensionDelRepartidor(pedir, id),
      ),
    darDeBaja: () =>
      pedirConfirmacion(
        'baja',
        { titulo: TEXTOS.confirmarLaBaja, texto: TEXTOS.queHaceLaBaja, boton: TEXTOS.darDeBaja },
        (id) => darDeBajaAlRepartidor(pedir, id),
      ),
  };
}
