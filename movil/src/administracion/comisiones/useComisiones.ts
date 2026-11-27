// Las comisiones para su pantalla (F-D05; administracion-app-E32 a E34): las pide al abrirla, y fija el global, fija el
// propio de una lavandería o lo quita. Cada acción responde las comisiones ya con el cambio, y la pantalla pone esas
// (administracion-app-E33): los porcentajes nuevos y el que aplica a cada lavandería, sin otra consulta. Sin consulta
// periódica: el Módulo D #5 no la pide (ADR-0007).
//
// El porcentaje se escribe en un formulario dentro de la tarjeta del global o de la lavandería, uno a la vez (imágenes 29
// y 30 de la Propuesta 06): se abre vacío, sin ningún número propuesto, porque los porcentajes los captura EL CLIENTE. Se
// manda lo que se escribió, solo sin los espacios de los lados, como los quita el servidor: la regla (de 0 a 100, con a
// lo más dos decimales) la aplica él. Con un 422, el error de "porcentaje" va junto al campo, el formulario sigue abierto
// con lo escrito y las comisiones siguen como estaban, porque el vigente no cambió (administracion-app-E34). Quitar el
// propio pide confirmarlo antes (imagen 68).
//
// La respuesta de una consulta que ya no es la última (porque la pantalla puso lo que devolvió una acción, o porque dejó
// de verse) no se pinta. Con dos acciones en camino, las respuestas pueden llegar en otro orden: la pantalla se queda con
// la de la acción que se mandó al último, y la de una anterior que llegue después ya no se pinta. Lo que responde un
// formulario que ya se cerró solo pone las comisiones, si llegaron.
import { useCallback, useEffect, useRef, useState } from 'react';

import { useApiDeLaAdministracion, type FallaDeLaAdministracion, type ResultadoDeLaAdministracion } from '../api';
import { confirmar } from '../confirmar';
import {
  consultarComisiones,
  CONTRATO_DE_LAS_COMISIONES,
  fijarElGlobal,
  fijarElPropio,
  quitarElPropio,
  type ComisionDeLaLavanderia,
  type Comisiones,
} from './api';
import { errorDelCampo, mensajeDeLasComisiones, TEXTOS_DE_LAS_COMISIONES as TEXTOS } from './textos';

/** Dónde se escribe el porcentaje: el global, o el propio de una lavandería. */
export type Destino = { tipo: 'global' } | { tipo: 'propio'; idPublico: string };

/** El formulario del porcentaje que está abierto. */
export interface FormularioDelPorcentaje {
  destino: Destino;
  /** Lo que se lleva escrito; empieza vacío. */
  texto: string;
  /** Mientras el servidor responde: "Guardar" muestra que trabaja y no se vuelve a tocar. */
  guardando: boolean;
  /** El error del servidor para el porcentaje, junto al campo (administracion-app-E34). */
  error?: string;
  /** Por qué no se guardó, si no es del campo: sin conexión, el 404 de la lavandería u otro error. */
  mensaje?: string;
}

export interface ComisionesDeLaPantalla {
  /** Lo último que llegó; null mientras no llega nada. */
  comisiones: Comisiones | null;
  /** Por qué no llegó la consulta; null si llegó o si todavía no responde. */
  falla: FallaDeLaAdministracion | null;
  /** "Intentar de nuevo". */
  consultarDeNuevo(): void;
  /** El formulario abierto; null si no hay ninguno. */
  formulario: FormularioDelPorcentaje | null;
  /** Abre el formulario del global o del propio de una lavandería, vacío; cierra el que hubiera. */
  abrir(destino: Destino): void;
  escribir(texto: string): void;
  guardar(): void;
  /** "Cancelar": cierra el formulario sin mandar nada. */
  cerrar(): void;
  /** Pide confirmar y, si se confirma, quita el propio de la lavandería. */
  quitar(lavanderia: ComisionDeLaLavanderia): void;
  /** La lavandería a la que se le está quitando el propio. */
  quitando: string | null;
  /** Por qué no se quitó el propio de una lavandería. */
  fallaAlQuitar: { idPublico: string; mensaje?: string } | null;
}

/** Si el formulario abierto es el de ese destino. */
export function esElDestino(formulario: FormularioDelPorcentaje | null, destino: Destino): boolean {
  if (!formulario) {
    return false;
  }
  const abierto = formulario.destino;
  return abierto.tipo === 'global'
    ? destino.tipo === 'global'
    : destino.tipo === 'propio' && destino.idPublico === abierto.idPublico;
}

const CAMPOS_VISIBLES = [CONTRATO_DE_LAS_COMISIONES.porcentaje] as const;

