// Los campos de la lavandería, los mismos en su registro (alta-lavanderias-E03) y en su perfil y horarios
// (alta-lavanderias-E19), con los cinco datos del Módulo B #1 (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3): el nombre;
// la dirección, con el municipio de la lista del territorio (GET /api/catalogos/municipios, con la pieza Selector, como
// la dirección del pedido, direcciones-E17); la foto, tomada o elegida y comprimida (src/alta-lavanderias/foto.ts); los
// servicios, cada uno con su precio por kilo; y las ventanas de una hora de cada día de la semana. Cada error del
// servidor va junto a su campo; los de la dirección, de los servicios y de las ventanas que no son de un solo campo, al
// final de su sección.
//
// En el perfil y horarios (con `edicion`) suma la descripción del perfil, opcional, y en lugar de la foto única muestra
// la lista de fotos del perfil en su orden (alta-lavanderias-E26 a E30; acta-2026-11-20-modelo-y-pasarela.md,
// decisión 44): cada una con "Quitar" si hay más de una y "Mover antes" si no es la primera, y la toma o la elección de
// una foto nueva, comprimida como la del registro, que va al final, mientras haya menos que el máximo que da el
// servidor. El registro conserva su foto única y no pide descripción (Módulo B #1).
//
// La administración edita cualquier lavandería con este mismo formulario, el del perfil y horarios
// (administracion-app-E14): pone sus textos, que hablan de la lavandería y no de tú, y lo que la pantalla dice en lugar
// del lavado en seco, al final de los servicios. Sin ellos, el formulario es el del socio, como siempre.
//
// La composición sigue las imágenes b01-registro y b06-perfil de la Propuesta 04, por secciones. Ni el registro ni el
// perfil tienen componente en el design system (design.md del cambio, pregunta abierta 4): se arman con las piezas y los
// tokens (ADR-0013), como las demás pantallas. Del mock no entran el cupo por ventana ("Sin cupo", DD-099 y
// alta-lavanderias-E23) ni el horario de atención aparte de las ventanas (DD-078 y alta-lavanderias-E24).
import { useState, type Dispatch, type ReactNode, type SetStateAction } from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';

import { ventanaEnTexto } from '../agenda/textos';
import { municipioEnTexto } from '../direcciones/textos';
import { LARGO_DEL_CODIGO_POSTAL } from '../direcciones/useColonias';
import { useMunicipios } from '../direcciones/useMunicipios';
import { Boton } from '../piezas/Boton';
import { CampoDeTexto } from '../piezas/CampoDeTexto';
import { Interruptor } from '../piezas/Interruptor';
import { Parrafo } from '../piezas/Parrafo';
import { Selector } from '../piezas/Selector';
import { TituloDeSeccion } from '../piezas/TituloDeSeccion';
import { letra } from '../tema/letra';
import { color, espaciado, radio, tipografia } from '../tema/theme';
import { elegirFoto, tomarFoto, type ResultadoDeLaFoto } from './foto';
import {
  campoDelPrecio,
  cambiarVentana,
  claveDeVentana,
  conFotoNueva,
  conLaFotoAntes,
  DIAS_DE_LA_SEMANA,
  HORAS_DEL_DIA,
  nombreDelDia,
  primerDiaConVentanas,
  SERVICIOS_QUE_SE_REGISTRAN,
  sinLaFoto,
  ventanasDelDia,
  type Formulario,
} from './formulario';
import {
  cuantasVentanas,
  fotoDeLaLista,
  instruccionDeLasFotos,
  moverAntesLaFoto,
  precioPorKiloDe,
  quitarLaFoto,
  TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO as TEXTOS,
} from './textos';

interface Propiedades {
  formulario: Formulario;
  alCambiar: Dispatch<SetStateAction<Formulario>>;
  /** Los errores del servidor, por campo (src/alta-lavanderias/formulario.ts, erroresDelEnvio). */
  errores: Record<string, string>;
  /**
   * Solo en el perfil y horarios: la descripción y la lista de fotos, con el máximo de fotos que da el servidor. Sin
   * ella, es el registro, con su foto única.
   */
  edicion?: { maximoDeFotos: number };
  /** Los textos que le hablan a quien edita, si no es el socio (TextosDeQuienEdita, abajo). Sin ellos, los del socio. */
  textos?: TextosDeQuienEdita;
  /**
   * Lo que va al final de la sección de servicios y precios, antes de su aviso: en la edición de la administración, el
   * lavado en seco en espera de DD-010 (administracion-app-E14). Sin él, nada.
   */
  alFinalDeLosServicios?: ReactNode;
}

