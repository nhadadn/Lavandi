// La fecha y la hora de la recolección, segundo paso del pedido nuevo (openspec/changes/agenda-de-recoleccion,
// agenda-E04 y E08; NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7). La abre "Continuar" en la selección de
// servicio, con la lavandería y el tipo en el pedido en curso (perfil-lavanderia-E12). Pide al servidor, con la sesión,
// las ventanas de una hora de esa lavandería para el día que se ve, y las muestra con el selector de fecha y hora,
// componente 2 del design system: los días de primer_dia a ultimo_dia, las ventanas del día elegido y, si ese día la
// lavandería no da servicio, un mensaje en lugar de la rejilla que deja pasar al día siguiente (agenda-E04).
//
// Al tocar una ventana queda elegida, con su palomita, y abajo se lee la elección; "Continuar" la guarda en el pedido
// en curso, tal como la dio el servidor, y pasa a la dirección (agenda-E08). Sin ventana elegida, el botón dice "Elige
// una ventana" y no se puede tocar, como en el mock A07. La ventana elegida se conserva al ver otros días.
//
// La composición es la del tablero "Fecha y hora (2 de 4)" de la Propuesta 5 corregida (C-233) y la imagen
// a07-fecha-hora de la Propuesta 04. Del mock no entran la ventana de entrega (DD-099; no-objetivo del cambio), las
// ventanas sin cupo (agenda-E05, DD-099) ni una primera ventana propuesta (agenda-E10, DD-078).
//
// Si el servidor ya no acepta la ventana al aceptar el resumen (resumen-y-confirmacion, confirmacion-E08), la app
// regresa aquí: el paso se abre de nuevo, sin la ventana rechazada elegida, con el motivo del servidor y con las ventanas
// del día pedidas otra vez. Lo demás del pedido en curso queda como estaba.
//
// Si las ventanas no llegan, sin escenario propio: los mensajes generales de la app, como la búsqueda de cercanas y
// el perfil, y no deja continuar. Sin pedido en curso, por ejemplo con un enlace directo, lleva a la pantalla
// principal; sin tipo de servicio, a la selección de servicio.
import { Redirect, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import type { Ventana } from '../../src/agenda/api';
import { eleccionEnTexto, TEXTOS_DE_LA_AGENDA } from '../../src/agenda/textos';
import { useAgenda } from '../../src/agenda/useAgenda';
import { mensajeDeLaFalla } from '../../src/cercanas/textos';
import { useRegresar } from '../../src/navegacion/regresar';
import { elegirVentana, usePedidoEnCurso } from '../../src/pedido/pedidoEnCurso';
import { RUTA_DE_LA_DIRECCION, RUTA_DEL_SERVICIO } from '../../src/pedido/rutas';
import { pasoDelPedido, TEXTOS_DEL_PEDIDO } from '../../src/pedido/textos';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Presentacion } from '../../src/piezas/Presentacion';
import { SelectorDeFechaYHora } from '../../src/piezas/SelectorDeFechaYHora';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

export default function FechaYHora() {
  const pedido = usePedidoEnCurso();
  // Cada rechazo de la ventana al aceptar el resumen abre el paso de nuevo, aunque siga en la pila (confirmacion-E08).
  return <PasoDeFechaYHora key={pedido?.ventanaRechazada?.vez ?? 0} />;
}

function PasoDeFechaYHora() {
  const router = useRouter();
  const regresar = useRegresar(RUTA_DEL_SERVICIO);
  const pedido = usePedidoEnCurso();
  // La ventana del pedido en curso, mientras el servidor no la haya rechazado (confirmacion-E08).
  const rechazada = pedido?.ventanaRechazada;
  const vigente = pedido?.ventana && pedido.ventana.inicio !== rechazada?.ventana.inicio ? pedido.ventana : null;
  // Si el pedido en curso ya tenía una ventana (se volvió a este paso), se abre en su día y aparece elegida.
  const [elegida, setElegida] = useState<Ventana | null>(vigente);
  const agenda = useAgenda(pedido?.lavanderia.id_publico, pedido?.ventana?.fecha ?? null);

  if (!pedido) {
    return <Redirect href="/inicio" />;
  }
  if (pedido.servicio === null) {
    return <Redirect href={RUTA_DEL_SERVICIO} />;
  }

  function continuar() {
    if (elegida === null) {
      return;
    }
    elegirVentana(elegida);
    router.push(RUTA_DE_LA_DIRECCION);
  }

  const { estado, dias } = agenda;
  const primerDia = dias[0] ?? null;

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: pasoDelPedido(2), alRegresar: regresar }}
      acciones={
        <>
          {elegida ? (
            <Text accessibilityLiveRegion="polite" style={[estilos.eleccion, letra('cuerpo', 'regular')]}>
              {eleccionEnTexto(elegida, primerDia)}
            </Text>
          ) : null}
          <Boton onPress={continuar} deshabilitado={elegida === null}>
            {elegida ? TEXTOS_DEL_PEDIDO.continuar : TEXTOS_DE_LA_AGENDA.eligeUnaVentana}
          </Boton>
        </>
      }
    >
      <Presentacion titulo={TEXTOS_DE_LA_AGENDA.titulo} texto={TEXTOS_DE_LA_AGENDA.subtitulo} alineacion="inicio" />
      {/* El motivo del servidor, hasta que se elige otra ventana. */}
      {rechazada && !vigente && elegida === null ? <MensajeDeError texto={rechazada.mensaje} /> : null}
      {dias.length > 0 ? (
        <SelectorDeFechaYHora
          dias={dias}
          diaElegido={agenda.fecha}
          alElegirDia={agenda.elegirDia}
          dia={estado.fase === 'listo' ? estado.dia : null}
          cargando={estado.fase === 'cargando'}
          inicioElegido={elegida?.inicio ?? null}
          alElegirVentana={setElegida}
        />
      ) : null}
      {/* Antes de la primera respuesta del servidor no se saben los días: solo que se está cargando. */}
      {dias.length === 0 && estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS_DE_LA_AGENDA.cargando}</Text>
        </View>
      ) : null}
      {estado.fase === 'error' ? <MensajeDeError texto={mensajeDeLaFalla(estado.falla)} /> : null}
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  eleccion: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
    textAlign: 'center',
  },
  cargando: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[5],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
});
