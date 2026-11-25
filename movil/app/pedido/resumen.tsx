// El resumen del pedido nuevo, "Pedido nuevo · 4 de 4" (openspec/changes/resumen-y-confirmacion, confirmacion-E01 y
// E07; NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #9). Llega desde el paso de la dirección con el pedido en
// curso completo: lavandería, servicio, ventana, recogida y entrega (direcciones-E09 y E10).
//
// - Muestra el componente 6 del design system con la lavandería, el servicio, la fecha con su ventana en la hora de la
//   plaza, la recogida y la entrega (confirmacion-E01). El precio estimado y la política de cancelación van reservados,
//   sin cifra ni texto inventado (confirmacion-E02 y E04: DD-012, DD-010 y DD-016). El envío no lleva renglón: el
//   contrato enumera servicio, fecha, dirección, precio estimado y política de cancelación (NSV-2026-001-v8.pdf:16:ANEXO
//   A, Sección A.3, Módulo A #9), y si el envío es un cargo aparte lo dicen DD-019 y DD-025 (confirmacion-E03).
// - "Confirmar y pagar" manda POST /api/pedidos solo con lo que pide CrearPedidoRequest. Con 201, el pedido nació en el
//   estado previo al pago (C-188, ADR-0021): la app guarda su id_publico y abre el pago. El botón no confirma nada: lo
//   confirma el cobro (confirmacion-E07). Mientras el servidor contesta, el botón no se puede volver a tocar, y si la
//   persona regresa del pago sin cambiar nada, el botón abre el pago del mismo pedido: nunca dos pedidos por un toque
//   de más.
// - Si el servidor ya no acepta la ventana, la app regresa a fecha y hora con su motivo; si no acepta la recogida o la
//   entrega, al paso de la dirección, con el aviso bajo la que rechazó. Lo demás del pedido queda como estaba
//   (confirmacion-E08). Si la dirección quedó con la plaza de antes de un cambio de zonas, el aviso pide guardarla de
//   nuevo, y "Agregar otra dirección" lo hace (confirmacion-E15).
// - Si el servidor no puede crearlo y lo dice (404, 503), su mensaje; sin conexión o con el servidor caído, sin
//   escenario propio, los mensajes generales de la app.
//
// La composición es la de la imagen a09-resumen de la Propuesta 04. Del mock no entran el total, el envío de $35, la
// nota del precio final al pesar (DD-012 y DD-125), los íconos ni los enlaces para cambiar cada dato.
//
// Sin pedido en curso, por ejemplo con un enlace directo, lleva a la pantalla principal; si le falta algo, al paso que
// lo pide.
import { Redirect, useRouter } from 'expo-router';
import { useRef, useState } from 'react';

import { useRegresar } from '../../src/navegacion/regresar';
import { crearPedido, type SolicitudDePedido } from '../../src/pedido/api';
import {
  guardarPedidoCreado,
  rechazarDirecciones,
  rechazarVentana,
  usePedidoEnCurso,
} from '../../src/pedido/pedidoEnCurso';
import { ResumenDelPedido, RenglonReservado } from '../../src/pedido/ResumenDelPedido';
import { RUTA_DE_FECHA_Y_HORA, RUTA_DE_LA_DIRECCION, RUTA_DEL_SERVICIO, rutaDelPago } from '../../src/pedido/rutas';
import { mensajeAlCrear, pasoDelPedido, TEXTOS_DEL_RESUMEN } from '../../src/pedido/textos';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Titulo } from '../../src/piezas/Titulo';
import { useSesion } from '../../src/sesion/ProveedorDeSesion';

export default function Resumen() {
  const router = useRouter();
  const regresar = useRegresar(RUTA_DE_LA_DIRECCION);
  const { token } = useSesion();
  const pedido = usePedidoEnCurso();
  // Se marca antes de mandar, sin esperar a que se vuelva a pintar: dos toques seguidos mandan una sola petición.
  const enviando = useRef(false);
  const [ocupado, setOcupado] = useState(false);
  const [mensaje, setMensaje] = useState<string | undefined>();

  if (!pedido) {
    return <Redirect href="/inicio" />;
  }
  const servicio = pedido.lavanderia.servicios.find(({ codigo }) => codigo === pedido.servicio);
  if (!servicio) {
    return <Redirect href={RUTA_DEL_SERVICIO} />;
  }
  const { ventana, recogida, entrega } = pedido;
  if (!ventana) {
    return <Redirect href={RUTA_DE_FECHA_Y_HORA} />;
  }
  if (!recogida || !entrega) {
    return <Redirect href={RUTA_DE_LA_DIRECCION} />;
  }
  const mismaDireccion = entrega.id_publico === recogida.id_publico;

  async function aceptar(creado: string | undefined, solicitud: SolicitudDePedido) {
    if (enviando.current || !token) {
      return;
    }
    // Ya se creó con estos datos: se paga ese, no se crea otro.
    if (creado) {
      router.push(rutaDelPago(creado));
      return;
    }
    enviando.current = true;
    setOcupado(true);
    setMensaje(undefined);
    const resultado = await crearPedido(token, solicitud);
    enviando.current = false;
    setOcupado(false);
    if (resultado.ok) {
      guardarPedidoCreado(resultado.datos.id_publico);
      router.push(rutaDelPago(resultado.datos.id_publico));
      return;
    }
    switch (resultado.motivo) {
      case 'ventana':
        rechazarVentana(resultado.mensaje);
        router.dismissTo(RUTA_DE_FECHA_Y_HORA);
        return;
      case 'rechazadas':
        rechazarDirecciones(resultado.rechazos);
        router.dismissTo(RUTA_DE_LA_DIRECCION);
        return;
      default:
        setMensaje(mensajeAlCrear(resultado));
    }
  }

  const solicitud: SolicitudDePedido = {
    lavanderia: pedido.lavanderia.id_publico,
    servicio: servicio.codigo,
    ventana: { inicio: ventana.inicio, fin: ventana.fin },
    recogida: recogida.id_publico,
    entrega: mismaDireccion ? null : entrega.id_publico,
  };

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: pasoDelPedido(4), alRegresar: regresar }}
      acciones={
        <>
          <MensajeDeError texto={mensaje} />
          <Boton
            onPress={() => {
              // La petición sigue sola; mientras tanto el botón queda ocupado.
              aceptar(pedido.creado, solicitud);
            }}
            ocupado={ocupado}
          >
            {TEXTOS_DEL_RESUMEN.confirmarYPagar}
          </Boton>
        </>
      }
    >
      <Titulo alineacion="inicio">{TEXTOS_DEL_RESUMEN.titulo}</Titulo>
      <ResumenDelPedido
        lavanderia={pedido.lavanderia.nombre}
        servicio={servicio.nombre}
        ventana={ventana}
        recogida={recogida}
        entrega={mismaDireccion ? 'misma' : entrega}
      >
        <RenglonReservado testID="renglon-precio-estimado" etiqueta={TEXTOS_DEL_RESUMEN.precioEstimado} />
        <RenglonReservado
          testID="renglon-politica-de-cancelacion"
          etiqueta={TEXTOS_DEL_RESUMEN.politicaDeCancelacion}
        />
      </ResumenDelPedido>
    </Pantalla>
  );
}
