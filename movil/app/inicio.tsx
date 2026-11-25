// La pantalla principal del Módulo A (openspec/changes/lavanderias-cercanas): a donde llega la persona al entrar.
// Reemplaza al inicio provisional (C-225). Pide la ubicación solo mientras se usa, con su texto (cercanas-E03); con
// ella consulta las lavanderías cercanas y centra el mapa (cercanas-E04); pinta un pin por lavandería, y al tocar uno
// muestra su tarjeta, que abre el perfil (cercanas-E12); sin lavanderías lo dice (cercanas-E14); y "Pedido nuevo" abre
// la lista para elegir lavandería (cercanas-E15).
//
// La composición es la del tablero "Inicio" de la Propuesta 5 corregida y la imagen a03-principal-mapa de la Propuesta
// 04 (C-230 y C-233): el título, el mapa en una tarjeta con cuántas hay, la tarjeta de la elegida y "Pedido nuevo"
// abajo, a todo lo ancho. Del mock no entran: la barra inferior (Pedidos, Ayuda y Perfil aún no existen), "Filtrar"
// (los filtros de la tarea 4.1, cercanas-E21, viven en la lista que abre "Pedido nuevo"), el pedido activo (DD-135), la campana y la búsqueda por nombre (DD-136), "Recoger en" (direcciones) y el
// estado de disponibilidad de la tarjeta (cercanas-E13, DD-083 y DD-078).
//
// Mientras no exista la barra inferior, "Mis pedidos" (historial-E01), "Mi perfil" y "Ayuda" (perfil-y-soporte) van
// bajo el título, como enlaces.
//
// "Cerrar sesión" (registro-E13) queda abajo, provisional, hasta que exista el perfil (H7): borra el token del teléfono,
// pide al servidor revocarlo y lleva al inicio de sesión como primera pantalla (C-237; DD-131, punto 3).
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { AvisoDeLaBusqueda } from '../src/cercanas/AvisoDeLaBusqueda';
import { hayMapaNativo } from '../src/cercanas/mapa';
import { MapaDeLavanderias } from '../src/cercanas/MapaDeLavanderias';
import { rutaDeLaLista, rutaDelPerfil } from '../src/cercanas/rutas';
import { detalleDeLavanderia, mensajeDeLaFalla, resumenDeLasCercanas, TEXTOS } from '../src/cercanas/textos';
import { useLavanderiasCercanas } from '../src/cercanas/useLavanderiasCercanas';
import { RUTA_DEL_HISTORIAL } from '../src/historial/rutas';
import { TEXTOS_DEL_HISTORIAL } from '../src/historial/textos';
import { useAbrirComoPrimera } from '../src/navegacion/regresar';
import { RUTA_DE_AYUDA, RUTA_DEL_PERFIL_DE_USUARIO } from '../src/perfil-usuario/rutas';
import { TEXTOS_DEL_PERFIL_DE_USUARIO } from '../src/perfil-usuario/textos';
import { Boton } from '../src/piezas/Boton';
import { Enlace } from '../src/piezas/Enlace';
import { MensajeDeError } from '../src/piezas/MensajeDeError';
import { Pantalla } from '../src/piezas/Pantalla';
import { TarjetaDeLavanderia } from '../src/piezas/TarjetaDeLavanderia';
import { Titulo } from '../src/piezas/Titulo';
import { useSesion } from '../src/sesion/ProveedorDeSesion';
import { color, espaciado, radio, tipografia } from '../src/tema/theme';

export default function Inicio() {
  const router = useRouter();
  const { estado, permitir } = useLavanderiasCercanas();
  // La lavandería del pin que se tocó, por su id_publico.
  const [elegida, setElegida] = useState<string | null>(null);

  const lavanderias = estado.fase === 'lista' ? estado.lavanderias : [];
  const lavanderiaElegida = lavanderias.find((lavanderia) => lavanderia.id_publico === elegida);
  const punto = 'punto' in estado ? estado.punto : null;

  return (
    <Pantalla
      acciones={
        <>
          <Boton onPress={() => router.push(rutaDeLaLista(punto))}>{TEXTOS.pedidoNuevo}</Boton>
          <CerrarSesion />
        </>
      }
    >
      <Titulo alineacion="inicio">{TEXTOS.titulo}</Titulo>
      <View style={estilos.cuenta}>
        <Enlace texto={TEXTOS_DEL_HISTORIAL.misPedidos} alTocar={() => router.push(RUTA_DEL_HISTORIAL)} />
        <Enlace
          texto={TEXTOS_DEL_PERFIL_DE_USUARIO.miPerfil}
          alTocar={() => router.push(RUTA_DEL_PERFIL_DE_USUARIO)}
        />
        <Enlace texto={TEXTOS_DEL_PERFIL_DE_USUARIO.ayuda} alTocar={() => router.push(RUTA_DE_AYUDA)} />
      </View>
      <View style={estilos.tarjetaDelMapa}>
        {punto ? (
          <MapaDeLavanderias centro={punto} lavanderias={lavanderias} alElegir={setElegida} />
        ) : (
          <AvisoDeLaBusqueda estado={estado} permitir={permitir} />
        )}
        {estado.fase === 'lista' ? (
          <Text style={estilos.resumen}>{resumenDeLasCercanas(lavanderias.length, hayMapaNativo())}</Text>
        ) : null}
      </View>
      {estado.fase === 'error' ? <MensajeDeError texto={mensajeDeLaFalla(estado.falla)} /> : null}
      {lavanderiaElegida ? (
        <TarjetaDeLavanderia
          testID={`tarjeta-${lavanderiaElegida.id_publico}`}
          nombre={lavanderiaElegida.nombre}
          detalle={detalleDeLavanderia(lavanderiaElegida)}
          alTocar={() => router.push(rutaDelPerfil(lavanderiaElegida.id_publico))}
        />
      ) : null}
    </Pantalla>
  );
}

function CerrarSesion() {
  const abrirComoPrimera = useAbrirComoPrimera();
  const { cerrar } = useSesion();
  const [cerrando, setCerrando] = useState(false);

  async function cerrarSesion() {
    setCerrando(true);
    try {
      await cerrar();
    } catch {
      // El teléfono no pudo borrar el token, pero la app ya quedó sin sesión y el servidor lo revoca: se sale igual.
    }
    // Se vacía la pila: "atrás" ya no regresa a las pantallas de la sesión cerrada, y el inicio de sesión queda como
    // primera pantalla.
    abrirComoPrimera('/inicio-de-sesion');
  }

  return (
    <Boton variante="secundario" ocupado={cerrando} onPress={cerrarSesion}>
      Cerrar sesión
    </Boton>
  );
}

const estilos = StyleSheet.create({
  // Las entradas a la cuenta, en una línea; si no caben, bajan a la siguiente.
  cuenta: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    columnGap: espaciado[4],
  },
  // El mapa, o el aviso en su lugar, en una tarjeta que ocupa el alto que queda.
  tarjetaDelMapa: {
    flex: 1,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  resumen: {
    paddingHorizontal: espaciado[4],
    paddingVertical: espaciado[3],
    borderTopWidth: 1,
    borderTopColor: color.semantico.borde,
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
});
