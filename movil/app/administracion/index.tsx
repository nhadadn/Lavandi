// El inicio del Módulo D, la primera pantalla de la administración (administracion-app-E03): a donde entra su cuenta al
// iniciar sesión (administracion-app-E02). Lista las secciones como enlaces bajo el título, como los módulos de la
// lavandería y del repartidor mientras no exista una barra inferior (ADR-0022, decisión 11 y alternativa 4): el Módulo D
// en el orden del contrato, del #1 al #7, con el #6 en Ingresos y Conciliación (NSV-2026-001-v8.pdf:19:ANEXO A,
// Sección A.3), y después los indicadores y las descargas del A.5 (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5). Cada
// entrada abre su pantalla dentro de la app; cada una la pone su función.
//
// "Cerrar sesión" va abajo, con la confirmación de la app (registro-E13; administracion-app-E04). La composición es la
// de las imágenes 03-inicio-del-modulo y 56-inicio-sin-pendientes de la Propuesta 06 de los mocks: el título a la
// izquierda, las entradas en dos grupos, "Gestión" y "Estado del negocio", y dos conteos en su insignia, a la derecha de
// su entrada: junto a Repartidores, los pendientes de alta, en el color de atención de su estado; junto a Monitoreo de
// pedidos, los pedidos activos, en el de información. Sin pendientes, Repartidores va sin insignia (56). Los conteos los
// pide el inicio cada vez que queda a la vista, y si una consulta falla su entrada va sin insignia
// (src/administracion/useConteosDelInicio.ts; design.md del cambio, sección 5).
import { useRouter } from 'expo-router';
import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { RUTAS_DE_LAS_SECCIONES, type Seccion } from '../../src/administracion/rutas';
import { TEXTOS_DE_LA_ADMINISTRACION as TEXTOS } from '../../src/administracion/textos';
import { useConteosDelInicio } from '../../src/administracion/useConteosDelInicio';
import { Enlace } from '../../src/piezas/Enlace';
import { InsigniaDeEstado } from '../../src/piezas/InsigniaDeEstado';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Titulo } from '../../src/piezas/Titulo';
import { TituloDeSeccion } from '../../src/piezas/TituloDeSeccion';
import { CerrarSesion } from '../../src/sesion/CerrarSesion';
import { color, espaciado } from '../../src/tema/theme';

/** Las siete funciones del Módulo D, en su orden, con el #6 en Ingresos y Conciliación. */
const GESTION: readonly Seccion[] = [
  'lavanderias',
  'repartidores',
  'usuarios',
  'monitoreo',
  'comisiones',
  'ingresos',
  'conciliacion',
  'avisos',
];

/** Los indicadores del A.5 y su descarga. */
const ESTADO_DEL_NEGOCIO: readonly Seccion[] = ['indicadores', 'descargas'];

export default function InicioDeLaAdministracion() {
  const { pendientes, activos } = useConteosDelInicio();
  const insignias: Partial<Record<Seccion, ReactNode>> = {
    repartidores: pendientes ? (
      <Conteo seccion="repartidores" texto={TEXTOS.pendientes(pendientes)} tono={color.estado.atencion} />
    ) : null,
    monitoreo:
      activos === null ? null : (
        <Conteo seccion="monitoreo" texto={TEXTOS.activos(activos)} tono={color.estado.informacion} />
      ),
  };

  return (
    <Pantalla desplazable acciones={<CerrarSesion />}>
      <Titulo alineacion="inicio">{TEXTOS.titulo}</Titulo>
      <Grupo titulo={TEXTOS.gestion} secciones={GESTION} insignias={insignias} />
      <Grupo titulo={TEXTOS.estadoDelNegocio} secciones={ESTADO_DEL_NEGOCIO} insignias={insignias} />
    </Pantalla>
  );
}

function Grupo({
  titulo,
  secciones,
  insignias,
}: {
  titulo: string;
  secciones: readonly Seccion[];
  insignias: Partial<Record<Seccion, ReactNode>>;
}) {
  const router = useRouter();
  return (
    <View style={estilos.grupo}>
      <TituloDeSeccion>{titulo}</TituloDeSeccion>
      <View>
        {secciones.map((seccion) => (
          <View key={seccion} style={estilos.entrada}>
            <View style={estilos.enlace}>
              <Enlace texto={TEXTOS.secciones[seccion]} alTocar={() => router.push(RUTAS_DE_LAS_SECCIONES[seccion])} />
            </View>
            {insignias[seccion]}
          </View>
        ))}
      </View>
    </View>
  );
}

/** El conteo de una entrada, en la insignia de la app con su texto: el color nunca va solo. */
function Conteo({ seccion, texto, tono }: { seccion: Seccion; texto: string; tono: string }) {
  return <InsigniaDeEstado codigo={seccion} nombre={texto} tono={tono} testID={`conteo-${seccion}`} />;
}

const estilos = StyleSheet.create({
  grupo: {
    gap: espaciado[1],
  },
  entrada: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  enlace: {
    flexShrink: 1,
  },
});
