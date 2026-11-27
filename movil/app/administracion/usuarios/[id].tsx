// La ficha de una cuenta de usuario en la gestión de usuarios (F-D03; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo
// D #3, "Consulta de usuarios activos, historial y bloqueo"): se abre al tocarla en la lista, por su id_publico. Muestra
// su nombre, su estado, su correo, su teléfono y su historial de pedidos, cada uno con folio, fecha, lavandería, estado
// y monto, en el orden en que llegan: del más reciente al más antiguo (administracion-app-E24, con la regla de
// administracion-E01).
//
// Abajo, fijo, "Bloquear" o "Levantar el bloqueo" según su estado, cada uno con su confirmación y el texto del panel; al
// confirmarlo, la ficha muestra lo que respondió el servidor, con el estado nuevo (administracion-app-E25). Lo que pasa
// con un pedido en curso al bloquear espera DD-016 (administracion-app-E27): va en ámbar sobre "Bloquear", y no dentro
// del diálogo. Una cuenta que no existe responde 404 y la ficha lo dice, sin ningún dato ni botón
// (administracion-app-E12). Con un 401, el cliente de la administración ya volvió al inicio de sesión
// (administracion-app-E05). No ofrece editar ni borrar la cuenta: el Módulo D #3 dice consulta, historial y bloqueo.
//
// La composición es la de las imágenes 22 a 24 y 66 de la Propuesta 06: el nombre con la insignia de su estado, el
// correo y el teléfono en una tarjeta, el historial con una tarjeta por pedido y el botón fijo abajo: "Bloquear" como
// secundario y "Levantar el bloqueo" como principal, con lo que pasa al levantarlo.
import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import type { FichaDelUsuario, PedidoDelUsuario } from '../../../src/administracion/usuarios/api';
import { InsigniaDelUsuario } from '../../../src/administracion/usuarios/InsigniaDelUsuario';
import { PorConfirmar } from '../../../src/administracion/usuarios/PorConfirmar';
import { RUTA_DE_LOS_USUARIOS } from '../../../src/administracion/usuarios/rutas';
import {
  fechaYMonto,
  mensajeDeLaFalla,
  nombreDelUsuario,
  pedidoParaLeer,
  TEXTOS_DE_LOS_USUARIOS as TEXTOS,
} from '../../../src/administracion/usuarios/textos';
import {
  useFichaDelUsuario,
  type FichaDeLaPantalla,
} from '../../../src/administracion/usuarios/useFichaDelUsuario';
import { pedidoConFolio } from '../../../src/estados/textos';
import { useRegresar } from '../../../src/navegacion/regresar';
import { Boton } from '../../../src/piezas/Boton';
import { InsigniaDeEstado } from '../../../src/piezas/InsigniaDeEstado';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { TituloDeSeccion } from '../../../src/piezas/TituloDeSeccion';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../../src/tema/theme';

export default function FichaDelUsuarioEnLaAdministracion() {
  const regresar = useRegresar(RUTA_DE_LOS_USUARIOS);
  const { id } = useLocalSearchParams<{ id: string }>();
  const ficha = useFichaDelUsuario(id);
  const { estado } = ficha;
  const usuario = estado.fase === 'lista' ? estado.usuario : null;

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS.tituloDeLaFicha, alRegresar: regresar }}
      acciones={usuario ? <AccionesDelBloqueo usuario={usuario} ficha={ficha} /> : undefined}
    >
      {ficha.fallaDeLaAccion ? <MensajeDeError texto={mensajeDeLaFalla(ficha.fallaDeLaAccion)} /> : null}
      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoLaFicha}</Text>
        </View>
      ) : null}
      {/* Una cuenta que no existe no tiene nada que volver a pedir (administracion-app-E12). */}
      {estado.fase === 'no-existe' ? <MensajeDeError texto={mensajeDeLaFalla(estado.falla)} /> : null}
      {estado.fase === 'error' ? (
        <>
          <MensajeDeError texto={mensajeDeLaFalla(estado.falla)} />
          <Boton variante="secundario" onPress={ficha.volverAConsultar}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}
      {usuario ? (
        <>
          <View style={estilos.presentacion}>
            <TituloDeSeccion>{nombreDelUsuario(usuario.nombre)}</TituloDeSeccion>
            <View style={estilos.insignia}>
              <InsigniaDelUsuario estado={usuario.estado} testID="estado-del-usuario" />
            </View>
          </View>
          <View style={estilos.tarjeta}>
            <Dato etiqueta={TEXTOS.correo} valor={usuario.correo} testID="dato-correo" />
            <Dato
              etiqueta={TEXTOS.telefono}
              valor={usuario.telefono ?? TEXTOS.sinTelefono}
              testID="dato-telefono"
              ultimo
            />
          </View>
          <TituloDeSeccion>{TEXTOS.historial}</TituloDeSeccion>
          {usuario.historial.length === 0 ? <Parrafo alineacion="inicio">{TEXTOS.sinPedidos}</Parrafo> : null}
          {usuario.historial.map((pedido) => (
            <PedidoDelHistorial key={pedido.folio} pedido={pedido} />
          ))}
        </>
      ) : null}
    </Pantalla>
  );
}

