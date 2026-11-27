// "Elige tu lavandería": la lista de lavanderías cercanas que abre "Pedido nuevo" en la pantalla principal, primer paso
// del pedido (cercanas-E15). Cada lavandería va en su tarjeta, que abre su perfil (cercanas-E12); sin ninguna, lo dice
// (cercanas-E14). Consulta con el punto que le pasa la pantalla principal; si se abre sin él, lo busca igual que ella
// (cercanas-E03 y E04).
//
// Arriba, los filtros de distancia y calificación, con los cortes de la configuración (cercanas-E21): la lista muestra lo
// que el servidor devuelve con ellos, y si no queda ninguna lo dice y ofrece "Quitar filtros". Los filtros se ven cuando
// ya hay un punto con qué consultar.
//
// El título, el encabezado con "Regresar" y los filtros son los del tablero "Búsqueda" de la Propuesta 5 corregida
// (C-233) y la imagen a04-busqueda de la Propuesta 04. De ese tablero no entran el filtro de disponibilidad
// (cercanas-E19, DD-083 y DD-078), la búsqueda por nombre ni el orden, que quedaron fuera del contrato (DD-136).
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';

import { AvisoDeLaBusqueda } from '../../src/cercanas/AvisoDeLaBusqueda';
import { FiltrosDeLaBusqueda } from '../../src/cercanas/FiltrosDeLaBusqueda';
import { puntoDeLosParametros, rutaDelPerfil, type ParametrosDeLaLista } from '../../src/cercanas/rutas';
import { detalleDeLavanderia, mensajeDeLaFalla, TEXTOS } from '../../src/cercanas/textos';
import { useLavanderiasCercanas } from '../../src/cercanas/useLavanderiasCercanas';
import { useRegresar } from '../../src/navegacion/regresar';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { TarjetaDeLavanderia } from '../../src/piezas/TarjetaDeLavanderia';

export default function ElegirLavanderia() {
  const router = useRouter();
  const regresar = useRegresar('/inicio');
  const parametros = useLocalSearchParams<Record<keyof ParametrosDeLaLista, string>>();
  const [distanciaMaximaMetros, setDistanciaMaximaMetros] = useState<number | null>(null);
  const [calificacionMinima, setCalificacionMinima] = useState<number | null>(null);
  const { estado, permitir } = useLavanderiasCercanas(puntoDeLosParametros(parametros), {
    distanciaMaximaMetros,
    calificacionMinima,
  });

  const conFiltros = distanciaMaximaMetros !== null || calificacionMinima !== null;
  const vacia = estado.fase === 'lista' && estado.lavanderias.length === 0;

  function quitarFiltros() {
    setDistanciaMaximaMetros(null);
    setCalificacionMinima(null);
  }

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.tituloDeLaLista, alRegresar: regresar }}>
      {'punto' in estado ? (
        <FiltrosDeLaBusqueda
          distanciaMaximaMetros={distanciaMaximaMetros}
          calificacionMinima={calificacionMinima}
          alCambiarDistancia={setDistanciaMaximaMetros}
          alCambiarCalificacion={setCalificacionMinima}
        />
      ) : null}
      {vacia && !conFiltros ? <Parrafo alineacion="inicio">{TEXTOS.sinLavanderias}</Parrafo> : null}
      {vacia && conFiltros ? (
        <>
          <Parrafo alineacion="inicio">{TEXTOS.sinCoincidencias}</Parrafo>
          <Boton variante="secundario" onPress={quitarFiltros}>
            {TEXTOS.quitarFiltros}
          </Boton>
        </>
      ) : null}
      {estado.fase === 'lista'
        ? estado.lavanderias.map((lavanderia) => (
            <TarjetaDeLavanderia
              key={lavanderia.id_publico}
              testID={`tarjeta-${lavanderia.id_publico}`}
              nombre={lavanderia.nombre}
              detalle={detalleDeLavanderia(lavanderia)}
              alTocar={() => router.push(rutaDelPerfil(lavanderia.id_publico))}
            />
          ))
        : null}
      {estado.fase === 'error' ? <MensajeDeError texto={mensajeDeLaFalla(estado.falla)} /> : null}
      <AvisoDeLaBusqueda estado={estado} permitir={permitir} />
    </Pantalla>
  );
}
