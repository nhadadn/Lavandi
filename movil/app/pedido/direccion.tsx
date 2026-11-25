// El paso de la dirección del pedido nuevo, "Pedido nuevo · 3 de 4" (openspec/changes/direcciones, direcciones-E06 a
// E11). Llega desde el paso de fecha y hora con el pedido en curso.
//
// - Las favoritas de la cuenta, que trae el servidor con su punto guardado, son las opciones para la recogida; elegir
//   una no geocodifica (direcciones-E06 y E07). "Agregar otra dirección" abre la dirección nueva para la recogida o
//   para la entrega; al volver, la nueva queda elegida para lo que se agregó. Una favorita entra con las favoritas; una
//   que no lo es queda aparte, solo para este pedido, y en un pedido nuevo ya no está (direcciones-E08).
// - "Entregar en la misma dirección" viene encendido: el pedido lleva la recogida en las dos (direcciones-E09).
//   Apagado, una segunda lista deja elegir la entrega (direcciones-E10).
// - "Continuar" manda la recogida y la entrega al servidor, que las acepta solo dentro de la zona de servicio de la
//   plaza de la lavandería. Si acepta, quedan en el pedido en curso y la app pasa al resumen. Si rechaza una, el aviso
//   va bajo esa lista, con su motivo, y se va al elegir otra (direcciones-E11).
// - Si el servidor rechaza la recogida o la entrega al aceptar el resumen (resumen-y-confirmacion, confirmacion-E08), la
//   app regresa aquí con el aviso bajo la que rechazó, que sigue elegida, igual que el de "Continuar". Si la dirección
//   quedó con la plaza de antes de un cambio de zonas, el aviso pide guardarla de nuevo, y "Agregar otra dirección" lo
//   hace (confirmacion-E15).
//
// La composición es la del tablero "Dirección (3 de 4)" de la Propuesta 5 corregida (C-233) y la imagen a08-direccion
// de la Propuesta 04. Del mock no entran la estrella de las favoritas (van bajo su título) ni el radio de servicio de la
// lavandería (direcciones-E13, DD-083). Ninguna viene elegida: la persona elige, como en la selección de servicio.
//
// Sin pedido en curso, por ejemplo con un enlace directo, lleva a la pantalla principal. Sin conexión o con el servidor
// caído, sin escenario propio: los mensajes generales de la app.
import { Redirect, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { mensajeDeLaFalla } from '../../src/cercanas/textos';
import type { Falla } from '../../src/configuracion/api';
import { validarRecogidaYEntrega, type Direccion, type Rechazo } from '../../src/direcciones/api';
import { OpcionDeDireccion } from '../../src/direcciones/OpcionDeDireccion';
import { avisoDeRechazo, TEXTOS_DE_DIRECCIONES } from '../../src/direcciones/textos';
import { useFavoritas, type EstadoDeLasFavoritas } from '../../src/direcciones/useFavoritas';
import { useRegresar } from '../../src/navegacion/regresar';
import {
  elegirDirecciones,
  usePedidoEnCurso,
  type DireccionAgregada,
  type DireccionRechazada,
  type ParaQueEsLaDireccion,
  type PedidoEnCurso,
} from '../../src/pedido/pedidoEnCurso';
import { RUTA_DE_FECHA_Y_HORA, RUTA_DEL_RESUMEN, rutaDeLaDireccionNueva } from '../../src/pedido/rutas';
import { pasoDelPedido, TEXTOS_DEL_PEDIDO } from '../../src/pedido/textos';
import { Boton } from '../../src/piezas/Boton';
import { Enlace } from '../../src/piezas/Enlace';
import { Interruptor } from '../../src/piezas/Interruptor';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Titulo } from '../../src/piezas/Titulo';
import { TituloDeSeccion } from '../../src/piezas/TituloDeSeccion';
import { useSesion } from '../../src/sesion/ProveedorDeSesion';
import { letra } from '../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../src/tema/theme';

/**
 * Lo que la persona eligió tocando una opción, y cuántas direcciones había agregado entonces: una que agregue después
 * para lo mismo manda sobre lo que había tocado.
 */
interface Eleccion {
  id: string | null;
  vistas: number;
}

type Elecciones = Record<ParaQueEsLaDireccion, Eleccion>;

