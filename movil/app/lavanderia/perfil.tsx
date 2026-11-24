// El perfil y horarios de la lavandería, del Módulo B (openspec/changes/alta-de-lavanderias, alta-lavanderias-E19;
// NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6: "Edición de información de la lavandería y ventanas de
// disponibilidad"). Se abre desde la pantalla de pedidos. Al abrirse, pide al servidor, con la sesión, la lavandería de
// la cuenta con su información y sus ventanas actuales (GET /api/socio/lavanderias/{id_publico}) y la muestra con los
// mismos campos del registro (src/alta-lavanderias/FormularioDeLaLavanderia.tsx).
//
// "Guardar cambios" manda solo lo que cambió desde que se abrió (PATCH): la dirección completa si cambió alguna de sus
// partes, y los servicios y las ventanas completos si cambió alguno; con una foto nueva, en multipart por POST con
// _method=PATCH (design.md del cambio, "El contrato de la API"). Cada error del servidor va junto a su campo, y la
// dirección con su motivo, como en_otra_plaza (alta-lavanderias-E10; pregunta abierta 5). Al guardar, la pantalla queda
// con la lavandería que devolvió el servidor y lo dice. Mientras guarda, el botón se bloquea: un doble toque no manda
// dos veces. Sin conexión o con el servidor caído, sin escenario propio: los mensajes generales de la app, y lo escrito
// se queda.
//
// La composición es la de la imagen b06-perfil de la Propuesta 04, con el encabezado y "Regresar" de las demás
// pantallas. Del mock no entran el cupo por ventana ("Sin cupo", DD-099) ni lo que ve una lavandería suspendida (DD-104).
import { useEffect, useRef, useState, type SetStateAction } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { consultarLavanderiaDelSocio, editarLavanderia } from '../../src/alta-lavanderias/api';
import {
  cambiosEntre,
  erroresDelEnvio,
  formularioDe,
  type ErroresDelFormulario,
  type Formulario,
} from '../../src/alta-lavanderias/formulario';
import { FormularioDeLaLavanderia } from '../../src/alta-lavanderias/FormularioDeLaLavanderia';
import { useLavanderiaDeLaCuenta } from '../../src/alta-lavanderias/ProveedorDeLaLavanderia';
import { TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO as TEXTOS } from '../../src/alta-lavanderias/textos';
import { mensajeDeLaFalla } from '../../src/cercanas/textos';
import type { Falla } from '../../src/configuracion/api';
import { useRegresar } from '../../src/navegacion/regresar';
import { RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA } from '../../src/pedidos-lavanderia/rutas';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { useSesion } from '../../src/sesion/ProveedorDeSesion';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

const SIN_ERRORES: ErroresDelFormulario = { campos: {} };

/** El formulario como se abrió (o como quedó al guardar) y como está ahora. */
interface Edicion {
  original: Formulario;
  actual: Formulario;
}

export default function PerfilYHorarios() {
  const regresar = useRegresar(RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA);
  const { token } = useSesion();
  const { estado: deLaCuenta, poner } = useLavanderiaDeLaCuenta();
  const idPublico = deLaCuenta.fase === 'lista' ? deLaCuenta.lavanderia?.id_publico : undefined;

  const [edicion, setEdicion] = useState<Edicion | null>(null);
  const [fallaDeLaLectura, setFallaDeLaLectura] = useState<Falla | null>(null);
  const [errores, setErrores] = useState<ErroresDelFormulario>(SIN_ERRORES);
  const [guardando, setGuardando] = useState(false);
  const [guardado, setGuardado] = useState(false);
  // Ataja el segundo toque antes de que el botón se bloquee.
  const enCamino = useRef(false);

  // La lavandería actual, al abrir la pantalla.
  useEffect(() => {
    if (!token || !idPublico) {
      return;
    }
    let vigente = true;
    consultarLavanderiaDelSocio(token, idPublico).then((resultado) => {
      if (!vigente) {
        return;
      }
      if (resultado.ok) {
        const formulario = formularioDe(resultado.datos);
        setEdicion({ original: formulario, actual: formulario });
      } else {
        setFallaDeLaLectura(resultado);
      }
    });
    return () => {
      vigente = false;
    };
  }, [token, idPublico]);

  // Lo que la persona cambia: el aviso de lo guardado se va con el primer cambio.
  function cambiar(cambio: SetStateAction<Formulario>) {
    setGuardado(false);
    setEdicion((anterior) =>
      anterior ? { ...anterior, actual: typeof cambio === 'function' ? cambio(anterior.actual) : cambio } : anterior,
    );
  }

  async function guardar() {
    if (!token || !idPublico || !edicion || enCamino.current) {
      return;
    }
    enCamino.current = true;
    setGuardando(true);
    setGuardado(false);
    setErrores(SIN_ERRORES);
    const cambios = cambiosEntre(edicion.original, edicion.actual);
    const resultado = await editarLavanderia(token, idPublico, cambios);
    enCamino.current = false;
    setGuardando(false);
    if (resultado.ok) {
      const formulario = formularioDe(resultado.datos);
      setEdicion({ original: formulario, actual: formulario });
      setGuardado(true);
      poner(resultado.datos);
      return;
    }
    setErrores(erroresDelEnvio(resultado, cambios));
  }

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS.perfilYHorarios, alRegresar: regresar }}
      acciones={
        edicion ? (
          <Boton ocupado={guardando} onPress={() => void guardar()}>
            {TEXTOS.guardarCambios}
          </Boton>
        ) : undefined
      }
    >
      {!edicion && !fallaDeLaLectura ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.leyendo}</Text>
        </View>
      ) : null}
      {fallaDeLaLectura ? <MensajeDeError texto={mensajeDeLaFalla(fallaDeLaLectura)} /> : null}
      {edicion ? (
        <FormularioDeLaLavanderia formulario={edicion.actual} alCambiar={cambiar} errores={errores.campos} />
      ) : null}
      <MensajeDeError texto={errores.general} />
      {guardado ? (
        <Text accessibilityLiveRegion="polite" style={[estilos.guardado, letra('cuerpo', 'semifuerte')]}>
          {TEXTOS.cambiosGuardados}
        </Text>
      ) : null}
    </Pantalla>
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
  guardado: {
    color: color.estado.exito,
    fontSize: tipografia.escala.menor,
  },
});
