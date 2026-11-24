// La dirección nueva del pedido (openspec/changes/direcciones): se escribe o se marca en el mapa. La abre "Agregar otra
// dirección" en el paso de la dirección, para la recogida o para la entrega (parámetro "para").
//
// - Escrita (direcciones-E01 a E03): calle, número, número interior opcional, colonia, municipio y referencias
//   opcionales (DD-135, punto 6). El servidor la convierte en un punto una sola vez, al guardarla (ADR-0009, decisión
//   4); la app no manda punto. Si falta la colonia, el servidor lo dice y la app lo señala junto al campo. Si el
//   servidor no la encuentra, o el servicio que la busca no responde, la app lo dice y ofrece marcarla en el mapa, sin
//   perder lo escrito.
// - El municipio, en las dos formas, se elige de la lista del territorio (direcciones-E17; C-238), que la app pide una
//   vez por sesión, con la pieza Selector (decisión 29 del acta del 2026-11-20). La app manda su código. No hay campo
//   de texto libre: el servidor rechaza un municipio que no es del catálogo, y su error va junto al campo
//   (direcciones-E18). Sin lista, lo dice junto al campo y deja intentarlo de nuevo; la forma del mapa sigue sirviendo,
//   porque ahí el municipio es opcional.
// - El código postal, en las dos formas, antes de la colonia: se pide y no se exige, y son cinco números (direcciones-E20;
//   decisión 29 del acta del 2026-11-20). Con los cinco, la app pide sus colonias y las ofrece en la pieza Selector, con
//   "No aparece mi colonia" para escribirla a mano. Elegir una escribe su nombre en la colonia y, si la persona no había
//   elegido municipio, elige el de esa colonia (direcciones-E19). Sin colonias para ese código postal, lo dice y la
//   colonia se escribe (direcciones-E20). La lista ayuda y no llena sola la colonia, que sigue obligatoria
//   (direcciones-E02). Si la persona cambia el código postal, la colonia que eligió de la lista anterior se quita.
// - En el mapa (direcciones-E04 y E05): el mapa nativo de cada plataforma se abre en la ubicación del teléfono, la
//   persona lo mueve bajo el pin y escribe la colonia, obligatoria, y las demás señas, opcionales. Se guarda con el
//   punto del pin, sin geocodificar. Sin ubicación, o en un Android sin la llave de Google Maps, no hay mapa: lo dice y
//   deja escribir la dirección.
// - En las dos, "Guardar como favorita" pide su nombre, y la favorita aparece después en la lista de favoritas
//   (direcciones-E06). Sin marcarla, la dirección sirve para este pedido y no entra a la lista (direcciones-E08).
// - Al guardarla, la dirección que devolvió el servidor, con su punto, queda en el pedido en curso para la recogida o
//   para la entrega, y la app vuelve al paso de la dirección.
//
// La composición es la del tablero "Dirección nueva" de la Propuesta 5 corregida (C-233) y las imágenes
// a08-direccion-nueva y a08-direccion-mapa de la Propuesta 04, con el código postal que agregó C-238. Del mock no entran
// el autocompletado de la calle (direcciones-E21 a E23, bloqueados por DD-137) ni la calle sugerida desde el pin (BR-12
// y ADR-0009: sin geocodificación inversa).
//
// Sin conexión o con el servidor caído, sin escenario propio: los mensajes generales de la app.
import { Redirect, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import type { Punto } from '../../src/cercanas/api';
import { hayMapaNativo } from '../../src/cercanas/mapa';
import {
  guardarDireccion,
  type ColoniaDelCatalogo,
  type DireccionNueva,
  type FallaDelAlta,
  type SenasDeLaDireccion,
} from '../../src/direcciones/api';
import { MapaParaMarcar } from '../../src/direcciones/MapaParaMarcar';
import {
  mensajeDelAlta,
  municipioEnTexto,
  ofreceElMapa,
  TEXTOS_DE_DIRECCIONES,
} from '../../src/direcciones/textos';
import { LARGO_DEL_CODIGO_POSTAL, useColonias } from '../../src/direcciones/useColonias';
import { useMunicipios } from '../../src/direcciones/useMunicipios';
import { usePuntoDeInicio } from '../../src/direcciones/usePuntoDeInicio';
import { useRegresar } from '../../src/navegacion/regresar';
import { agregarDireccion, usePedidoEnCurso, type ParaQueEsLaDireccion } from '../../src/pedido/pedidoEnCurso';
import { RUTA_DE_LA_DIRECCION } from '../../src/pedido/rutas';
import { Boton } from '../../src/piezas/Boton';
import { CampoDeTexto } from '../../src/piezas/CampoDeTexto';
import { Enlace } from '../../src/piezas/Enlace';
import { Interruptor } from '../../src/piezas/Interruptor';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Pestanas } from '../../src/piezas/Pestanas';
import { anunciar, Selector, type OpcionDelSelector } from '../../src/piezas/Selector';
import { useSesion } from '../../src/sesion/ProveedorDeSesion';
import { color, espaciado, tipografia } from '../../src/tema/theme';