export default function PasoDeLaDireccion() {
  const router = useRouter();
  const regresar = useRegresar(RUTA_DE_FECHA_Y_HORA);
  const { token } = useSesion();
  const pedido = usePedidoEnCurso();
  const favoritas = useFavoritas();
  const [elecciones, setElecciones] = useState<Elecciones>(() => eleccionesIniciales(pedido));
  const [misma, setMisma] = useState(() => entregaEnLaMisma(pedido));
  const [rechazadas, setRechazadas] = useState<Partial<Record<ParaQueEsLaDireccion, DireccionRechazada>>>({});
  const [falta, setFalta] = useState<ParaQueEsLaDireccion | null>(null);
  const [falla, setFalla] = useState<Falla | null>(null);
  const [validando, setValidando] = useState(false);

  if (!pedido) {
    return <Redirect href="/inicio" />;
  }

  const agregadas = pedido.agregadas ?? [];
  const delServidor = favoritas.fase === 'listas' ? favoritas.favoritas : [];
  const nuevas = agregadas.map(({ direccion }) => direccion);
  const listaDeFavoritas = sinRepetir([...delServidor, ...nuevas.filter((direccion) => direccion.es_favorita)]);
  const soloEste = sinRepetir(nuevas.filter((direccion) => !direccion.es_favorita));

  const elegidas: Record<ParaQueEsLaDireccion, string | null> = {
    recogida: elegidaPara('recogida', elecciones, agregadas),
    entrega: elegidaPara('entrega', elecciones, agregadas),
  };
  // El aviso de una rechazada solo sigue mientras siga elegida (direcciones-E11), la haya rechazado "Continuar" o el
  // resumen (confirmacion-E08).
  const rechazoPara = (para: ParaQueEsLaDireccion) =>
    [rechazadas[para], pedido.direccionesRechazadas?.[para]].find((rechazada) => rechazada?.id === elegidas[para])
      ?.rechazo;

  function elegir(para: ParaQueEsLaDireccion, id: string) {
    setElecciones((anteriores) => ({ ...anteriores, [para]: { id, vistas: agregadas.length } }));
  }

  function mensajeDeAbajo(): string | undefined {
    if (falta === 'recogida' && !elegidas.recogida) {
      return TEXTOS_DE_DIRECCIONES.faltaLaRecogida;
    }
    if (falta === 'entrega' && !misma && !elegidas.entrega) {
      return TEXTOS_DE_DIRECCIONES.faltaLaEntrega;
    }
    return falla ? mensajeDeLaFalla(falla) : undefined;
  }

  async function continuar(lavanderia: string) {
    const recogida = elegidas.recogida;
    const entrega = misma ? null : elegidas.entrega;
    if (!recogida) {
      setFalta('recogida');
      return;
    }
    if (!misma && !entrega) {
      setFalta('entrega');
      return;
    }
    if (!token || validando) {
      return;
    }
    setFalta(null);
    setFalla(null);
    setValidando(true);
    const resultado = await validarRecogidaYEntrega(token, { lavanderia, recogida, entrega });
    setValidando(false);
    if (resultado.ok) {
      elegirDirecciones(resultado.datos);
      router.push(RUTA_DEL_RESUMEN);
      return;
    }
    if (resultado.motivo === 'rechazadas') {
      const { recogida: rechazoDeRecogida, entrega: rechazoDeEntrega } = resultado.rechazos;
      setRechazadas({
        recogida: rechazoDeRecogida ? { id: recogida, rechazo: rechazoDeRecogida } : undefined,
        entrega: rechazoDeEntrega && entrega ? { id: entrega, rechazo: rechazoDeEntrega } : undefined,
      });
      return;
    }
    setFalla(resultado);
  }

  function lista(para: ParaQueEsLaDireccion) {
    return (
      <Direcciones
        para={para}
        estado={favoritas}
        favoritas={listaDeFavoritas}
        soloEste={soloEste}
        elegida={elegidas[para]}
        rechazo={rechazoPara(para)}
        alElegir={(id) => elegir(para, id)}
        alAgregar={() => router.push(rutaDeLaDireccionNueva(para))}
      />
    );
  }

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: pasoDelPedido(3), alRegresar: regresar }}
      acciones={
        <Boton onPress={() => continuar(pedido.lavanderia.id_publico)} ocupado={validando}>
          {TEXTOS_DEL_PEDIDO.continuar}
        </Boton>
      }
    >
      <Titulo alineacion="inicio">{TEXTOS_DE_DIRECCIONES.tituloDeLaRecogida}</Titulo>
      {lista('recogida')}
      <View style={estilos.division} />
      <TituloDeSeccion>{TEXTOS_DE_DIRECCIONES.entrega}</TituloDeSeccion>
      <Interruptor etiqueta={TEXTOS_DE_DIRECCIONES.mismaDireccion} valor={misma} alCambiar={setMisma} />
      {misma ? null : (
        <>
          <Text style={[estilos.pregunta, letra('titulares', 'fuerte')]}>{TEXTOS_DE_DIRECCIONES.tituloDeLaEntrega}</Text>
          {lista('entrega')}
        </>
      )}
      <MensajeDeError texto={mensajeDeAbajo()} />
    </Pantalla>
  );
}

