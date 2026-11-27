// La ficha de un repartidor en la gestión de repartidores (F-D02; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D
// #2, "Alta, baja, suspensión y edición de socios repartidores"; :18, Módulo C #1, "El alta, validación y autorización
// del repartidor corresponden a EL CLIENTE"): se abre al tocarlo en la lista, por su id_publico. Muestra su nombre, su
// foto, su estado, su plaza, su teléfono, su correo y su tipo de vehículo (administracion-app-E19), y abajo solo los
// botones de las transiciones que el servidor dice que su estado permite ("transiciones"), además de "Editar":
//
// - pendiente: la plaza se elige en el Selector, con las plazas de GET /api/administracion/plazas; sin plaza elegida, el
//   botón del alta no se puede tocar y dice qué falta, y la app no lo manda (administracion-app-E20). Si las plazas no
//   llegan, el mensaje y "Intentar de nuevo", como la imagen 05;
// - activo: "Suspender" y "Dar de baja"; suspendido: "Levantar la suspensión" y "Dar de baja" (administracion-app-E21 y
//   E22);
// - de baja: ninguna acción de estado, porque ninguna sale de la baja (administracion-app-E22).
//
// Cada acción pide su confirmación con el diálogo del sistema y, al confirmarla, la ficha muestra el estado nuevo con
// sus botones. Si otra sesión ya lo cambió, el servidor responde 409: su mensaje va arriba y la ficha se vuelve a pedir
// (administracion-app-E12). Un repartidor que no existe responde 404 y la pantalla lo dice. Con un 401, el cliente de la
// administración ya volvió al inicio de sesión (administracion-app-E05). No muestra su posición (DD-077).
//
// La composición es la de las imágenes 15 a 19 y 63 a 65 de la Propuesta 06: la foto en un círculo junto al nombre (un
// título de sección), la insignia y la plaza; los datos en una tarjeta; y los botones fijos abajo, el principal entero y
// los secundarios en un renglón. De baja no hay imagen: "Editar" queda solo, como secundario, como en la lavandería de
// baja (imagen 11) y en el panel, que no esconde la edición; sin otro texto, que administracion-app-E22 no pide.
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Image, StyleSheet, Text, View } from 'react-native';

import type {
  RepartidorDeLaAdministracion,
  TransicionDelRepartidor,
} from '../../../src/administracion/repartidores/api';
import { InsigniaYPlaza } from '../../../src/administracion/repartidores/InsigniaYPlaza';
import { RUTA_DE_LOS_REPARTIDORES, rutaDeLaEdicionDelRepartidor } from '../../../src/administracion/repartidores/rutas';
import {
  darDeAltaEn,
  mensajeDeLosRepartidores,
  TEXTOS_DE_LOS_REPARTIDORES as TEXTOS,
} from '../../../src/administracion/repartidores/textos';
import {
  useFichaDelRepartidor,
  type AccionDeEstado,
  type FichaDelRepartidor,
} from '../../../src/administracion/repartidores/useFichaDelRepartidor';
import { useRegresar } from '../../../src/navegacion/regresar';
import { Boton } from '../../../src/piezas/Boton';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { Selector } from '../../../src/piezas/Selector';
import { TituloDeSeccion } from '../../../src/piezas/TituloDeSeccion';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../../src/tema/theme';

// El lado del círculo de la foto, con las medidas de tokens.json.
const LADO_DE_LA_FOTO = espaciado[8];