// Las partes de la dirección que se escriben, con su nombre en la API.
const PARTES_DE_LA_DIRECCION = [
  { parte: 'calle', campo: 'direccion.calle', etiqueta: TEXTOS.calle },
  { parte: 'numero', campo: 'direccion.numero', etiqueta: TEXTOS.numero },
  { parte: 'colonia', campo: 'direccion.colonia', etiqueta: TEXTOS.colonia },
] as const;

// La foto se ve en la proporción de la cámara de los teléfonos, a todo lo ancho.
const PROPORCION_DE_LA_FOTO = 4 / 3;

export function FormularioDeLaLavanderia({
  formulario,
  alCambiar,
  errores,
  edicion,
  textos = TEXTOS_DEL_SOCIO,
  alFinalDeLosServicios = null,
}: Propiedades) {
  const municipios = useMunicipios();
  // El día cuyas ventanas se ven: al abrir, el primero que tiene alguna.
  const [dia, setDia] = useState(() => primerDiaConVentanas(formulario.ventanas));
  // Lo que pasó con la foto en el teléfono: se prepara, o no se pudo.
  const [preparando, setPreparando] = useState<'camara' | 'galeria' | null>(null);
  const [errorDeLaFoto, setErrorDeLaFoto] = useState<string | null>(null);

  function cambiar<Campo extends keyof Formulario>(campo: Campo, valor: Formulario[Campo]) {
    alCambiar((anterior) => ({ ...anterior, [campo]: valor }));
  }

  async function ponerFoto(origen: 'camara' | 'galeria', obtener: () => Promise<ResultadoDeLaFoto>) {
    if (preparando) {
      return;
    }
    setPreparando(origen);
    const resultado = await obtener();
    setPreparando(null);
    if (resultado.ok && edicion) {
      setErrorDeLaFoto(null);
      // Al final de la lista, y nunca más allá del máximo (alta-lavanderias-E27 y E30).
      alCambiar((anterior) =>
        anterior.fotos.length < edicion.maximoDeFotos
          ? { ...anterior, fotos: conFotoNueva(anterior.fotos, resultado.foto.uri) }
          : anterior,
      );
    } else if (resultado.ok) {
      setErrorDeLaFoto(null);
      cambiar('foto', { uri: resultado.foto.uri, nueva: true });
    } else if (resultado.motivo === 'sin-permiso') {
      setErrorDeLaFoto(TEXTOS.sinPermisoDeLaCamara);
    } else if (resultado.motivo === 'no-se-pudo') {
      setErrorDeLaFoto(TEXTOS.fotoNoPreparada);
    }
  }

  // Solo números, hasta cinco (direcciones-E20).
  function cambiarElCodigoPostal(texto: string) {
    cambiar('codigoPostal', texto.replace(/\D/g, '').slice(0, LARGO_DEL_CODIGO_POSTAL));
  }

  const { estado: listaDeMunicipios, reintentar } = municipios;
  const sinMunicipios = listaDeMunicipios.fase === 'no-disponibles';

  // Tomar o elegir la foto: la única del registro, o una más de la lista del perfil.
  const botonesDeLaFoto = (
    <>
      <Boton
        variante="secundario"
        ocupado={preparando === 'camara'}
        deshabilitado={preparando !== null}
        onPress={() => void ponerFoto('camara', tomarFoto)}
      >
        {TEXTOS.tomarFoto}
      </Boton>
      <Boton
        variante="secundario"
        ocupado={preparando === 'galeria'}
        deshabilitado={preparando !== null}
        onPress={() => void ponerFoto('galeria', elegirFoto)}
      >
        {TEXTOS.elegirDeLaGaleria}
      </Boton>
    </>
  );

  return (
    <View style={estilos.formulario}>
      <View style={estilos.seccion}>
        <TituloDeSeccion>{textos.datos}</TituloDeSeccion>
        <CampoDeTexto
          nombre="nombre"
          etiqueta={TEXTOS.nombre}
          valor={formulario.nombre}
          alCambiar={(texto) => cambiar('nombre', texto)}
          error={errores.nombre}
        />
        {edicion ? (
          <CampoDeTexto
            nombre="descripcion"
            etiqueta={TEXTOS.descripcion}
            valor={formulario.descripcion}
            alCambiar={(texto) => cambiar('descripcion', texto)}
            error={errores.descripcion}
            multilinea
          />
        ) : null}
      </View>

      <View style={estilos.seccion}>
        <TituloDeSeccion>{TEXTOS.direccion}</TituloDeSeccion>
        {PARTES_DE_LA_DIRECCION.map(({ parte, campo, etiqueta }) => (
          <CampoDeTexto
            key={parte}
            nombre={campo}
            etiqueta={etiqueta}
            valor={formulario[parte]}
            alCambiar={(texto) => cambiar(parte, texto)}
            error={errores[campo]}
          />
        ))}
        <CampoDeTexto
          nombre="direccion.codigo_postal"
          tipo="codigo-postal"
          etiqueta={TEXTOS.codigoPostal}
          valor={formulario.codigoPostal}
          alCambiar={cambiarElCodigoPostal}
          error={errores['direccion.codigo_postal']}
        />
        <Selector
          nombre="direccion.municipio"
          etiqueta={TEXTOS.municipio}
          valor={formulario.municipio?.texto ?? ''}
          marcador={listaDeMunicipios.fase === 'cargando' ? TEXTOS.cargandoMunicipios : TEXTOS.elegirMunicipio}
          etiquetaDeLaBusqueda={TEXTOS.buscarMunicipio}
          opciones={
            listaDeMunicipios.fase === 'listos'
              ? listaDeMunicipios.municipios.map((uno) => ({ clave: uno.codigo, texto: municipioEnTexto(uno) }))
              : []
          }
          elegida={formulario.municipio?.codigo ?? null}
          alElegir={({ clave, texto }) => cambiar('municipio', { codigo: clave, texto })}
          // El error del servidor manda; sin él, el aviso de la lista que no llegó.
          error={errores['direccion.municipio'] ?? (sinMunicipios ? TEXTOS.municipiosNoDisponibles : undefined)}
          deshabilitado={listaDeMunicipios.fase !== 'listos'}
        />
        {sinMunicipios ? (
          <Boton variante="secundario" onPress={reintentar}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        ) : null}
        <AvisoDeLaSeccion testID="campo-direccion" texto={errores.direccion} />
      </View>

      {edicion ? (
        <View style={estilos.seccion} testID="campo-fotos">
          <TituloDeSeccion>{TEXTOS.fotos}</TituloDeSeccion>
          <Parrafo alineacion="inicio">{textos.fotos(edicion.maximoDeFotos)}</Parrafo>
          {formulario.fotos.map((foto, lugar) => (
            <View key={`${lugar}-${foto.uri}`} testID={`foto-${lugar + 1}`} style={estilos.fotoDeLaLista}>
              <Image
                accessible
                accessibilityLabel={fotoDeLaLista(lugar + 1)}
                source={{ uri: foto.uri }}
                style={estilos.foto}
              />
              <View style={estilos.accionesDeLaFoto}>
                {lugar > 0 ? (
                  <View style={estilos.accionDeLaFoto}>
                    <Boton
                      variante="secundario"
                      paraLeer={moverAntesLaFoto(lugar + 1)}
                      onPress={() => alCambiar((anterior) => ({ ...anterior, fotos: conLaFotoAntes(anterior.fotos, lugar) }))}
                    >
                      {TEXTOS.moverAntes}
                    </Boton>
                  </View>
                ) : null}
                {/* La última no se quita: el registro pide una (alta-lavanderias-E28). */}
                {formulario.fotos.length > 1 ? (
                  <View style={estilos.accionDeLaFoto}>
                    <Boton
                      variante="secundario"
                      paraLeer={quitarLaFoto(lugar + 1)}
                      onPress={() => alCambiar((anterior) => ({ ...anterior, fotos: sinLaFoto(anterior.fotos, lugar) }))}
                    >
                      {TEXTOS.quitar}
                    </Boton>
                  </View>
                ) : null}
              </View>
            </View>
          ))}
          {/* Con el máximo, no se ofrece agregar otra (alta-lavanderias-E30). */}
          {formulario.fotos.length < edicion.maximoDeFotos ? (
            <>
              <Text style={[estilos.subtitulo, letra('cuerpo', 'semifuerte')]}>{TEXTOS.agregarFoto}</Text>
              {botonesDeLaFoto}
            </>
          ) : null}
          <AvisoDeLaSeccion texto={errorDeLaFoto ?? errores.fotos} />
        </View>
      ) : (
        <View style={estilos.seccion} testID="campo-foto">
          <TituloDeSeccion>{TEXTOS.foto}</TituloDeSeccion>
          {formulario.foto ? (
            <Image
              accessible
              accessibilityLabel={TEXTOS.fotoDeLaLavanderia}
              source={{ uri: formulario.foto.uri }}
              style={estilos.foto}
            />
          ) : null}
          {botonesDeLaFoto}
          <AvisoDeLaSeccion texto={errorDeLaFoto ?? errores.foto} />
        </View>
      )}

      <View style={estilos.seccion}>
        <TituloDeSeccion>{TEXTOS.serviciosYPrecios}</TituloDeSeccion>
        {SERVICIOS_QUE_SE_REGISTRAN.map(({ tipo, nombre }) => {
          const servicio = formulario.servicios[tipo];
          const cambiarServicio = (cambio: Partial<typeof servicio>) =>
            alCambiar((anterior) => ({
              ...anterior,
              servicios: { ...anterior.servicios, [tipo]: { ...anterior.servicios[tipo], ...cambio } },
            }));
          return (
            <View key={tipo} style={estilos.servicio}>
              <Interruptor etiqueta={nombre} valor={servicio.ofrece} alCambiar={(ofrece) => cambiarServicio({ ofrece })} />
              {servicio.ofrece ? (
                <CampoDeTexto
                  nombre={campoDelPrecio(tipo)}
                  tipo="precio"
                  etiqueta={precioPorKiloDe(nombre)}
                  valor={servicio.precio}
                  alCambiar={(precio) => cambiarServicio({ precio })}
                  error={errores[campoDelPrecio(tipo)]}
                />
              ) : null}
            </View>
          );
        })}
        {alFinalDeLosServicios}
        <AvisoDeLaSeccion testID="campo-servicios" texto={errores.servicios} />
      </View>

      <View style={estilos.seccion}>
        <TituloDeSeccion>{TEXTOS.ventanas}</TituloDeSeccion>
        <Parrafo alineacion="inicio">{textos.ventanas}</Parrafo>
        <Selector
          nombre="dia"
          etiqueta={TEXTOS.dia}
          valor={nombreDelDia(dia)}
          marcador={TEXTOS.dia}
          etiquetaDeLaBusqueda={TEXTOS.buscarDia}
          opciones={DIAS_DE_LA_SEMANA.map((uno) => ({
            clave: String(uno.dia),
            texto: uno.nombre,
            detalle: cuantasVentanas(ventanasDelDia(formulario.ventanas, uno.dia)),
          }))}
          elegida={String(dia)}
          alElegir={({ clave }) => setDia(Number(clave))}
        />
        {HORAS_DEL_DIA.map((hora) => {
          const clave = claveDeVentana(dia, hora.hora_inicio);
          return (
            <Interruptor
              key={clave}
              etiqueta={ventanaEnTexto(hora)}
              valor={formulario.ventanas.includes(clave)}
              alCambiar={(marcada) =>
                alCambiar((anterior) => ({ ...anterior, ventanas: cambiarVentana(anterior.ventanas, clave, marcada) }))
              }
            />
          );
        })}
        <AvisoDeLaSeccion testID="campo-ventanas" texto={errores.ventanas} />
      </View>
    </View>
  );
}