type Forma = 'escrita' | 'mapa';

const FORMAS = [
  { clave: 'escrita', texto: TEXTOS_DE_DIRECCIONES.escribirla },
  { clave: 'mapa', texto: TEXTOS_DE_DIRECCIONES.enElMapa },
] as const;

// Las señas que se escriben. El municipio se elige de la lista: se guarda aparte, con el texto que muestra el campo.
type SenasEscritas = Omit<SenasDeLaDireccion, 'municipio'>;

const SIN_SENAS: SenasEscritas = {
  calle: '',
  numero_exterior: '',
  numero_interior: '',
  codigo_postal: '',
  colonia: '',
  referencias: '',
};

/**
 * El municipio elegido: el código que viaja y el texto que muestra el campo. porLaColonia: lo eligió la app con la
 * colonia, no la persona, así que otra colonia lo cambia (direcciones-E19).
 */
interface MunicipioElegido {
  codigo: string;
  texto: string;
  porLaColonia: boolean;
}

/**
 * Cómo se captura la colonia cuando su código postal trae colonias: de la lista, a mano, o según lo escrito (con la
 * colonia vacía, la lista; con algo escrito antes, a mano, para no esconderlo).
 */
type FormaDeLaColonia = 'segun-lo-escrito' | 'de-la-lista' | 'a-mano';

// Los campos que la pantalla muestra, con el municipio y el nombre de la favorita: el error de cualquiera de ellos va
// junto a él, no arriba del botón.
const CAMPOS_VISIBLES = [...Object.keys(SIN_SENAS), 'municipio', 'alias'];

// La etiqueta de cada seña en cada forma: en el mapa, solo la colonia es obligatoria (direcciones-E04).
const ETIQUETAS: Record<Forma, Record<keyof SenasDeLaDireccion, string>> = {
  escrita: {
    calle: TEXTOS_DE_DIRECCIONES.calle,
    numero_exterior: TEXTOS_DE_DIRECCIONES.numero,
    numero_interior: TEXTOS_DE_DIRECCIONES.numeroInterior,
    codigo_postal: TEXTOS_DE_DIRECCIONES.codigoPostal,
    colonia: TEXTOS_DE_DIRECCIONES.colonia,
    municipio: TEXTOS_DE_DIRECCIONES.municipio,
    referencias: TEXTOS_DE_DIRECCIONES.referencias,
  },
  mapa: {
    calle: TEXTOS_DE_DIRECCIONES.calleEnElMapa,
    numero_exterior: TEXTOS_DE_DIRECCIONES.numeroEnElMapa,
    numero_interior: TEXTOS_DE_DIRECCIONES.numeroInterior,
    codigo_postal: TEXTOS_DE_DIRECCIONES.codigoPostal,
    colonia: TEXTOS_DE_DIRECCIONES.colonia,
    municipio: TEXTOS_DE_DIRECCIONES.municipioEnElMapa,
    referencias: TEXTOS_DE_DIRECCIONES.referencias,
  },
};

