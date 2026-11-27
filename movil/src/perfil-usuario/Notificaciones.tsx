// La sección "Notificaciones" de "Mi perfil" (perfil-usuario-E08; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A
// #15). Es la lectura mínima del numeral 15 (design.md de perfil-y-soporte, decisión 4): dice si los avisos de la app
// están activos en el teléfono y, si no lo están, cómo activarlos, con el permiso de avisos (src/avisos/permiso.ts):
//
// - si la persona nunca respondió la petición, "Activar avisos" la muestra;
// - si ya la negó, o apagó los avisos en el teléfono, "Abrir ajustes" abre los ajustes de la app en el sistema, que es
//   donde se activan entonces.
//
// Al volver la app al frente, como al regresar de los ajustes, lee otra vez el permiso. Si los avisos quedaron activos,
// el teléfono se registra en la api con la sesión (avisos-E03; src/avisos/telefono.ts).
//
// Sin avisos en la compilación (Expo Go, iPhone hasta avisos-E18 o un APK sin el archivo de Firebase), la sección no
// aparece: no hay qué activar. Del mock a15-perfil no entran los interruptores por tipo de aviso, que no tienen cita
// (proposal.md de perfil-y-soporte, "Fuera del contrato"). No es uno de los seis componentes del design system: se arma
// con los tokens (ADR-0013), como las demás secciones del perfil.
import { useCallback, useEffect, useRef, useState } from 'react';
import { AppState, Linking, StyleSheet, Text, View } from 'react-native';

import { avisosDisponibles } from '../avisos/firebase';
import {
  alResponderLaPeticion,
  leerPermisoDeAvisos,
  pedirPermisoDeAvisos,
  type PermisoDeAvisos,
} from '../avisos/permiso';
import { registrarElTelefonoDeNuevo } from '../avisos/telefono';
import { Boton } from '../piezas/Boton';
import { TituloDeSeccion } from '../piezas/TituloDeSeccion';
import { letra } from '../tema/letra';
import { color, espaciado, tipografia } from '../tema/theme';
import { TEXTOS_DEL_PERFIL_DE_USUARIO as TEXTOS } from './textos';

export function Notificaciones() {
  if (!avisosDisponibles()) {
    return null;
  }
  return <SeccionDeNotificaciones />;
}

function SeccionDeNotificaciones() {
  const [permiso, setPermiso] = useState<PermisoDeAvisos | null>(null);
  const [pidiendo, setPidiendo] = useState(false);
  // El permiso que se mostró por última vez: el teléfono se registra solo cuando pasa a concedido aquí.
  const anterior = useRef<PermisoDeAvisos | null>(null);

  const mostrar = useCallback((siguiente: PermisoDeAvisos) => {
    if (siguiente === 'concedido' && anterior.current !== null && anterior.current !== 'concedido') {
      registrarElTelefonoDeNuevo();
    }
    anterior.current = siguiente;
    setPermiso(siguiente);
  }, []);

  const leer = useCallback(() => {
    leerPermisoDeAvisos()
      .then(mostrar)
      // Sin poder leerlo, la sección se queda como estaba.
      .catch(() => undefined);
  }, [mostrar]);

  useEffect(() => {
    leer();
    const suscripcion = AppState.addEventListener('change', (estado) => {
      if (estado === 'active') {
        leer();
      }
    });
    // La petición que se muestra al abrirse la sesión puede contestarse con el perfil ya abierto.
    const dejarDeEscuchar = alResponderLaPeticion(mostrar);
    return () => {
      suscripcion.remove();
      dejarDeEscuchar();
    };
  }, [leer, mostrar]);

  async function activar() {
    setPidiendo(true);
    try {
      // Lo que conteste la persona llega a mostrar() por alResponderLaPeticion.
      await pedirPermisoDeAvisos();
    } catch {
      // La petición no se pudo mostrar: la persona sigue sin responder y puede volver a intentarlo.
    } finally {
      setPidiendo(false);
    }
  }

  function abrirLosAjustes() {
    Linking.openSettings().catch(() => undefined);
  }

  return (
    <View style={estilos.seccion} testID="notificaciones">
      <TituloDeSeccion>{TEXTOS.notificaciones}</TituloDeSeccion>
      {permiso === 'concedido' ? (
        <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{TEXTOS.avisosActivos}</Text>
      ) : null}
      {permiso === 'sin-responder' || permiso === 'negado' ? (
        <Text style={[estilos.texto, letra('cuerpo', 'regular')]}>{TEXTOS.avisosApagados}</Text>
      ) : null}
      {permiso === 'sin-responder' ? (
        <Boton variante="secundario" ocupado={pidiendo} onPress={activar}>
          {TEXTOS.activarAvisos}
        </Boton>
      ) : null}
      {permiso === 'negado' ? (
        <>
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.activalosEnLosAjustes}</Text>
          <Boton variante="secundario" onPress={abrirLosAjustes}>
            {TEXTOS.abrirAjustes}
          </Boton>
        </>
      ) : null}
    </View>
  );
}

const estilos = StyleSheet.create({
  seccion: {
    gap: espaciado[2],
  },
  texto: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