export function useComisiones(): ComisionesDeLaPantalla {
  const pedir = useApiDeLaAdministracion();
  const [comisiones, setComisiones] = useState<Comisiones | null>(null);
  const [falla, setFalla] = useState<FallaDeLaAdministracion | null>(null);
  const [formulario, setFormulario] = useState<FormularioDelPorcentaje | null>(null);
  const [quitando, setQuitando] = useState<string | null>(null);
  const [fallaAlQuitar, setFallaAlQuitar] = useState<ComisionesDeLaPantalla['fallaAlQuitar']>(null);
  // La consulta vigente: cada consulta, cada respuesta de una acción y la salida de la pantalla toman un número nuevo.
  const vigente = useRef(0);
  // El formulario vigente: cada vez que se abre o se cierra uno toma un número nuevo.
  const abierto = useRef(0);
  // Mientras se quita un propio, otro toque no abre otra confirmación.
  const quitandoAhora = useRef(false);
  // Cada acción toma un número al mandarse; la pantalla recuerda el de la última cuya respuesta puso.
  const mandada = useRef(0);
  const puesta = useRef(0);

  // Consulta ya. Devuelve lo que hace al dejar de verse la pantalla: lo que llegue tarde ya no se pinta.
  const consultar = useCallback(() => {
    const esta = ++vigente.current;
    void consultarComisiones(pedir).then((resultado) => {
      if (esta !== vigente.current) {
        return;
      }
      if (resultado.ok) {
        setComisiones(resultado.datos);
        setFalla(null);
      } else {
        setFalla(resultado);
      }
    });
    return () => {
      vigente.current++;
    };
  }, [pedir]);

  useEffect(consultar, [consultar]);

  const consultarDeNuevo = useCallback(() => {
    setFalla(null);
    consultar();
  }, [consultar]);

  // Las comisiones que devolvió la acción con ese número. Si en pantalla ya están las de una acción mandada después, estas
  // son viejas y no se ponen; si no, son las más nuevas, así que una consulta que siga en camino ya no se pinta.
  const poner = useCallback((nuevas: Comisiones, accion: number) => {
    if (accion < puesta.current) {
      return;
    }
    puesta.current = accion;
    vigente.current++;
    setComisiones(nuevas);
    setFalla(null);
  }, []);

  const abrir = useCallback((destino: Destino) => {
    abierto.current++;
    setFormulario({ destino, texto: '', guardando: false });
    setFallaAlQuitar(null);
  }, []);

  const cerrar = useCallback(() => {
    abierto.current++;
    setFormulario(null);
  }, []);

  const escribir = useCallback((texto: string) => {
    setFormulario((actual) => (actual ? { ...actual, texto } : actual));
  }, []);

  const guardar = useCallback(() => {
    if (!formulario || formulario.guardando) {
      return;
    }
    const este = abierto.current;
    const accion = ++mandada.current;
    const { destino, texto } = formulario;
    setFormulario({ ...formulario, guardando: true, error: undefined, mensaje: undefined });
    const porcentaje = texto.trim();
    const peticion =
      destino.tipo === 'global'
        ? fijarElGlobal(pedir, porcentaje)
        : fijarElPropio(pedir, destino.idPublico, porcentaje);
    void peticion.then((resultado: ResultadoDeLaAdministracion<Comisiones>) => {
      if (resultado.ok) {
        poner(resultado.datos, accion);
      }
      if (este !== abierto.current) {
        return;
      }
      if (resultado.ok) {
        abierto.current++;
        setFormulario(null);
        return;
      }
      setFormulario((actual) =>
        actual
          ? {
              ...actual,
              guardando: false,
              error: errorDelCampo(resultado, CONTRATO_DE_LAS_COMISIONES.porcentaje),
              mensaje: mensajeDeLasComisiones(resultado, CAMPOS_VISIBLES),
            }
          : actual,
      );
    });
  }, [formulario, pedir, poner]);

  const quitar = useCallback(
    (lavanderia: ComisionDeLaLavanderia) => {
      if (quitandoAhora.current) {
        return;
      }
      confirmar({
        titulo: TEXTOS.confirmarQuitar,
        texto: TEXTOS.queHaceQuitar,
        accion: TEXTOS.quitar,
        alConfirmar: () => {
          const { id_publico: idPublico } = lavanderia;
          const accion = ++mandada.current;
          quitandoAhora.current = true;
          setQuitando(idPublico);
          setFallaAlQuitar(null);
          void quitarElPropio(pedir, idPublico).then((resultado) => {
            quitandoAhora.current = false;
            setQuitando(null);
            if (resultado.ok) {
              poner(resultado.datos, accion);
            } else {
              setFallaAlQuitar({ idPublico, mensaje: mensajeDeLasComisiones(resultado) });
            }
          });
        },
      });
    },
    [pedir, poner],
  );

  return {
    comisiones,
    falla,
    consultarDeNuevo,
    formulario,
    abrir,
    escribir,
    guardar,
    cerrar,
    quitar,
    quitando,
    fallaAlQuitar,
  };
}