export default function PantallaDeDireccionNueva() {
  const { token } = useSesion();
  const pedido = usePedidoEnCurso();
  const regresar = useRegresar(RUTA_DE_LA_DIRECCION);
  const { para: paraDelEnlace } = useLocalSearchParams<{ para?: string }>();
  const para: ParaQueEsLaDireccion = paraDelEnlace === 'entrega' ? 'entrega' : 'recogida';

  const [forma, setForma] = useState<Forma>('escrita');
  const [senas, setSenas] = useState<SenasEscritas>(SIN_SENAS);
  const [municipio, setMunicipio] = useState<MunicipioElegido | null>(null);
  const municipios = useMunicipios();
  const colonias = useColonias(senas.codigo_postal);
  // La clave de la colonia elegida de la lista; null si se escribió a mano o no se eligió.
  const [coloniaDeLaLista, setColoniaDeLaLista] = useState<string | null>(null);
  const [formaDeLaColonia, setFormaDeLaColonia] = useState<FormaDeLaColonia>('segun-lo-escrito');
  const [esFavorita, setEsFavorita] = useState(false);
  const [alias, setAlias] = useState('');
  const [falla, setFalla] = useState<FallaDelAlta | null>(null);
  const [guardando, setGuardando] = useState(false);
  // La ubicación se lee la primera vez que se abre el mapa, no antes.
  const [mapaAbierto, setMapaAbierto] = useState(false);
  const inicio = usePuntoDeInicio(mapaAbierto);
  // El punto bajo el pin, si la persona ya movió el mapa; si no, el de inicio.
  const [pinMovido, setPinMovido] = useState<Punto | null>(null);

  if (!pedido) {
    return <Redirect href="/inicio" />;
  }

  const pin = pinMovido ?? (inicio.fase === 'listo' ? inicio.punto : null);
  // En el mapa, sin mapa o sin punto no hay qué guardar.
  const sePuedeGuardar = forma === 'escrita' || (pin !== null && hayMapaNativo());
  const errores = falla?.motivo === 'invalido' ? falla.errores : {};

  function cambiarForma(siguiente: Forma) {
    setForma(siguiente);
    setFalla(null);
    if (siguiente === 'mapa') {
      setMapaAbierto(true);
    }
  }

  function campo(nombre: keyof SenasEscritas, multilinea = false) {
    return (
      <CampoDeTexto
        nombre={nombre}
        etiqueta={ETIQUETAS[forma][nombre]}
        valor={senas[nombre]}
        alCambiar={(texto) => setSenas((anteriores) => ({ ...anteriores, [nombre]: texto }))}
        error={errores[nombre]?.[0]}
        multilinea={multilinea}
      />
    );
  }

  // Solo números, hasta cinco. Si cambia, la colonia elegida de la lista del código postal anterior se quita.
  function cambiarElCodigoPostal(texto: string) {
    const codigo = texto.replace(/\D/g, '').slice(0, LARGO_DEL_CODIGO_POSTAL);
    const cambio = codigo !== senas.codigo_postal;
    const quitarLaColonia = cambio && coloniaDeLaLista !== null;
    // Aunque no cambie, se vuelve a dibujar: así el campo borra lo que no era número.
    setSenas((anteriores) => ({
      ...anteriores,
      codigo_postal: codigo,
      colonia: quitarLaColonia ? '' : anteriores.colonia,
    }));
    if (cambio) {
      setColoniaDeLaLista(null);
      setFormaDeLaColonia('segun-lo-escrito');
    }
  }

  function escribirLaColonia(texto: string) {
    setSenas((anteriores) => ({ ...anteriores, colonia: texto }));
    setColoniaDeLaLista(null);
  }

  // La colonia elegida de la lista: su nombre del catálogo y, si la persona no eligió municipio, el suyo
  // (direcciones-E19).
  function elegirLaColonia(clave: string, colonia: ColoniaDelCatalogo) {
    setSenas((anteriores) => ({ ...anteriores, colonia: colonia.nombre }));
    setColoniaDeLaLista(clave);
    setFormaDeLaColonia('de-la-lista');
    if (municipio && !municipio.porLaColonia) {
      return;
    }
    const delCatalogo =
      municipios.estado.fase === 'listos'
        ? municipios.estado.municipios.find((uno) => uno.codigo === colonia.municipio.codigo)
        : undefined;
    const texto = delCatalogo ? municipioEnTexto(delCatalogo) : colonia.municipio.nombre;
    setMunicipio({ codigo: colonia.municipio.codigo, texto, porLaColonia: true });
    if (municipio?.codigo !== colonia.municipio.codigo) {
      anunciar(`${ETIQUETAS[forma].municipio}: ${texto}`);
    }
  }

  // "No aparece mi colonia": se escribe a mano, empezando con lo que buscó (direcciones-E20).
  function escribirlaAMano(busqueda: string) {
    setFormaDeLaColonia('a-mano');
    setColoniaDeLaLista(null);
    if (busqueda) {
      setSenas((anteriores) => ({ ...anteriores, colonia: busqueda }));
    }
  }

  // La colonia: de la lista de su código postal, o escrita, con lo que diga el catálogo de ese código postal.
  function campoDeLaColonia() {
    const etiqueta = ETIQUETAS[forma].colonia;
    const error = errores.colonia?.[0];
    if (colonias.fase === 'con-colonias') {
      const deLaLista =
        formaDeLaColonia === 'de-la-lista' || (formaDeLaColonia === 'segun-lo-escrito' && senas.colonia.trim() === '');
      if (deLaLista) {
        const lista = colonias.colonias;
        return (
          <Selector
            nombre="colonia"
            etiqueta={etiqueta}
            valor={senas.colonia}
            marcador={TEXTOS_DE_DIRECCIONES.elegirColonia}
            etiquetaDeLaBusqueda={TEXTOS_DE_DIRECCIONES.buscarColonia}
            opciones={lista.map((una, indice) => ({ clave: String(indice), texto: una.nombre, detalle: una.tipo }))}
            elegida={coloniaDeLaLista}
            alElegir={({ clave }) => elegirLaColonia(clave, lista[Number(clave)])}
            error={error}
            otraOpcion={{ texto: TEXTOS_DE_DIRECCIONES.noApareceMiColonia, alElegir: escribirlaAMano }}
          />
        );
      }
    }
    const nota = NOTA_DE_LAS_COLONIAS[colonias.fase];
    return (
      <View style={estilos.campoConNota}>
        <CampoDeTexto
          nombre="colonia"
          etiqueta={etiqueta}
          valor={senas.colonia}
          alCambiar={escribirLaColonia}
          error={error}
        />
        {nota ? (
          <Text accessibilityLiveRegion="polite" style={estilos.texto}>
            {nota}
          </Text>
        ) : null}
        {colonias.fase === 'con-colonias' ? (
          <Enlace texto={TEXTOS_DE_DIRECCIONES.elegirDeLaLista} alTocar={() => setFormaDeLaColonia('de-la-lista')} />
        ) : null}
      </View>
    );
  }

  // El municipio de la lista, o lo que se ve mientras llega o si no llegó (direcciones-E17).
  function campoDelMunicipio() {
    const { estado, reintentar } = municipios;
    const opciones: OpcionDelSelector[] =
      estado.fase === 'listos'
        ? estado.municipios.map((uno) => ({ clave: uno.codigo, texto: municipioEnTexto(uno) }))
        : [];
    const sinLista = estado.fase === 'no-disponibles';
    const aviso =
      forma === 'escrita'
        ? TEXTOS_DE_DIRECCIONES.municipiosNoDisponibles
        : TEXTOS_DE_DIRECCIONES.municipiosNoDisponiblesEnElMapa;
    return (
      <>
        <Selector
          nombre="municipio"
          etiqueta={ETIQUETAS[forma].municipio}
          valor={municipio?.texto ?? ''}
          marcador={
            estado.fase === 'cargando' ? TEXTOS_DE_DIRECCIONES.cargandoMunicipios : TEXTOS_DE_DIRECCIONES.elegirMunicipio
          }
          etiquetaDeLaBusqueda={TEXTOS_DE_DIRECCIONES.buscarMunicipio}
          opciones={opciones}
          elegida={municipio?.codigo ?? null}
          alElegir={({ clave, texto }) => setMunicipio({ codigo: clave, texto, porLaColonia: false })}
          // El error del servidor manda; sin él, el aviso de la lista que no llegó.
          error={errores.municipio?.[0] ?? (sinLista ? aviso : undefined)}
          deshabilitado={estado.fase !== 'listos'}
        />
        {sinLista ? (
          <Boton variante="secundario" onPress={reintentar}>
            {TEXTOS_DE_DIRECCIONES.intentarDeNuevo}
          </Boton>
        ) : null}
      </>
    );
  }

  function nuevaDireccion(): DireccionNueva | null {
    const favorita = { es_favorita: esFavorita, alias };
    const todas = { ...senas, municipio: municipio?.codigo ?? '' };
    if (forma === 'escrita') {
      return { origen: 'escrita', ...todas, ...favorita };
    }
    return pin ? { origen: 'mapa', latitud: pin.latitud, longitud: pin.longitud, ...todas, ...favorita } : null;
  }

  async function guardar() {
    const nueva = nuevaDireccion();
    if (!token || guardando || !nueva) {
      return;
    }
    setGuardando(true);
    setFalla(null);
    const resultado = await guardarDireccion(token, nueva);
    setGuardando(false);
    if (!resultado.ok) {
      setFalla(resultado);
      return;
    }
    agregarDireccion(resultado.datos, para);
    regresar();
  }

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS_DE_DIRECCIONES.tituloDeLaNueva, alRegresar: regresar }}
      acciones={
        sePuedeGuardar ? (
          <Boton onPress={guardar} ocupado={guardando}>
            {TEXTOS_DE_DIRECCIONES.guardar}
          </Boton>
        ) : undefined
      }
    >
      <Pestanas pestanas={FORMAS} elegida={forma} alElegir={cambiarForma} />
      {forma === 'mapa' ? <ElMapa inicio={inicio} alMover={setPinMovido} /> : null}
      <View style={estilos.campos}>
        {campo('calle')}
        {campo('numero_exterior')}
        {campo('numero_interior')}
        <CampoDeTexto
          nombre="codigo_postal"
          tipo="codigo-postal"
          etiqueta={ETIQUETAS[forma].codigo_postal}
          valor={senas.codigo_postal}
          alCambiar={cambiarElCodigoPostal}
          error={errores.codigo_postal?.[0]}
        />
        {campoDeLaColonia()}
        {campoDelMunicipio()}
        {campo('referencias', true)}
        <Interruptor
          etiqueta={TEXTOS_DE_DIRECCIONES.guardarComoFavorita}
          valor={esFavorita}
          alCambiar={setEsFavorita}
        />
        {esFavorita ? (
          <CampoDeTexto
            nombre="alias"
            etiqueta={TEXTOS_DE_DIRECCIONES.nombreDeLaFavorita}
            valor={alias}
            alCambiar={setAlias}
            error={errores.alias?.[0]}
          />
        ) : null}
      </View>
      {falla ? <MensajeDeError texto={mensajeDelAlta(falla, CAMPOS_VISIBLES)} /> : null}
      {falla && forma === 'escrita' && ofreceElMapa(falla) ? (
        <Boton variante="secundario" onPress={() => cambiarForma('mapa')}>
          {TEXTOS_DE_DIRECCIONES.marcarlaEnElMapa}
        </Boton>
      ) : null}
    </Pantalla>
  );
}

