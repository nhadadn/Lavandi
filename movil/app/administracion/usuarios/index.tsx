// Usuarios, la lista de la gestión de usuarios (F-D03; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3,
// "Consulta de usuarios activos, historial y bloqueo"; administracion-app-E24): se abre con "Usuarios" en el inicio del
// módulo (administracion-app-E03). Trae las cuentas con el rol de usuario como las da GET /api/administracion/usuarios,
// cada una con su nombre, su estado y su correo, en páginas, con búsqueda por correo y por nombre. Tocar una abre su
// ficha. Se pide al abrir la pantalla, al buscar, al cambiar de página y al volver a ella, para que el estado que cambió
// en la ficha se vea aquí.
//
// No ofrece dar de alta, editar ni borrar usuarios: el Módulo D #3 dice consulta, historial y bloqueo. El filtro de
// usuarios activos espera DD-111 (administracion-app-E26): su lugar va en ámbar, sin ofrecerlo.
//
// Si la lista no llega, sin escenario propio: los mensajes generales de la app e "Intentar de nuevo", como la imagen 05
// de la Propuesta 06. Con un 401, el cliente de la administración ya volvió al inicio de sesión (administracion-app-E05).
//
// La composición es la de la imagen 21 de la Propuesta 06: el encabezado con el regreso al inicio del módulo, la
// búsqueda, el filtro que espera DD-111 en su tarjeta, una fila tocable por cuenta, con el nombre arriba, la insignia y el
// correo debajo, y la flecha a la derecha; y abajo, la página en que va con "Siguiente página".
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../../src/administracion/rutas';
import type { PaginaDeLaLista, UsuarioDeLaLista } from '../../../src/administracion/usuarios/api';
import { InsigniaDelUsuario } from '../../../src/administracion/usuarios/InsigniaDelUsuario';
import { PorConfirmar } from '../../../src/administracion/usuarios/PorConfirmar';
import { rutaDeLaFichaDelUsuario } from '../../../src/administracion/usuarios/rutas';
import {
  mensajeDeLaFalla,
  nombreDelUsuario,
  paginaDeLaLista,
  TEXTOS_DE_LOS_USUARIOS as TEXTOS,
  usuarioParaLeer,
} from '../../../src/administracion/usuarios/textos';
import { useListaDeUsuarios } from '../../../src/administracion/usuarios/useListaDeUsuarios';
import { useRegresar } from '../../../src/navegacion/regresar';
import { Boton } from '../../../src/piezas/Boton';
import { CampoDeTexto } from '../../../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { letra } from '../../../src/tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../../../src/tema/theme';

export default function Usuarios() {
  const router = useRouter();
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const [buscar, setBuscar] = useState('');
  const [pagina, setPagina] = useState(1);
  const { estado, volverAConsultar } = useListaDeUsuarios(buscar, pagina);

  // Una búsqueda nueva empieza en la primera página.
  const alBuscar = (texto: string) => {
    setBuscar(texto);
    setPagina(1);
  };

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.titulo, alRegresar: regresar }}>
      <CampoDeTexto nombre="buscar" etiqueta={TEXTOS.buscar} valor={buscar} alCambiar={alBuscar} />
      <PorConfirmar
        etiqueta={TEXTOS.activosDeUnPeriodo}
        dd={TEXTOS.esperaElFiltro}
        enTarjeta
        testID="activos-de-un-periodo"
      />
      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoLaLista}</Text>
        </View>
      ) : null}
      {estado.fase === 'error' ? (
        <>
          <MensajeDeError texto={mensajeDeLaFalla(estado.falla)} />
          <Boton variante="secundario" onPress={volverAConsultar}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}
      {estado.fase === 'lista' ? (
        <>
          {estado.lista.usuarios.length === 0 ? (
            <Parrafo alineacion="inicio">
              {buscar.trim() === '' ? TEXTOS.sinUsuarios : TEXTOS.sinCoincidencias}
            </Parrafo>
          ) : null}
          {estado.lista.usuarios.map((usuario) => (
            <FilaDelUsuario
              key={usuario.id_publico}
              usuario={usuario}
              alTocar={() => router.push(rutaDeLaFichaDelUsuario(usuario.id_publico))}
            />
          ))}
          <Paginas pagina={estado.lista.pagina} alIrA={setPagina} />
        </>
      ) : null}
    </Pantalla>
  );
}

// Una cuenta de la lista: toda la fila se toca y se anuncia como un solo enlace, "Ana, Activo, ana@ejemplo.com".
function FilaDelUsuario({ usuario, alTocar }: { usuario: UsuarioDeLaLista; alTocar: () => void }) {
  return (
    <Pressable
      role="link"
      accessibilityLabel={usuarioParaLeer(usuario)}
      onPress={alTocar}
      testID={`usuario-${usuario.id_publico}`}
      style={({ pressed }) => [estilos.fila, pressed ? estilos.filaPresionada : null]}
    >
      <View style={estilos.textos}>
        <Text style={[estilos.nombre, letra('cuerpo', 'semifuerte')]}>{nombreDelUsuario(usuario.nombre)}</Text>
        <View style={estilos.renglon}>
          <InsigniaDelUsuario estado={usuario.estado} />
          <Text style={[estilos.correo, letra('cuerpo', 'regular')]} testID="correo-del-usuario">
            {usuario.correo}
          </Text>
        </View>
      </View>
      {/* La flecha es adorno: la fila ya se anuncia como enlace. Es un carácter, sin librería de íconos. */}
      <Text style={estilos.flecha} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
        ›
      </Text>
    </Pressable>
  );
}

// La página en que va la lista, "Página 1 de 3", y los botones a la de al lado que existe. En la primera solo va
// "Siguiente página" y en la última solo "Página anterior", como en la imagen 21.
function Paginas({ pagina, alIrA }: { pagina: PaginaDeLaLista; alIrA: (pagina: number) => void }) {
  const { actual, ultima } = pagina;
  return (
    <View style={estilos.paginas}>
      <Text style={[estilos.suave, estilos.pagina, letra('cuerpo', 'regular')]}>{paginaDeLaLista(actual, ultima)}</Text>
      {actual > 1 ? (
        <View>
          <Boton variante="secundario" onPress={() => alIrA(actual - 1)}>
            {TEXTOS.paginaAnterior}
          </Boton>
        </View>
      ) : null}
      {actual < ultima ? (
        <View>
          <Boton variante="secundario" onPress={() => alIrA(actual + 1)}>
            {TEXTOS.siguientePagina}
          </Boton>
        </View>
      ) : null}
    </View>
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
  // La tarjeta tocable de la imagen 21.
  fila: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  filaPresionada: {
    backgroundColor: color.semantico.superficie_hundida,
  },
  textos: {
    flex: 1,
    gap: espaciado[2],
  },
  nombre: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  renglon: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: espaciado[3],
  },
  correo: {
    flexShrink: 1,
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  flecha: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.titulo,
  },
  // "Página 1 de 3" a la izquierda y los botones a la derecha; si no caben, bajan al siguiente renglón.
  paginas: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'flex-end',
    gap: espaciado[2],
  },
  // "Página 1 de 3" en el texto menor, como en la imagen 21.
  pagina: {
    flexGrow: 1,
    fontSize: tipografia.escala.menor,
  },
});
