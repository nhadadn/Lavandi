// Los pedidos asignados del repartidor, la primera pantalla del Módulo C (openspec/changes/repartidor;
// NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #2: "Lista de pedidos asignados en tiempo real"): a donde entra
// la cuenta de repartidor que ya opera (registro-E33 y repartidor-E03). Muestra sus pedidos en curso, como los da el
// servidor, de la recolección más próxima a la más lejana, cada uno con la tarjeta de pedido de socio repartidor, el
// componente 5 del design system (repartidor-E10), y los pone al día cada 30 segundos con la pantalla visible
// (repartidor-E11; ADR-0007). Sin pedidos, un mensaje neutro; si la lista no llega, sin escenario propio, los mensajes
// generales de la app.
//
// La oferta que espera su respuesta se acepta desde su tarjeta (repartidor-E15): la tarjeta se bloquea mientras se manda
// y se pone al día con la respuesta. "Rechazar" abre el rechazo, que pide el motivo (repartidor-E16 y E17). El pedido
// aceptado ofrece solo el botón de su paso siguiente, que se manda igual que Aceptar (repartidor-E19); si ya no es el
// paso siguiente o la oferta ya se respondió (409), el mensaje del servidor va arriba de la lista y la lista se vuelve a
// consultar (repartidor-E20). "Entregado" no se manda desde la tarjeta: abre la entrega, que deja tomar la foto de
// evidencia antes de marcarlo (repartidor-E23).
//
// Bajo el título, la cabecera con los datos del repartidor: su nombre, su foto, su teléfono, su correo y su tipo de
// vehículo, como los da el perfil que el módulo ya leyó al entrar (src/repartidor/ProveedorDelRepartidor.tsx). Es donde
// el repartidor ve lo que le editó la administración (repartidor-E08; acta-2026-11-20-modelo-y-pasarela.md, decisión
// 55): al volver a entrar al módulo, el perfil llega con los datos nuevos. Sin pantalla, ruta ni petición propias, y sin
// nada que editar. La foto se ve desde su URL firmada; sin foto, nada en su lugar. Del mock c02-asignados no sale: se
// arma con los tokens, como las pantallas sin componente (ADR-0013).
//
// Debajo, las entradas a lo demás del Módulo C, como enlaces, igual que en los pedidos de la lavandería mientras no
// exista una barra inferior: el historial de entregas (repartidor-E25) y las estadísticas (repartidor-E28).
//
// "Cerrar sesión" va abajo, con la confirmación de la app (registro-E13). La composición sigue la imagen c02-asignados
// de la Propuesta 04: el título a la izquierda y las tarjetas debajo.
import { useRouter } from 'expo-router';
import { ActivityIndicator, Image, StyleSheet, Text, View } from 'react-native';

import { Enlace } from '../../../src/piezas/Enlace';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { TarjetaDePedidoDeRepartidor } from '../../../src/piezas/TarjetaDePedidoDeRepartidor';
import { Titulo } from '../../../src/piezas/Titulo';
import { ESTADO_ENTREGADO, type PerfilDelRepartidor } from '../../../src/repartidor/api';
import { useRepartidorDeLaCuenta } from '../../../src/repartidor/ProveedorDelRepartidor';
import {
  RUTA_DE_LAS_ESTADISTICAS_DEL_REPARTIDOR,
  RUTA_DEL_HISTORIAL_DEL_REPARTIDOR,
  rutaDeLaEntrega,
  rutaDelRechazoDelRepartidor,
} from '../../../src/repartidor/rutas';
import { mensajeDelRepartidor, TEXTOS_DEL_REPARTIDOR as TEXTOS } from '../../../src/repartidor/textos';
import { useAccionesDelRepartidor } from '../../../src/repartidor/useAccionesDelRepartidor';
import { usePedidosDelRepartidor } from '../../../src/repartidor/usePedidosDelRepartidor';
import { CerrarSesion } from '../../../src/sesion/CerrarSesion';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, radio, tipografia } from '../../../src/tema/theme';

// El lado del círculo de la foto, con las medidas de tokens.json.
const LADO_DE_LA_FOTO = espaciado[8];