// El mapa con su instrucción, o lo que se ve mientras se busca la ubicación, o sin ella.
function ElMapa({ inicio, alMover }: { inicio: ReturnType<typeof usePuntoDeInicio>; alMover: (punto: Punto) => void }) {
  switch (inicio.fase) {
    case 'cargando':
      return (
        <View style={estilos.aviso}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={estilos.texto}>{TEXTOS_DE_DIRECCIONES.buscandoTuUbicacion}</Text>
        </View>
      );
    case 'sin-ubicacion':
      return (
        <View style={estilos.aviso}>
          <Text style={estilos.texto}>{TEXTOS_DE_DIRECCIONES.sinUbicacionParaElMapa}</Text>
        </View>
      );
    case 'listo':
      return (
        <View style={estilos.mapa}>
          <MapaParaMarcar centro={inicio.punto} alMover={alMover} />
          {hayMapaNativo() ? <Text style={estilos.texto}>{TEXTOS_DE_DIRECCIONES.instruccionDelMapa}</Text> : null}
        </View>
      );
  }
}

// Lo que se dice junto a la colonia escrita, según lo que dio su código postal (direcciones-E20).
const NOTA_DE_LAS_COLONIAS: Partial<Record<ReturnType<typeof useColonias>['fase'], string>> = {
  buscando: TEXTOS_DE_DIRECCIONES.buscandoColonias,
  'sin-colonias': TEXTOS_DE_DIRECCIONES.sinColoniasDelCodigoPostal,
  'no-disponibles': TEXTOS_DE_DIRECCIONES.coloniasNoDisponibles,
};

const estilos = StyleSheet.create({
  campos: {
    gap: espaciado[4],
  },
  campoConNota: {
    gap: espaciado[1],
  },
  mapa: {
    gap: espaciado[2],
  },
  aviso: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[5],
  },
  texto: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
