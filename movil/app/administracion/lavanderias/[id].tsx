// La ficha de una lavandería para la administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1;
// administracion-app-E13, E15, E16 y E12; tareas 4.1 y 4.2; design.md del cambio, F-D01). Se abre desde la lista. Trae,
// como GET /api/administracion/lavanderias/{id_publico}, su nombre, su estado y su plaza, y sus datos editables: la
// dirección, los servicios y precios, las ventanas de disponibilidad, y la descripción y las fotos.
//
// Abajo, fijos, "Editar" en cualquier estado, también de baja, como el panel (LavanderiasTable.php:44), y solo los
// botones de las transiciones que trae la respuesta: la app no decide cuáles. Cada uno pide su confirmación
// (src/administracion/confirmar.ts) con los textos del panel, y después la ficha queda con el estado nuevo y solo sus
// acciones. Con un 409, porque otra sesión ya la cambió, el mensaje del servidor tal cual arriba de la ficha, que se
// vuelve a pedir (administracion-app-E12).
//
// Lo que pasa con los pedidos en curso al suspender o dar de baja espera DD-104 (administracion-app-E18): va en ámbar
// sobre los botones, no dentro del diálogo. Una lavandería pendiente no lo lleva: nunca tuvo alta, así que no aparece a
// los usuarios ni tiene pedidos (imagen 09). El alta no se ofrece: espera DD-053 (administracion-app-E17).
//
// La composición es la de las imágenes 07 a 11 y 57 a 59 de la Propuesta 06: el encabezado "Lavandería" con regreso; el
// nombre, la insignia del estado y la plaza; los datos en una tarjeta; debajo, lo que pasa al levantar la suspensión o
// que la baja no tiene salida. La acción principal va primero, a todo lo ancho: "Levantar la suspensión" en la
// suspendida, "Editar" en las demás; las otras, secundarias, de dos en dos. En la de baja, "Editar" sola, secundaria.
import { useLocalSearchParams, useRouter } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import type { LavanderiaDeLaAdministracion, Transicion } from '../../../src/administracion/lavanderias/api';
import { EstadoYPlaza } from '../../../src/administracion/lavanderias/EstadoYPlaza';
import { PorConfirmar } from '../../../src/administracion/lavanderias/PorConfirmar';
import { rutaDeLaEdicion } from '../../../src/administracion/lavanderias/rutas';
import {
  ACCIONES_DE_ESTADO,
  DECISIONES,
  descripcionYFotosEnTexto,
  direccionEnTexto,
  mensajeDeLaAdministracion,
  serviciosEnTexto,
  TEXTOS_DE_LAS_LAVANDERIAS as TEXTOS,
  ventanasEnTexto,
} from '../../../src/administracion/lavanderias/textos';
import { useLavanderia } from '../../../src/administracion/lavanderias/useLavanderia';
import { confirmar } from '../../../src/administracion/confirmar';
import { RUTAS_DE_LAS_SECCIONES } from '../../../src/administracion/rutas';
import { useRegresar } from '../../../src/navegacion/regresar';
import { Boton } from '../../../src/piezas/Boton';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { TituloDeSeccion } from '../../../src/piezas/TituloDeSeccion';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../../src/tema/theme';

export default function FichaDeLaLavanderia() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const regresar = useRegresar(RUTAS_DE_LAS_SECCIONES.lavanderias);
  const { estado, aviso, enCurso, cambiar, reintentar } = useLavanderia(id);
  const lavanderia = estado.fase === 'lista' ? estado.lavanderia : null;

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS.lavanderia, alRegresar: regresar }}
      acciones={
        lavanderia ? (
          <Acciones lavanderia={lavanderia} enCurso={enCurso} alCambiar={(transicion) => void cambiar(transicion)} />
        ) : undefined
      }
    >
      <MensajeDeError texto={aviso ?? undefined} />

      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoLaFicha}</Text>
        </View>
      ) : null}

      {estado.fase === 'error' ? (
        <>
          <MensajeDeError texto={mensajeDeLaAdministracion(estado.falla)} />
          {/* Una que no existe no aparece al intentar otra vez. */}
          {estado.falla.motivo !== 'no-existe' ? (
            <Boton variante="secundario" onPress={reintentar}>
              {TEXTOS.intentarDeNuevo}
            </Boton>
          ) : null}
        </>
      ) : null}

      {lavanderia ? (
        <>
          <View style={estilos.cabecera}>
            <TituloDeSeccion>{lavanderia.nombre}</TituloDeSeccion>
            <EstadoYPlaza estado={lavanderia.estado} plaza={lavanderia.plaza} />
          </View>
          <View style={estilos.tarjeta}>
            <Dato etiqueta={TEXTOS.direccion} valor={direccionEnTexto(lavanderia.direccion)} testID="dato-direccion" />
            <Dato
              etiqueta={TEXTOS.serviciosYPrecios}
              valor={serviciosEnTexto(lavanderia.servicios)}
              testID="dato-servicios"
              siguiente
            />
            <Dato
              etiqueta={TEXTOS.ventanas}
              valor={ventanasEnTexto(lavanderia.ventanas)}
              testID="dato-ventanas"
              siguiente
            />
            <Dato
              etiqueta={TEXTOS.descripcionYFotos}
              valor={descripcionYFotosEnTexto(lavanderia)}
              testID="dato-descripcion-y-fotos"
              siguiente
            />
          </View>
          {lavanderia.transiciones.includes('levantar_suspension') ? (
            <Parrafo alineacion="inicio">{TEXTOS.alLevantarLaSuspension}</Parrafo>
          ) : null}
          {lavanderia.estado === 'baja' ? <Parrafo alineacion="inicio">{TEXTOS.deBaja}</Parrafo> : null}
        </>
      ) : null}
    </Pantalla>
  );
}