/**
 * Los textos del formulario que cambian según quién edita la lavandería. El socio edita la suya y el formulario le habla
 * de tú ("Tu lavandería", "Puedes tener hasta…", "las horas en que puedes recolectar"); la administración edita
 * cualquiera con este mismo formulario y pone los suyos, que hablan de la lavandería (administracion-app-E14; imágenes
 * 12, 60 y 61 de la Propuesta 06).
 */
export interface TextosDeQuienEdita {
  /** El título de la sección del nombre y la descripción. */
  datos: string;
  /** Lo que dice la sección de fotos del perfil, con el máximo que da el servidor. */
  fotos: (maximo: number) => string;
  /** Lo que dice la sección de las ventanas de disponibilidad. */
  ventanas: string;
}

// Los del socio, que edita su propia lavandería.
const TEXTOS_DEL_SOCIO: TextosDeQuienEdita = {
  datos: TEXTOS.tuLavanderia,
  fotos: instruccionDeLasFotos,
  ventanas: TEXTOS.instruccionDeLasVentanas,
};

// El aviso de una sección, al final de ella: lo que no es de un solo campo. Sin texto, no ocupa lugar.
function AvisoDeLaSeccion({ testID, texto }: { testID?: string; texto?: string }) {
  return (
    <View testID={testID}>
      {texto ? (
        <Text role="alert" accessibilityLiveRegion="polite" style={[estilos.aviso, letra('cuerpo', 'regular')]}>
          {texto}
        </Text>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  formulario: {
    gap: espaciado[6],
  },
  seccion: {
    gap: espaciado[3],
  },
  servicio: {
    gap: espaciado[2],
  },
  foto: {
    width: '100%',
    aspectRatio: PROPORCION_DE_LA_FOTO,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie_hundida,
  },
  fotoDeLaLista: {
    gap: espaciado[2],
  },
  // "Mover antes" y "Quitar", lado a lado, debajo de cada foto.
  accionesDeLaFoto: {
    flexDirection: 'row',
    gap: espaciado[2],
  },
  accionDeLaFoto: {
    flex: 1,
  },
  subtitulo: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  aviso: {
    color: color.estado.error,
    fontSize: tipografia.escala.menor,
  },
});