export default function FichaDelRepartidorEnLaAdministracion() {
  const router = useRouter();
  const regresar = useRegresar(RUTA_DE_LOS_REPARTIDORES);
  const { id } = useLocalSearchParams<{ id: string }>();
  const ficha = useFichaDelRepartidor(id);
  const [plazaElegida, setPlazaElegida] = useState<string | null>(null);
  const repartidor = ficha.ficha.datos;
  const falla = ficha.ficha.falla;

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS.tituloDeLaFicha, alRegresar: regresar }}
      acciones={
        repartidor ? (
          <AccionesDeLaFicha
            repartidor={repartidor}
            ficha={ficha}
            plazaElegida={plazaElegida}
            alEditar={() => router.push(rutaDeLaEdicionDelRepartidor(repartidor.id_publico))}
          />
        ) : undefined
      }
    >
      <MensajeDeError texto={ficha.aviso} />
      {repartidor === null && falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoLaFicha}</Text>
        </View>
      ) : null}
      {falla ? <MensajeDeError texto={mensajeDeLosRepartidores(falla)} /> : null}
      {falla && falla.motivo !== 'no-existe' ? (
        <Boton variante="secundario" onPress={ficha.ficha.consultarDeNuevo}>
          {TEXTOS.intentarDeNuevo}
        </Boton>
      ) : null}
      {repartidor ? (
        <>
          <View style={estilos.cabecera}>
            <FotoDelRepartidor foto={repartidor.foto} />
            <View style={estilos.presentacion}>
              <TituloDeSeccion>{repartidor.nombre ?? TEXTOS.sinNombre}</TituloDeSeccion>
              <InsigniaYPlaza estado={repartidor.estado} plaza={repartidor.plaza} />
            </View>
          </View>
          <View style={estilos.tarjeta} testID="datos-del-repartidor">
            <Dato etiqueta={TEXTOS.telefono} valor={repartidor.telefono} />
            <Dato etiqueta={TEXTOS.correo} valor={repartidor.correo} />
            <Dato etiqueta={TEXTOS.tipoDeVehiculo} valor={repartidor.tipo_vehiculo} ultimo />
          </View>
          {repartidor.transiciones.includes('dar_de_alta') ? (
            <>
              <Selector
                nombre="plaza"
                etiqueta={TEXTOS.plazaParaElAlta}
                valor={plazaElegida ?? ''}
                marcador={TEXTOS.eligeLaPlaza}
                etiquetaDeLaBusqueda={TEXTOS.buscarPlaza}
                opciones={(ficha.plazas.datos ?? []).map(({ nombre }) => ({ clave: nombre, texto: nombre }))}
                elegida={plazaElegida}
                alElegir={({ clave }) => setPlazaElegida(clave)}
                error={ficha.errorDeLaPlaza}
                deshabilitado={ficha.plazas.datos === null || ficha.enviando !== null}
              />
              {ficha.plazas.falla ? (
                <>
                  <MensajeDeError texto={mensajeDeLosRepartidores(ficha.plazas.falla)} />
                  <Boton variante="secundario" onPress={ficha.plazas.consultarDeNuevo}>
                    {TEXTOS.intentarDeNuevo}
                  </Boton>
                </>
              ) : null}
              <Parrafo alineacion="inicio">{TEXTOS.validacionFuera}</Parrafo>
              {repartidor.transiciones.includes('dar_de_baja') ? (
                <Parrafo alineacion="inicio">{TEXTOS.siNoLoAutorizas}</Parrafo>
              ) : null}
            </>
          ) : null}
          {repartidor.transiciones.includes('levantar_suspension') ? (
            <Parrafo alineacion="inicio">{TEXTOS.alLevantarLaSuspension}</Parrafo>
          ) : null}
        </>
      ) : null}
    </Pantalla>
  );
}

// La foto en un círculo, desde su URL firmada, que se anuncia "Foto de perfil" (imágenes 15 a 19). Sin foto, el círculo
// queda vacío y el lector de pantalla lo salta, como la foto de perfil del resto de la app
// (src/perfil-usuario/FotoDePerfil.tsx:16): la silueta de las imágenes es un SVG, y la app no tiene librería para
// dibujarlo ni un token semántico con su color (#98A1AC; vault/design-system/design-system.md, decisión 3).
function FotoDelRepartidor({ foto }: { foto: RepartidorDeLaAdministracion['foto'] }) {
  if (!foto) {
    return (
      <View style={estilos.foto} accessibilityElementsHidden importantForAccessibility="no-hide-descendants" />
    );
  }
  return (
    <Image
      accessible
      accessibilityRole="image"
      accessibilityLabel={TEXTOS.fotoDePerfil}
      source={{ uri: foto.url }}
      resizeMode="cover"
      style={estilos.foto}
    />
  );
}