export default function PedidosDelRepartidor() {
  const router = useRouter();
  // El módulo solo abre sus pedidos con el perfil ya leído (app/repartidor/_layout.tsx).
  const { estado } = useRepartidorDeLaCuenta();
  const lista = usePedidosDelRepartidor();
  const { pedidos, falla } = lista;
  const acciones = useAccionesDelRepartidor(lista);
  // El mensaje de la lista solo si no repite el de la última acción, como sin conexión en las dos.
  const mensajeDeLaLista = falla ? mensajeDelRepartidor(falla) : null;

  return (
    <Pantalla desplazable acciones={<CerrarSesion />}>
      <Titulo alineacion="inicio">{TEXTOS.pedidosAsignados}</Titulo>
      {estado.fase === 'listo' ? <DatosDelRepartidor repartidor={estado.repartidor} /> : null}
      <View style={estilos.entradas}>
        <Enlace
          texto={TEXTOS.historialDeEntregas}
          alTocar={() => router.push(RUTA_DEL_HISTORIAL_DEL_REPARTIDOR)}
        />
        <Enlace
          texto={TEXTOS.misEstadisticas}
          alTocar={() => router.push(RUTA_DE_LAS_ESTADISTICAS_DEL_REPARTIDOR)}
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
        <TarjetaDePedidoDeRepartidor
          key={pedido.id_publico}
          pedido={pedido}
          enviando={acciones.enviandoEl(pedido)}
          alAceptar={() => void acciones.aceptar(pedido)}
          alRechazar={() => router.push(rutaDelRechazoDelRepartidor(pedido.id_publico))}
          alAvanzar={() =>
            pedido.siguiente?.estado === ESTADO_ENTREGADO
              ? router.push(rutaDeLaEntrega(pedido.id_publico))
              : void acciones.avanzar(pedido)
          }
        />
      ))}
      <MensajeDeError texto={mensajeDeLaLista && mensajeDeLaLista !== acciones.aviso ? mensajeDeLaLista : undefined} />
    </Pantalla>
  );
}

// La cabecera con sus datos (repartidor-E08): la foto en un círculo, a la izquierda, y a su lado el nombre y, debajo,
// el teléfono, el correo y el tipo de vehículo, cada uno con su etiqueta en el tono suave.
function DatosDelRepartidor({ repartidor }: { repartidor: PerfilDelRepartidor }) {
  const { nombre, foto, telefono, correo, tipo_vehiculo: tipoDeVehiculo } = repartidor;
  return (
    <View style={estilos.cabecera} testID="datos-del-repartidor">
      {foto ? (
        <Image
          accessible
          accessibilityRole="image"
          accessibilityLabel={TEXTOS.tuFotoDePerfil}
          source={{ uri: foto.url }}
          resizeMode="cover"
          style={estilos.foto}
        />
      ) : null}
      <View style={estilos.datos}>
        <Text style={[estilos.nombre, letra('cuerpo', 'semifuerte')]}>{nombre}</Text>
        <DatoDelRepartidor etiqueta={TEXTOS.telefono} valor={telefono} />
        <DatoDelRepartidor etiqueta={TEXTOS.correo} valor={correo} />
        <DatoDelRepartidor etiqueta={TEXTOS.tipoDeVehiculo} valor={tipoDeVehiculo} />
      </View>
    </View>
  );
}

// Un dato de la cabecera, que se lee en un solo anuncio: "Teléfono: 618 123 4567".
function DatoDelRepartidor({ etiqueta, valor }: { etiqueta: string; valor: string }) {
  return (
    <View accessible accessibilityLabel={`${etiqueta}: ${valor}`} style={estilos.dato}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <Text style={[estilos.valor, letra('cuerpo', 'regular')]}>{valor}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  cabecera: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
  },
  foto: {
    width: LADO_DE_LA_FOTO,
    height: LADO_DE_LA_FOTO,
    borderRadius: radio.pildora,
    backgroundColor: color.semantico.superficie_hundida,
  },
  datos: {
    flex: 1,
    gap: espaciado[1],
  },
  nombre: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  // La etiqueta y su valor en una línea; si no caben, el valor baja a la siguiente.
  dato: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    columnGap: espaciado[2],
  },
  etiqueta: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  valor: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
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
