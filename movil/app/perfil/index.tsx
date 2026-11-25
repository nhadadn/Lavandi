// "Mi perfil" del Módulo A (openspec/changes/perfil-y-soporte; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A
// #15). Se abre desde "Mi perfil" en la pantalla principal y pide el perfil al servidor con la sesión.
//
// - Arriba, la foto: se toma con la cámara o se elige de la galería, y se sube comprimida en cuanto se tiene
//   (perfil-usuario-E03); si la API la rechaza, el error va junto a ella y la anterior sigue (perfil-usuario-E04).
// - El nombre se cambia y se guarda con "Guardar nombre" (perfil-usuario-E01). La app manda lo que se escribió y el
//   servidor quita los espacios de los lados; el perfil muestra el nombre que guardó. Vacío o solo con espacios, la API
//   lo rechaza y el error va junto al campo; el nombre guardado no cambia (perfil-usuario-E02).
// - El correo solo se ve: el numeral 15 no lo lista y no se cambia desde el perfil.
// - Las direcciones guardadas son las favoritas de la cuenta (perfil-usuario-E05).
// - "Notificaciones" dice si los avisos están activos en el teléfono y, si no, ofrece pedir el permiso o abrir los
//   ajustes (perfil-usuario-E08; src/perfil-usuario/Notificaciones.tsx). Sin avisos en la compilación no aparece. No
//   depende del perfil: se ve aunque no llegue.
// - "Legal", el aviso de privacidad y los términos, con las direcciones de la configuración, que se abren dentro de la
//   app (textos-legales-E05; src/textos-legales/EnlacesLegales.tsx). No dependen del perfil: se ven aunque no llegue.
// - "Eliminar mi cuenta", al final, borra la cuenta después de confirmarlo y vuelve a la bienvenida (perfil-usuario-E15).
//
// La composición sigue las imágenes a15-perfil y a15-perfil-foto de la Propuesta 04 en lo que tiene escenario. Del mock
// no entran todavía: los métodos de pago (perfil-usuario-E07, bloqueado por DD-055), los interruptores por tipo de aviso
// (sin cita), la barra inferior y el lápiz de cada dirección (su edición no existe todavía). "Cerrar sesión" sigue en la
// pantalla principal (registro-E13).
//
// Si el perfil no llega, sin escenario propio: los mensajes generales de la app, como el perfil de la lavandería, y sin
// la foto ni el formulario del nombre.
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { errorDeCampo } from '../../src/acceso/mensajes';
import { mensajeDeLaFalla } from '../../src/cercanas/textos';
import type { Falla } from '../../src/configuracion/api';
import { useFavoritas } from '../../src/direcciones/useFavoritas';
import { useRegresar } from '../../src/navegacion/regresar';
import { cambiarNombre, type PerfilDeUsuario } from '../../src/perfil-usuario/api';
import { BorrarCuenta } from '../../src/perfil-usuario/BorrarCuenta';
import { DireccionesGuardadas } from '../../src/perfil-usuario/DireccionesGuardadas';
import { FotoDePerfil } from '../../src/perfil-usuario/FotoDePerfil';
import { Notificaciones } from '../../src/perfil-usuario/Notificaciones';
import { mensajeDelNombre, TEXTOS_DEL_PERFIL_DE_USUARIO as TEXTOS } from '../../src/perfil-usuario/textos';
import { usePerfilDeUsuario } from '../../src/perfil-usuario/usePerfilDeUsuario';
import { Boton } from '../../src/piezas/Boton';
import { CampoDeTexto } from '../../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { useSesion } from '../../src/sesion/ProveedorDeSesion';
import { letra } from '../../src/tema/letra';
import { EnlacesLegales } from '../../src/textos-legales/EnlacesLegales';
import { TEXTOS_LEGALES } from '../../src/textos-legales/textos';
import { color, espaciado, tipografia } from '../../src/tema/theme';

export default function MiPerfil() {
  const regresar = useRegresar('/inicio');
  const { estado, actualizar } = usePerfilDeUsuario();
  const favoritas = useFavoritas();

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.miPerfil, alRegresar: regresar }}>
      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargando}</Text>
        </View>
      ) : null}
      {estado.fase === 'error' ? <MensajeDeError texto={mensajeDeLaFalla(estado.falla)} /> : null}
      {estado.fase === 'listo' ? (
        <>
          <FotoDePerfil foto={estado.perfil.foto} alGuardar={actualizar} />
          <NombreYCorreo perfil={estado.perfil} alGuardar={actualizar} />
          <DireccionesGuardadas favoritas={favoritas} />
        </>
      ) : null}
      <Notificaciones />
      <EnlacesLegales titulo={TEXTOS_LEGALES.legal} />
      <BorrarCuenta />
    </Pantalla>
  );
}

interface PropiedadesDelNombre {
  perfil: PerfilDeUsuario;
  /** Recibe el perfil que devolvió el servidor, con el nombre que guardó. */
  alGuardar: (perfil: PerfilDeUsuario) => void;
}

function NombreYCorreo({ perfil, alGuardar }: PropiedadesDelNombre) {
  const { token } = useSesion();
  const [nombre, setNombre] = useState(perfil.nombre ?? '');
  const [guardando, setGuardando] = useState(false);
  const [falla, setFalla] = useState<Falla | null>(null);
  const [guardado, setGuardado] = useState(false);

  function escribir(texto: string) {
    setNombre(texto);
    setGuardado(false);
  }

  async function guardar() {
    if (!token) {
      return;
    }
    setGuardando(true);
    setFalla(null);
    setGuardado(false);
    const resultado = await cambiarNombre(token, nombre);
    setGuardando(false);
    if (!resultado.ok) {
      setFalla(resultado);
      return;
    }
    // El campo queda con el nombre que guardó el servidor, sin los espacios de los lados.
    setNombre(resultado.datos.nombre ?? '');
    setGuardado(true);
    alGuardar(resultado.datos);
  }

  return (
    <>
      <View style={estilos.grupo}>
        <CampoDeTexto
          nombre="nombre"
          etiqueta={TEXTOS.nombre}
          valor={nombre}
          alCambiar={escribir}
          error={errorDeCampo(falla ?? undefined, 'nombre')}
        />
        <MensajeDeError texto={falla ? mensajeDelNombre(falla) : undefined} />
        {guardado ? (
          <Text accessibilityLiveRegion="polite" style={[estilos.confirmacion, letra('cuerpo', 'regular')]}>
            {TEXTOS.nombreGuardado}
          </Text>
        ) : null}
        <Boton variante="secundario" ocupado={guardando} onPress={guardar}>
          {TEXTOS.guardarNombre}
        </Boton>
      </View>
      <View style={estilos.correo}>
        <Text style={[estilos.etiqueta, letra('cuerpo', 'semifuerte')]}>{TEXTOS.correo}</Text>
        <Text style={[estilos.valor, letra('cuerpo', 'regular')]}>{perfil.correo}</Text>
        <Text style={[estilos.ayuda, letra('cuerpo', 'regular')]}>{TEXTOS.ayudaDelCorreo}</Text>
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
  grupo: {
    gap: espaciado[2],
  },
  confirmacion: {
    color: color.estado.exito,
    fontSize: tipografia.escala.menor,
  },
  correo: {
    gap: espaciado[1],
  },
  etiqueta: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  valor: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  ayuda: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
});