// Un dato de la tarjeta, que se lee en un solo anuncio: "Teléfono: 618 123 4567". Un dato que no está no ocupa lugar.
function Dato({ etiqueta, valor, ultimo = false }: { etiqueta: string; valor: string | null; ultimo?: boolean }) {
  if (valor === null) {
    return null;
  }
  return (
    <View accessible accessibilityLabel={`${etiqueta}: ${valor}`} style={[estilos.dato, ultimo ? null : estilos.conLinea]}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{etiqueta}</Text>
      <Text style={[estilos.valor, letra('cuerpo', 'regular')]}>{valor}</Text>
    </View>
  );
}

interface BotonDeLaFicha {
  clave: string;
  texto: string;
  alTocar: () => void;
  accion?: AccionDeEstado;
  deshabilitado?: boolean;
}

// Los botones fijos abajo: el principal entero y los secundarios en un renglón. El principal es la acción que espera el
// estado (el alta del pendiente, levantar la suspensión del suspendido) o, si no hay, "Editar"; de baja, "Editar" queda
// solo, como secundario. Mientras una acción va, ninguno se puede tocar.
function AccionesDeLaFicha({
  repartidor,
  ficha,
  plazaElegida,
  alEditar,
}: {
  repartidor: RepartidorDeLaAdministracion;
  ficha: FichaDelRepartidor;
  plazaElegida: string | null;
  alEditar: () => void;
}) {
  const permite = (transicion: TransicionDelRepartidor) => repartidor.transiciones.includes(transicion);
  const editar: BotonDeLaFicha = { clave: 'editar', texto: TEXTOS.editar, alTocar: alEditar };
  const alta: BotonDeLaFicha | null = permite('dar_de_alta')
    ? {
        clave: 'alta',
        accion: 'alta',
        texto: plazaElegida ? darDeAltaEn(plazaElegida) : TEXTOS.eligeUnaPlaza,
        deshabilitado: plazaElegida === null,
        alTocar: () => {
          if (plazaElegida) {
            ficha.darDeAlta(plazaElegida);
          }
        },
      }
    : null;
  const levantamiento: BotonDeLaFicha | null = permite('levantar_suspension')
    ? {
        clave: 'levantamiento',
        accion: 'levantamiento',
        texto: TEXTOS.levantarLaSuspension,
        alTocar: ficha.levantarLaSuspension,
      }
    : null;
  const deEstado: BotonDeLaFicha[] = [];
  if (permite('suspender')) {
    deEstado.push({ clave: 'suspension', accion: 'suspension', texto: TEXTOS.suspender, alTocar: ficha.suspender });
  }
  if (permite('dar_de_baja')) {
    deEstado.push({ clave: 'baja', accion: 'baja', texto: TEXTOS.darDeBaja, alTocar: ficha.darDeBaja });
  }

  const esperado = alta ?? levantamiento;
  const principal = esperado ?? (deEstado.length > 0 ? editar : null);
  const secundarios = esperado ? [editar, ...deEstado] : principal ? deEstado : [editar];
  const ocupada = ficha.enviando !== null;

  return (
    <>
      {principal ? (
        <Boton
          ocupado={principal.accion !== undefined && ficha.enviando === principal.accion}
          deshabilitado={ocupada || principal.deshabilitado}
          onPress={principal.alTocar}
        >
          {principal.texto}
        </Boton>
      ) : null}
      <View style={estilos.secundarios}>
        {secundarios.map((boton) => (
          <View key={boton.clave} style={estilos.secundario}>
            <Boton
              variante="secundario"
              ocupado={boton.accion !== undefined && ficha.enviando === boton.accion}
              deshabilitado={ocupada}
              onPress={boton.alTocar}
            >
              {boton.texto}
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
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[4],
  },
  presentacion: {
    flex: 1,
    gap: espaciado[2],
  },
  foto: {
    width: LADO_DE_LA_FOTO,
    height: LADO_DE_LA_FOTO,
    borderRadius: radio.pildora,
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    backgroundColor: color.semantico.superficie_hundida,
  },
  // La tarjeta de los datos, con un renglón por dato.
  tarjeta: {
    paddingHorizontal: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  // La etiqueta a la izquierda y el valor a la derecha; si no caben, el valor baja al siguiente renglón.
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
  secundarios: {
    flexDirection: 'row',
    gap: espaciado[2],
  },
  secundario: {
    flex: 1,
  },
});
