// La lista de cuentas de usuario para su pantalla (administracion-app-E24): la pide al servidor con la búsqueda y la
// página, al abrirse, al cambiar cualquiera de las dos y cada vez que la pantalla vuelve a verse, por ejemplo al regresar
// de una ficha donde se bloqueó una cuenta, para que el estado de cada una esté al día (useFocusEffect de expo-router),
// como "Mis pedidos" (src/historial/useHistorial.ts).
//
// La respuesta se guarda con la consulta que la pidió: al volver con la misma búsqueda y la misma página, se queda la
// lista que había mientras llega la nueva; con otra, la pantalla espera la suya y no muestra la de otra búsqueda. Si
// llegara la respuesta de una búsqueda que ya cambió, no se pinta. Con un 401, el cliente de la administración ya llevó
// al inicio de sesión: la pantalla no tiene nada que mostrar.
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { useApiDeLaAdministracion, type FallaDeLaAdministracion, type ResultadoDeLaAdministracion } from '../api';
import { consultarUsuarios, type ListaDeUsuarios } from './api';

/** cargando: se pide al servidor; lista: llegó la página; error: no llegó. */
export type EstadoDeLaLista =
  | { fase: 'cargando' }
  | { fase: 'lista'; lista: ListaDeUsuarios }
  | { fase: 'error'; falla: FallaDeLaAdministracion };

interface Respuesta {
  /** La búsqueda y la página con que se pidió. */
  clave: string;
  resultado: ResultadoDeLaAdministracion<ListaDeUsuarios>;
}

/**
 * La página pedida de la lista, con lo escrito en la búsqueda. Devuelve también "volver a consultar", para intentar de
 * nuevo cuando no llegó.
 */
export function useListaDeUsuarios(
  buscar: string,
  pagina: number,
): { estado: EstadoDeLaLista; volverAConsultar: () => void } {
  const pedir = useApiDeLaAdministracion();
  const [respuesta, setRespuesta] = useState<Respuesta | null>(null);
  // Cambia con "Intentar de nuevo": la misma consulta, otra vez, y la pantalla espera la suya.
  const [intento, setIntento] = useState(0);
  // Lo que viaja al servidor: el texto sin los espacios de las orillas (src/administracion/usuarios/api.ts).
  const texto = buscar.trim();
  const clave = claveDeLaConsulta(intento, pagina, texto);

  useFocusEffect(
    useCallback(() => {
      let vigente = true;
      const estaClave = claveDeLaConsulta(intento, pagina, texto);
      consultarUsuarios(pedir, { buscar: texto, pagina }).then((resultado) => {
        if (vigente) {
          setRespuesta({ clave: estaClave, resultado });
        }
      });
      return () => {
        vigente = false;
      };
    }, [pedir, texto, pagina, intento]),
  );

  const volverAConsultar = useCallback(() => setIntento((anterior) => anterior + 1), []);

  if (respuesta === null || respuesta.clave !== clave) {
    return { estado: { fase: 'cargando' }, volverAConsultar };
  }
  const { resultado } = respuesta;
  if (resultado.ok) {
    return { estado: { fase: 'lista', lista: resultado.datos }, volverAConsultar };
  }
  return {
    estado: resultado.motivo === 'sin-sesion' ? { fase: 'cargando' } : { fase: 'error', falla: resultado },
    volverAConsultar,
  };
}

function claveDeLaConsulta(intento: number, pagina: number, texto: string): string {
  return `${intento}:${pagina}:${texto}`;
}
