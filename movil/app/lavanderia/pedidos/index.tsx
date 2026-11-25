// La pantalla de pedidos de la lavandería, la primera del Módulo B (openspec/changes/pedidos-de-la-lavanderia;
// NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2): a donde entra la cuenta de socio lavandería
// (registro-E33). Muestra sus pedidos activos, como los da el servidor, de la recolección más próxima a la más lejana,
// cada uno con la tarjeta de pedido de socio lavandería, el componente 4 del design system (pedidos-lavanderia-E03).
// Sin pedidos, un mensaje neutro; si la lista no llega, sin escenario propio, los mensajes generales de la app, como
// "Mis pedidos".
//
// El pedido que espera respuesta se acepta desde su tarjeta (pedidos-lavanderia-E07): la tarjeta se bloquea mientras se
// manda y se pone al día con la respuesta. Si el pedido ya fue respondido (409), el mensaje del servidor va arriba de
// la lista y la lista se vuelve a consultar (pedidos-lavanderia-E08). "Rechazar" abre el rechazo, que pide el motivo
// (pedidos-lavanderia-E12 y E13). El pedido aceptado ofrece solo el botón de su paso siguiente, que se manda igual que
// Aceptar (estados-E06); si ya no es el paso siguiente (409), el mensaje va arriba y la lista se vuelve a consultar.
//
// Bajo el título, las entradas a lo demás del Módulo B, como enlaces, igual que en el inicio del usuario mientras no
// exista una barra inferior: el historial de pedidos (historial-lavanderia-E01), las estadísticas
// (historial-lavanderia-E05) y el perfil y horarios (alta-lavanderias-E19).
//
// "Cerrar sesión" va abajo, con la confirmación de la app (registro-E13). La composición sigue las imágenes del Módulo
// B de la Propuesta 04: el título a la izquierda y las tarjetas debajo.
import { useRouter } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { RUTA_DEL_PERFIL_DE_LA_LAVANDERIA } from '../../../src/alta-lavanderias/rutas';
import { TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO } from '../../../src/alta-lavanderias/textos';
import { mensajeDeLaFalla } from '../../../src/cercanas/textos';
import {
  RUTA_DE_LAS_ESTADISTICAS_DE_LA_LAVANDERIA,
  RUTA_DEL_HISTORIAL_DE_LA_LAVANDERIA,
} from '../../../src/historial-lavanderia/rutas';
import { TEXTOS_DEL_HISTORIAL_DE_LA_LAVANDERIA } from '../../../src/historial-lavanderia/textos';
import { rutaDelRechazo } from '../../../src/pedidos-lavanderia/rutas';
import { TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA as TEXTOS } from '../../../src/pedidos-lavanderia/textos';
import { useAccionesDeLaLavanderia } from '../../../src/pedidos-lavanderia/useAccionesDeLaLavanderia';
import { usePedidosDeLaLavanderia } from '../../../src/pedidos-lavanderia/usePedidosDeLaLavanderia';
import { Enlace } from '../../../src/piezas/Enlace';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { TarjetaDePedidoDeLavanderia } from '../../../src/piezas/TarjetaDePedidoDeLavanderia';
import { Titulo } from '../../../src/piezas/Titulo';
import { CerrarSesion } from '../../../src/sesion/CerrarSesion';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, tipografia } from '../../../src/tema/theme';

export default function PedidosDeLaLavanderia() {
  const router = useRouter();
  const lista = usePedidosDeLaLavanderia();
  const { pedidos, falla } = lista;
  const acciones = useAccionesDeLaLavanderia(lista);
  // El mensaje de la lista solo si no repite el de la última acción, como sin conexión en las dos.
  const mensajeDeLaLista = falla ? mensajeDeLaFalla(falla) : null;

  return (
    <Pantalla desplazable acciones={<CerrarSesion />}>
      <Titulo alineacion="inicio">{TEXTOS.titulo}</Titulo>
      <View style={estilos.entradas}>
        <Enlace
          texto={TEXTOS_DEL_HISTORIAL_DE_LA_LAVANDERIA.historial}
          alTocar={() => router.push(RUTA_DEL_HISTORIAL_DE_LA_LAVANDERIA)}
        />
        <Enlace
          texto={TEXTOS_DEL_HISTORIAL_DE_LA_LAVANDERIA.estadisticas}
          alTocar={() => router.push(RUTA_DE_LAS_ESTADISTICAS_DE_LA_LAVANDERIA)}
        />
        <Enlace
          texto={TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO.perfilYHorarios}
          alTocar={() => router.push(RUTA_DEL_PERFIL_DE_LA_LAVANDERIA)}
        />
      </View>
      <MensajeDeError texto={acciones.aviso ?? undefined} />
      {pedidos === null && falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargando}</Text>
        </View>
      ) : null}
      {pedidos?.length === 0 ? <Parrafo alineacion="inicio">{TEXTOS.sinPedidos}</Parrafo> : null}
      {pedidos?.map((pedido) => (
        <TarjetaDePedidoDeLavanderia
          key={pedido.id_publico}
          pedido={pedido}
          enviando={acciones.enviandoEl(pedido)}
          alAceptar={() => void acciones.aceptar(pedido)}
          alRechazar={() => router.push(rutaDelRechazo(pedido.id_publico))}
          alAvanzar={() => void acciones.avanzar(pedido)}
        />
      ))}
      <MensajeDeError texto={mensajeDeLaLista && mensajeDeLaLista !== acciones.aviso ? mensajeDeLaLista : undefined} />
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  // Las entradas a lo demás del módulo, en una línea; si no caben, bajan a la siguiente.
  entradas: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    columnGap: espaciado[4],
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
