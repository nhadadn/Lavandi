// "Guardar .csv" y "Guardar .xlsx" de una descarga lista (administracion-app-E58), para la pieza que pide la descarga y
// para la pantalla Descargas: guarda con src/administracion/descargas/guardar.ts y lleva lo que la pantalla dice.
//
// - Guardado: dice en qué carpeta quedó el último archivo.
// - La persona cerró el selector: no se guardó nada y no sale ningún error.
// - El servidor no entregó el archivo: se vuelve a consultar la descarga con el cliente del módulo, que con la sesión
//   vencida (401) la cierra y abre el inicio de sesión, como en cualquier pantalla del módulo (administracion-app-E05).
//   Si la descarga sigue, se pone al día con lo que dice el servidor; si ya no existe, lo dice.
// - Se guarda un archivo a la vez: mientras uno se baja o espera su carpeta, ningún botón para guardar de la pantalla
//   se puede tocar (ocupado). Así dos guardados no comparten la copia de la caché ni se pisan lo que dicen.
// - Al terminar, salga como salga, también si la persona cerró el selector, el botón deja de estar ocupado, en la
//   pantalla y para el lector de pantalla (accessibilityState.busy): nada queda esperando un guardado que ya acabó.
import { useCallback, useState } from 'react';

import { MENSAJES } from '../../acceso/mensajes';
import { useSesion } from '../../sesion/ProveedorDeSesion';
import { useApiDeLaAdministracion } from '../api';
import { consultarLaDescarga, type Descarga, type Formato } from './api';
import { guardarElArchivo, type ResultadoDelGuardado } from './guardar';
import { mensajeDeLaFalla } from './textos';

/** El último archivo que se guardó: de qué descarga, en qué formato y en qué carpeta. */
export interface ArchivoGuardado {
  descarga: Descarga;
  formato: Formato;
  carpeta: string;
}

export interface GuardadoDeArchivos {
  guardar(descarga: Descarga, formato: Formato): Promise<void>;
  /** El formato que se está guardando de esa descarga, o null. */
  guardandoDe(descarga: Descarga): Formato | null;
  /** Si se está guardando un archivo, de cualquier descarga. */
  ocupado: boolean;
  /** El último archivo que se guardó, o null. */
  guardado: ArchivoGuardado | null;
  /** Lo que no salió al guardar un archivo de esa descarga, o undefined. */
  fallaDe(descarga: Descarga): string | undefined;
}

/** alCambiar recibe la descarga como la dio el servidor cuando se vuelve a consultar; debe ser estable (useCallback). */
export function useGuardarElArchivo(alCambiar: (descarga: Descarga) => void): GuardadoDeArchivos {
  const { token } = useSesion();
  const pedir = useApiDeLaAdministracion();
  const [guardando, setGuardando] = useState<{ idPublico: string; formato: Formato } | null>(null);
  const [guardado, setGuardado] = useState<ArchivoGuardado | null>(null);
  const [falla, setFalla] = useState<{ idPublico: string; mensaje: string } | null>(null);

  const guardar = useCallback(
    async (descarga: Descarga, formato: Formato) => {
      // Las pantallas del módulo solo se ven con sesión (app/_layout.tsx).
      if (!token) {
        return;
      }
      const idPublico = descarga.id_publico;
      setGuardando({ idPublico, formato });
      setFalla(null);
      let resultado: ResultadoDelGuardado;
      try {
        resultado = await guardarElArchivo(descarga, formato, token);
      } catch {
        // guardarElArchivo dice cómo salió y no lanza; si algo lanzara, no se guardó nada y se dice como tal.
        resultado = { guardado: false, motivo: 'no-se-guardo' };
      } finally {
        setGuardando(null);
      }
      if (resultado.guardado) {
        setGuardado({ descarga, formato, carpeta: resultado.carpeta });
        return;
      }
      if (resultado.motivo === 'cerro-el-selector') {
        return;
      }
      if (resultado.motivo === 'no-se-guardo') {
        setFalla({ idPublico, mensaje: MENSAJES.servidor });
        return;
      }
      const consulta = await consultarLaDescarga(pedir, idPublico);
      if (consulta.ok) {
        alCambiar(consulta.datos.descarga);
        setFalla({ idPublico, mensaje: MENSAJES.servidor });
      } else if (consulta.motivo !== 'sin-sesion') {
        setFalla({ idPublico, mensaje: mensajeDeLaFalla(consulta) });
      }
    },
    [token, pedir, alCambiar],
  );

  const guardandoDe = useCallback(
    (descarga: Descarga) => (guardando?.idPublico === descarga.id_publico ? guardando.formato : null),
    [guardando],
  );

  const fallaDe = useCallback(
    (descarga: Descarga) => (falla?.idPublico === descarga.id_publico ? falla.mensaje : undefined),
    [falla],
  );

  return { guardar, guardandoDe, ocupado: guardando !== null, guardado, fallaDe };
}