// Al abrirse, lo que ya aceptó el servidor si la persona vuelve a este paso; y las direcciones agregadas hasta ahora ya
// no cambian la elección.
function eleccionesIniciales(pedido: PedidoEnCurso | null): Elecciones {
  const vistas = pedido?.agregadas?.length ?? 0;
  return {
    recogida: { id: pedido?.recogida?.id_publico ?? null, vistas },
    entrega: { id: entregaEnLaMisma(pedido) ? null : (pedido?.entrega?.id_publico ?? null), vistas },
  };
}

// Sin entrega aceptada todavía, la misma dirección, como viene el interruptor (direcciones-E09).
function entregaEnLaMisma(pedido: PedidoEnCurso | null): boolean {
  return !pedido?.entrega || pedido.entrega.id_publico === pedido.recogida?.id_publico;
}

// La elegida para la recogida o la entrega: la última que se agregó para eso después de tocar una opción, o la tocada.
function elegidaPara(para: ParaQueEsLaDireccion, elecciones: Elecciones, agregadas: DireccionAgregada[]): string | null {
  const { id, vistas } = elecciones[para];
  const posteriores = agregadas.slice(vistas).filter((agregada) => agregada.para === para);
  return posteriores.at(-1)?.direccion.id_publico ?? id;
}

function sinRepetir(direcciones: Direccion[]): Direccion[] {
  return direcciones.filter(
    (direccion, indice) => direcciones.findIndex((otra) => otra.id_publico === direccion.id_publico) === indice,
  );
}

interface PropiedadesDeDirecciones {
  para: ParaQueEsLaDireccion;
  estado: EstadoDeLasFavoritas;
  favoritas: Direccion[];
  soloEste: Direccion[];
  elegida: string | null;
  rechazo?: Rechazo;
  alElegir: (id: string) => void;
  alAgregar: () => void;
}

// Las opciones para la recogida o para la entrega: las favoritas, las de solo este pedido, el aviso si el servidor
// rechazó la elegida y "Agregar otra dirección".
function Direcciones({ para, estado, favoritas, soloEste, elegida, rechazo, alElegir, alAgregar }: PropiedadesDeDirecciones) {
  const hayOpciones = favoritas.length + soloEste.length > 0;
  const grupo = (testID: string, titulo: string, direcciones: Direccion[]) => (
    <View testID={testID} accessibilityRole="radiogroup" style={estilos.grupo}>
      <Text style={[estilos.etiqueta, letra('cuerpo', 'semifuerte')]}>{titulo}</Text>
      {direcciones.map((direccion) => (
        <OpcionDeDireccion
          key={direccion.id_publico}
          direccion={direccion}
          elegida={direccion.id_publico === elegida}
          alElegir={() => alElegir(direccion.id_publico)}
        />
      ))}
    </View>
  );

  return (
    <View testID={`direcciones-de-${para}`} style={estilos.grupo}>
      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={estilos.suave}>{TEXTOS_DE_DIRECCIONES.cargandoFavoritas}</Text>
        </View>
      ) : null}
      {estado.fase === 'error' ? <MensajeDeError texto={mensajeDeLaFalla(estado.falla)} /> : null}
      {favoritas.length > 0 ? grupo(`favoritas-de-${para}`, TEXTOS_DE_DIRECCIONES.favoritas, favoritas) : null}
      {estado.fase === 'listas' && !hayOpciones ? (
        <Text style={estilos.suave}>{TEXTOS_DE_DIRECCIONES.sinFavoritas}</Text>
      ) : null}
      {soloEste.length > 0 ? grupo(`solo-este-pedido-de-${para}`, TEXTOS_DE_DIRECCIONES.soloEstePedido, soloEste) : null}
      {rechazo ? (
        <View style={estilos.aviso}>
          <MensajeDeError texto={avisoDeRechazo(rechazo)} />
        </View>
      ) : null}
      <Enlace
        texto={hayOpciones ? TEXTOS_DE_DIRECCIONES.agregarOtra : TEXTOS_DE_DIRECCIONES.agregarUna}
        alTocar={alAgregar}
      />
    </View>
  );
}

const estilos = StyleSheet.create({
  grupo: {
    gap: espaciado[3],
  },
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  cargando: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[4],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  // El aviso rojo de una dirección rechazada (direcciones-E11): el borde de error además del texto.
  aviso: {
    padding: espaciado[3],
    borderWidth: grosor.control,
    borderColor: color.estado.error,
    borderRadius: radio.medio,
    backgroundColor: color.semantico.superficie,
  },
  division: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: color.semantico.borde,
  },
  pregunta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
});