interface PropiedadesDelDato {
  etiqueta: string;
  valor: string;
  testID: string;
  /** Desde el segundo dato, con la línea de borde arriba. */
  siguiente?: boolean;
}

// Un dato de la tarjeta: su etiqueta suave y su valor.
function Dato({ etiqueta, valor, testID, siguiente = false }: PropiedadesDelDato) {
  return (
    <View style={[estilos.dato, siguiente && estilos.datoSiguiente]} testID={testID}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <Text style={[estilos.valor, letra('cuerpo', 'regular')]}>{valor}</Text>
    </View>
  );
}

interface PropiedadesDeLasAcciones {
  lavanderia: LavanderiaDeLaAdministracion;
  enCurso: Transicion | null;
  alCambiar: (transicion: Transicion) => void;
}

// Los botones fijos abajo: "Editar" y solo las transiciones que trajo la respuesta, cada una con su confirmación; sobre
// ellos, lo que espera DD-104.
function Acciones({ lavanderia, enCurso, alCambiar }: PropiedadesDeLasAcciones) {
  const router = useRouter();
  const { transiciones, estado, id_publico: idPublico } = lavanderia;

  const editar = { clave: 'editar', texto: TEXTOS.editar, alTocar: () => router.push(rutaDeLaEdicion(idPublico)) };
  const deEstado = transiciones.map((transicion) => {
    const { boton, titulo, texto } = ACCIONES_DE_ESTADO[transicion];
    return {
      clave: transicion,
      texto: boton,
      alTocar: () => confirmar({ titulo, texto, accion: boton, alConfirmar: () => alCambiar(transicion) }),
    };
  });
  // La principal: levantar la suspensión si se puede; si no, editar, salvo que no haya ninguna otra acción.
  const levantar = deEstado.find(({ clave }) => clave === 'levantar_suspension');
  const principal = levantar ?? (deEstado.length > 0 ? editar : null);
  const secundarias = levantar
    ? [editar, ...deEstado.filter((accion) => accion !== levantar)]
    : principal
      ? deEstado
      : [editar];

  const conPedidos = transiciones.filter((transicion) => transicion !== 'levantar_suspension');
  const pedidosEnCurso =
    estado === 'pendiente' || conPedidos.length === 0
      ? null
      : conPedidos.includes('suspender')
        ? TEXTOS.pedidosEnCursoAlSuspenderODarDeBaja
        : TEXTOS.pedidosEnCursoAlDarDeBaja;

  return (
    <>
      {pedidosEnCurso ? (
        <PorConfirmar etiqueta={pedidosEnCurso} dd={DECISIONES.pedidosEnCurso} testID="pedidos-en-curso" />
      ) : null}
      {principal ? (
        <Boton ocupado={enCurso === principal.clave} deshabilitado={enCurso !== null} onPress={principal.alTocar}>
          {principal.texto}
        </Boton>
      ) : null}
      <View style={estilos.secundarias}>
        {secundarias.map(({ clave, texto, alTocar }) => (
          <View key={clave} style={estilos.secundaria}>
            <Boton
              variante="secundario"
              ocupado={enCurso === clave}
              deshabilitado={enCurso !== null}
              onPress={alTocar}
            >
              {texto}
            </Boton>
          </View>
        ))}
      </View>
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
  cabecera: {
    gap: espaciado[3],
  },
  tarjeta: {
    paddingHorizontal: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  dato: {
    gap: espaciado[1],
    paddingVertical: espaciado[4],
  },
  // Los datos van separados por la línea del color de borde, como en la tarjeta del mock.
  datoSiguiente: {
    borderTopWidth: grosor.control,
    borderTopColor: color.semantico.borde,
  },
  etiqueta: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  valor: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  // Las secundarias, de dos en dos, cada una con la mitad del ancho; sola, a todo lo ancho.
  secundarias: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: espaciado[2],
  },
  secundaria: {
    flexGrow: 1,
    flexBasis: '40%',
  },
});