// Un dato de la cuenta, que se lee en un solo anuncio: "Teléfono: 6181234567". La etiqueta a la izquierda y el valor a
// la derecha; si no caben, el valor baja al siguiente renglón.
function Dato({
  etiqueta,
  valor,
  testID,
  ultimo = false,
}: {
  etiqueta: string;
  valor: string;
  testID: string;
  ultimo?: boolean;
}) {
  return (
    <View
      accessible
      accessibilityLabel={`${etiqueta}: ${valor}`}
      testID={testID}
      style={[estilos.dato, ultimo ? null : estilos.conLinea]}
    >
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <Text style={[estilos.valor, letra('cuerpo', 'regular')]}>{valor}</Text>
    </View>
  );
}

// Los estados con que un pedido ya terminó, como los nombra la administración (servidor/app/Pedidos/NombresDelEstado.php):
// van en el texto suave, como "Entregado" en las imágenes 22 y 24; los que siguen en curso, en atención, como "En
// proceso". El nombre siempre va escrito: el color nunca va solo.
//
// Pendiente de la integración: esta regla no es la de la app. El color de un estado operativo es el de
// colorDelEstadoOperativo (src/piezas/TarjetaDePedidoDeLavanderia.tsx, adenda de ADR-0021), que pide su código, y el
// historial de UsuariosController solo trae su nombre. "Recolectado", "Aceptado", "Listo" y "En proceso de entrega"
// salen aquí en atención y no en su color. Cuando el contrato agregue el código del estado a cada pedido del historial,
// la tarjeta usa colorDelEstadoOperativo(codigo) y esta regla se va. No se traduce el nombre a su código en la app:
// sería otra copia de NombresDelEstado.
const TERMINADOS: ReadonlySet<string> = new Set(['Entregado', 'Rechazado']);

function tonoDelPedido(estado: string): string {
  return TERMINADOS.has(estado) ? color.semantico.texto_suave : color.estado.atencion;
}

// Un pedido del historial, que se lee en un solo anuncio: "Pedido 7K2M9QXA, En proceso, Lavandería del centro,
// 24/11/2026, $250.00". El folio y su estado arriba, la lavandería, y la fecha y el monto debajo.
function PedidoDelHistorial({ pedido }: { pedido: PedidoDelUsuario }) {
  return (
    <View accessible accessibilityLabel={pedidoParaLeer(pedido)} testID={`pedido-${pedido.folio}`} style={estilos.pedido}>
      <View style={estilos.cabecera}>
        <Text style={[estilos.folio, letra('cuerpo', 'semifuerte')]}>{pedidoConFolio(pedido.folio)}</Text>
        <InsigniaDeEstado
          codigo={pedido.estado}
          nombre={pedido.estado}
          forma="cuadrada"
          tono={tonoDelPedido(pedido.estado)}
        />
      </View>
      <Text style={[estilos.lavanderia, letra('cuerpo', 'regular')]}>{pedido.lavanderia}</Text>
      <Text style={[estilos.fechaYMonto, letra('cuerpo', 'regular')]}>{fechaYMonto(pedido)}</Text>
    </View>
  );
}

// El botón fijo abajo, según el estado de la cuenta. Activa: lo que espera DD-016 sobre "Bloquear", secundario.
// Bloqueada: lo que pasa al levantarlo sobre "Levantar el bloqueo", principal. Cada uno pide su confirmación con el
// diálogo del sistema (useFichaDelUsuario); con "Cancelar" no pasa nada. Mientras la acción va, el botón no se puede
// volver a tocar.
function AccionesDelBloqueo({ usuario, ficha }: { usuario: FichaDelUsuario; ficha: FichaDeLaPantalla }) {
  if (usuario.estado === 'bloqueado') {
    return (
      <>
        <Parrafo alineacion="inicio">{TEXTOS.alLevantarlo}</Parrafo>
        <Boton ocupado={ficha.enCurso === 'levantar'} onPress={ficha.levantarElBloqueo}>
          {TEXTOS.levantarElBloqueo}
        </Boton>
      </>
    );
  }
  return (
    <>
      <PorConfirmar
        etiqueta={TEXTOS.pedidoEnCursoAlBloquear}
        dd={TEXTOS.esperaElPedidoEnCurso}
        testID="bloqueo-con-pedido-en-curso"
      />
      <Boton variante="secundario" ocupado={ficha.enCurso === 'bloquear'} onPress={ficha.bloquear}>
        {TEXTOS.bloquear}
      </Boton>
    </>
  );
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
  presentacion: {
    gap: espaciado[3],
  },
  // La insignia no se estira a todo lo ancho.
  insignia: {
    flexDirection: 'row',
  },
  // La tarjeta de los datos, con un renglón por dato.
  tarjeta: {
    paddingHorizontal: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  dato: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    columnGap: espaciado[3],
    paddingVertical: espaciado[4],
  },
  conLinea: {
    borderBottomWidth: grosor.control,
    borderBottomColor: color.semantico.borde,
  },
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  valor: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    textAlign: 'right',
  },
  // La tarjeta de cada pedido del historial.
  pedido: {
    gap: espaciado[2],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  cabecera: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  folio: {
    flex: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  lavanderia: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  fechaYMonto: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
    fontVariant: ['tabular-nums'],
  },
});
