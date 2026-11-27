// La calificación del servicio de un pedido (openspec/changes/historial-y-calificacion, historial-E06, E07 y E08;
// NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14: "Escala de 1 a 5 estrellas con comentario opcional"). La
// abre "Calificar" en la tarjeta de un pedido Entregado sin calificar de "Mis pedidos", por su id_publico, con su folio
// y su lavandería para decir qué pedido se califica.
//
// - Una sola calificación, del servicio: a quién se califica sigue en DD-052 (historial-E11, bloqueado). El servidor la
//   guarda como calificación de la lavandería del pedido (historial-E12).
// - Las estrellas van de 1 a 5 y sin ellas el botón no se puede tocar y dice qué falta (historial-E08). El comentario es
//   opcional y se manda tal como se escribió; vacío, sin comentario (historial-E06 y E07).
// - Con la calificación guardada, la app vuelve a "Mis pedidos", que la muestra en el pedido (historial-E06). Lo que el
//   servidor no acepta de cada campo va junto a él; el 409 (todavía no se califica, o ya está calificado: la lectura
//   provisional de BR-25), su mensaje; sin conexión, los mensajes generales de la app.
//
// La composición es la del mock A14 en su variante literal del contrato, con "Regresar" en el encabezado como en las
// demás pantallas.
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { RUTA_DEL_HISTORIAL } from '../../../src/historial/rutas';
import { SelectorDeEstrellas } from '../../../src/historial/SelectorDeEstrellas';
import { pedidoQueSeCalifica, TEXTOS_DE_LA_CALIFICACION as TEXTOS } from '../../../src/historial/textos';
import { useCalificacion } from '../../../src/historial/useCalificacion';
import { useRegresar } from '../../../src/navegacion/regresar';
import { Boton } from '../../../src/piezas/Boton';
import { CampoDeTexto } from '../../../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';

export default function Calificacion() {
  const { id, folio, lavanderia } = useLocalSearchParams<{ id: string; folio?: string; lavanderia?: string }>();
  const regresar = useRegresar(RUTA_DEL_HISTORIAL);
  const { enviando, calificar, errorDeLasEstrellas, errorDelComentario, errorGeneral } = useCalificacion(id);
  const [estrellas, setEstrellas] = useState<number | null>(null);
  const [comentario, setComentario] = useState('');

  async function enviar() {
    if (await calificar(estrellas, comentario)) {
      regresar();
    }
  }

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS.encabezado, alRegresar: regresar }}
      acciones={
        <>
          <MensajeDeError texto={errorGeneral} />
          <Boton ocupado={enviando} deshabilitado={estrellas === null} onPress={() => void enviar()}>
            {estrellas === null ? TEXTOS.eligeUnaEstrella : TEXTOS.enviar}
          </Boton>
        </>
      }
    >
      {folio && lavanderia ? <Parrafo alineacion="inicio">{pedidoQueSeCalifica(folio, lavanderia)}</Parrafo> : null}
      <SelectorDeEstrellas
        nombre="estrellas"
        etiqueta={TEXTOS.pregunta}
        elegidas={estrellas}
        alElegir={setEstrellas}
        error={errorDeLasEstrellas}
      />
      <CampoDeTexto
        nombre="comentario"
        etiqueta={TEXTOS.comentario}
        valor={comentario}
        alCambiar={setComentario}
        error={errorDelComentario}
        multilinea
      />
    </Pantalla>
  );
}
