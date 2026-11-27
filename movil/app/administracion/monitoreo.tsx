// Monitoreo de pedidos (F-D04; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #4, "Vista en tiempo real de todos
// los pedidos activos con mapa"; administracion-app-E28 a E30): se abre con "Monitoreo de pedidos" en el inicio del
// módulo (administracion-app-E03). Arriba, el mapa con los marcadores; debajo, la lista de los pedidos activos de todas
// las plazas, cada uno con su folio, su estado operativo, su plaza y su recolección. Los dos salen de la misma respuesta
// de GET /api/administracion/monitoreo, que se pide cada 30 s mientras la pantalla está visible y la app al frente
// (administracion-app-E29; ADR-0022, decisión 12): el mapa no se vuelve a crear, cambian sus marcadores. Ningún
// repartidor (DD-077).
//
// En una compilación de Android sin la llave del mapa, el mapa no se monta y la pantalla lo dice; la lista sigue
// (administracion-app-E30; src/cercanas/mapa.ts). Si una consulta no llega, sin escenario propio: se queda la última
// lista, con los mensajes generales de la app e "Intentar de nuevo", como la imagen 05 de la Propuesta 06. Con un 401, el
// cliente de la administración ya volvió al inicio de sesión (administracion-app-E05).
//
// La composición y los textos son los de las imágenes 25 a 27 de la Propuesta 06: el encabezado con el regreso al inicio
// del módulo, cuántos pedidos activos hay y que se pone al día cada 30 s, el mapa con la leyenda de sus letras y, bajo
// "Pedidos activos", una tarjeta por pedido con la insignia cuadrada de su estado operativo, como la tarjeta de pedido
// de la lavandería. Sin pedidos activos, el mapa sin marcadores y "No hay pedidos activos.", el texto del panel.
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import type { PedidoDelMonitoreo } from '../../src/administracion/monitoreo/api';
import { MapaDelMonitoreo } from '../../src/administracion/monitoreo/MapaDelMonitoreo';
import {
  mensajeDelMonitoreo,
  recoleccionEnTexto,
  resumenDelMonitoreo,
  TEXTOS_DEL_MONITOREO as TEXTOS,
} from '../../src/administracion/monitoreo/textos';
import { useMonitoreo } from '../../src/administracion/monitoreo/useMonitoreo';
import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../src/administracion/rutas';
import { hayMapaNativo } from '../../src/cercanas/mapa';
import { pedidoConFolio } from '../../src/estados/textos';
import { useRegresar } from '../../src/navegacion/regresar';
import { Boton } from '../../src/piezas/Boton';
import { InsigniaDeEstado } from '../../src/piezas/InsigniaDeEstado';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { colorDelEstadoOperativo } from '../../src/piezas/TarjetaDePedidoDeLavanderia';
import { TituloDeSeccion } from '../../src/piezas/TituloDeSeccion';
import { letra } from '../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../src/tema/theme';

export default function MonitoreoDePedidos() {
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const { monitoreo, falla, consultarDeNuevo } = useMonitoreo();
  const conMapa = hayMapaNativo();
  const pedidos = monitoreo?.pedidos ?? null;

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.titulo, alRegresar: regresar }}>
      <Text style={[estilos.menor, letra('cuerpo', 'regular')]}>{resumenDelMonitoreo(pedidos?.length ?? null)}</Text>
      {/* Siempre en el mismo lugar, aun sin respuesta: así el mapa se crea una vez. */}
      {conMapa ? (
        <MapaDelMonitoreo marcadores={monitoreo?.marcadores ?? []} />
      ) : (
        <View style={estilos.tarjeta} testID="mapa-no-disponible">
          <Text style={[estilos.parrafo, letra('cuerpo', 'regular')]}>{TEXTOS.mapaNoDisponible}</Text>
        </View>
      )}
      {conMapa && pedidos && pedidos.length > 0 ? (
        <Text style={[estilos.menor, letra('cuerpo', 'regular')]}>{TEXTOS.leyenda}</Text>
      ) : null}
      {monitoreo === null && falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.parrafo, letra('cuerpo', 'regular')]}>{TEXTOS.cargando}</Text>
        </View>
      ) : null}
      {falla ? (
        <>
          <MensajeDeError texto={mensajeDelMonitoreo(falla)} />
          <Boton variante="secundario" onPress={consultarDeNuevo}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}
      {pedidos?.length === 0 ? (
        <View style={estilos.tarjeta}>
          <Text style={[estilos.parrafo, letra('cuerpo', 'regular')]}>{TEXTOS.sinPedidos}</Text>
        </View>
      ) : null}
      {pedidos && pedidos.length > 0 ? (
        <>
          <TituloDeSeccion>{TEXTOS.pedidosActivos}</TituloDeSeccion>
          {pedidos.map((pedido) => (
            <TarjetaDelPedido key={pedido.id_publico} pedido={pedido} />
          ))}
        </>
      ) : null}
    </Pantalla>
  );
}

// Un pedido de la lista (imagen 25): "Pedido <folio>" y la insignia cuadrada de su estado operativo, con el nombre que da
// el servidor y el color de la tarjeta de pedido de la lavandería; debajo, su plaza y su recolección en la hora de la
// plaza. Solo se lee: el monitoreo no tiene acciones.
function TarjetaDelPedido({ pedido }: { pedido: PedidoDelMonitoreo }) {
  const { id_publico: idPublico, folio, estado, plaza, ventana } = pedido;
  return (
    <View style={[estilos.tarjeta, estilos.compacta]} testID={`pedido-${idPublico}`}>
      <View style={estilos.cabecera}>
        <Text style={[estilos.folio, letra('titulares', 'fuerte')]}>{pedidoConFolio(folio)}</Text>
        <InsigniaDeEstado
          codigo={estado.codigo}
          nombre={estado.nombre}
          forma="cuadrada"
          tono={colorDelEstadoOperativo(estado.codigo)}
          testID="estado-del-pedido"
        />
      </View>
      <Text style={[estilos.renglon, letra('cuerpo', 'regular')]}>{plaza}</Text>
      <Text style={[estilos.renglon, estilos.suave, letra('cuerpo', 'regular')]}>{recoleccionEnTexto(ventana)}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  // El texto menor y suave bajo el encabezado y bajo el mapa, como el "par menor" de la imagen 25.
  menor: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    lineHeight: tipografia.escala.menor * tipografia.interlineado.normal,
  },
  parrafo: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
    lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
  },
  cargando: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[5],
  },
  tarjeta: {
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  // La tarjeta compacta de la lista, como en la imagen 25.
  compacta: {
    paddingVertical: espaciado[3],
    gap: espaciado[1],
  },
  cabecera: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  folio: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  renglon: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
    lineHeight: tipografia.escala.menor * tipografia.interlineado.normal,
  },
  suave: {
    color: color.semantico.texto_suave,
  },
});
