// La confirmación del pedido (openspec/changes/resumen-y-confirmacion, confirmacion-E09; NSV-2026-001-v8.pdf:17:ANEXO A,
// Sección A.3, Módulo A #11). Pide al servidor, con la sesión, el pedido por su id_publico (GET /api/pedidos/{id}).
//
// - Si el servidor ya lo pasó a Confirmado después de su pago (el estado confirmado, con folio y confirmado_en), muestra
//   el folio y el resumen con la lavandería, el servicio, la fecha con su ventana y la recogida, con el componente 6 del
//   design system. El resumen es la copia que guardó el pedido, no el pedido en curso del teléfono.
// - Si todavía no lo está, no muestra folio ni nombra el estado previo al pago (DD-123, ADR-0021): solo que todavía no
//   está confirmado. La abre la espera "Procesando pago" cuando el servidor da el cobro por pagado (pago-E08), así que
//   no vuelve a consultar.
// - Si no existe o es de otra cuenta, el servidor responde igual (confirmacion-E12) y la app dice que no lo encontró.
//   Sin conexión o con el servidor caído, sin escenario propio, los mensajes generales de la app.
// - "Volver al inicio" deja la app sin pedido en curso y abre la pantalla principal como la primera de la pila.
//
// La composición es la de la imagen a11-confirmacion de la Propuesta 04. Del mock no entran "Seguir mi pedido" (el
// seguimiento es estados-del-pedido), copiar el folio, el aviso del correo ni el de los avisos al teléfono (avisos).
import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { MENSAJES } from '../../../src/acceso/mensajes';
import { useAbrirComoPrimera } from '../../../src/navegacion/regresar';
import { estaConfirmado, type FallaAlConsultar, type Pedido } from '../../../src/pedido/api';
import { vaciarPedidoEnCurso } from '../../../src/pedido/pedidoEnCurso';
import { ResumenDelPedido } from '../../../src/pedido/ResumenDelPedido';
import { TEXTOS_DE_LA_CONFIRMACION } from '../../../src/pedido/textos';
import { usePedido } from '../../../src/pedido/usePedido';
import { Boton } from '../../../src/piezas/Boton';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { Presentacion } from '../../../src/piezas/Presentacion';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, tipografia } from '../../../src/tema/theme';

export default function Confirmacion() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const estado = usePedido(id);
  const abrirComoPrimera = useAbrirComoPrimera();

  function volverAlInicio() {
    vaciarPedidoEnCurso();
    abrirComoPrimera('/inicio');
  }

  return (
    <Pantalla
      desplazable
      acciones={
        estado.fase === 'cargando' ? undefined : (
          <Boton onPress={volverAlInicio}>{TEXTOS_DE_LA_CONFIRMACION.volverAlInicio}</Boton>
        )
      }
    >
      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS_DE_LA_CONFIRMACION.cargando}</Text>
        </View>
      ) : null}
      {estado.fase === 'listo' && estaConfirmado(estado.pedido) ? <Confirmado pedido={estado.pedido} /> : null}
      {estado.fase === 'listo' && !estaConfirmado(estado.pedido) ? (
        <Presentacion titulo={TEXTOS_DE_LA_CONFIRMACION.tuPedido} texto={TEXTOS_DE_LA_CONFIRMACION.sinConfirmar} />
      ) : null}
      {estado.fase === 'error' ? <MensajeDeError texto={mensajeDeLaConsulta(estado.falla)} /> : null}
    </Pantalla>
  );
}

// El folio y el resumen del pedido Confirmado (confirmacion-E09).
function Confirmado({ pedido }: { pedido: Pedido }) {
  return (
    <>
      <View style={estilos.encabezado}>
        <Presentacion titulo={TEXTOS_DE_LA_CONFIRMACION.confirmado} />
        <Parrafo>{TEXTOS_DE_LA_CONFIRMACION.tuFolioEs}</Parrafo>
        <Text style={[estilos.folio, letra('monoespaciada', 'fuerte')]} selectable>
          {pedido.folio}
        </Text>
      </View>
      <ResumenDelPedido
        lavanderia={pedido.lavanderia.nombre}
        servicio={pedido.servicios.map(({ nombre }) => nombre).join(', ')}
        ventana={pedido.ventana}
        recogida={pedido.recogida}
      />
    </>
  );
}

function mensajeDeLaConsulta(falla: FallaAlConsultar): string {
  switch (falla.motivo) {
    case 'no-encontrado':
      return TEXTOS_DE_LA_CONFIRMACION.noEncontrado;
    case 'sin-conexion':
      return MENSAJES.sinConexion;
    default:
      return MENSAJES.servidor;
  }
}

const estilos = StyleSheet.create({
  cargando: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[5],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  encabezado: {
    alignItems: 'center',
    gap: espaciado[2],
    paddingTop: espaciado[5],
  },
  // El folio con números tabulares, para que sus caracteres no bailen (design system, reglas transversales).
  folio: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.titulo,
    fontVariant: ['tabular-nums'],
  },
});
