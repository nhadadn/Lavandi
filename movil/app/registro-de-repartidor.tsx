// El registro del repartidor, del Módulo C (openspec/changes/repartidor, repartidor-E01 y E02;
// NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #1: "Registro del repartidor con nombre, teléfono, correo, foto de
// perfil y tipo de vehículo"). Va sin sesión. La persona escribe sus datos y su contraseña (ADR-0005: correo y contraseña
// para todos los roles), toma su foto o la elige de su galería, y envía: la app comprime la foto con los parámetros de
// configuración (ADR-0006) y manda el registro en multipart (POST /api/repartidor/registro). Los cinco datos son
// obligatorios (design.md, lectura 4): el servidor dice cuál falta o no vale, y la app lo muestra junto a su campo.
//
// Con el registro guardado, el servidor abre la sesión, como el registro del usuario, y la app entra al módulo del
// repartidor, que muestra que su registro espera la autorización (app/repartidor/espera.tsx): el alta corresponde a EL
// CLIENTE, en el panel (repartidor-E05). Mientras manda, el botón se bloquea. Sin conexión, sin escenario propio: los
// mensajes generales de la app, y lo escrito se queda.
//
// Cómo llega la persona a esta pantalla desde la bienvenida o el inicio de sesión es DD-053 (repartidor-E04): hoy no
// tiene enlace. "Regresar" lleva al inicio de sesión. Con la sesión abierta no se ve (app/_layout.tsx).
//
// La composición es la de la imagen c01-registro de la Propuesta 04: el título, "Tus datos" con la foto de perfil arriba,
// y "Enviar registro". La contraseña no está en el mock: la pide ADR-0005. No tiene componente en el design system
// (design.md, Open Questions): se arma con las piezas y los tokens (ADR-0013).
import { useState } from 'react';

import { useEntrada } from '../src/acceso/useEntrada';
import { useRegresar } from '../src/navegacion/regresar';
import { Boton } from '../src/piezas/Boton';
import { CampoDeFoto } from '../src/piezas/CampoDeFoto';
import { CampoDeTexto, type TipoDeCampo } from '../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../src/piezas/MensajeDeError';
import { Pantalla } from '../src/piezas/Pantalla';
import { Parrafo } from '../src/piezas/Parrafo';
import { TituloDeSeccion } from '../src/piezas/TituloDeSeccion';
import { CAMPOS_DEL_REGISTRO, registrarRepartidor, type DatosDelRegistro } from '../src/repartidor/api';
import { TEXTOS_DEL_REPARTIDOR as TEXTOS } from '../src/repartidor/textos';

type DatoDeTexto = Exclude<keyof DatosDelRegistro, 'foto'>;

// Los datos de texto, en el orden de la imagen c01-registro, con la contraseña después del correo.
const CAMPOS: { campo: DatoDeTexto; etiqueta: string; tipo: TipoDeCampo }[] = [
  { campo: 'nombre', etiqueta: TEXTOS.nombre, tipo: 'texto' },
  { campo: 'telefono', etiqueta: TEXTOS.telefono, tipo: 'telefono' },
  { campo: 'correo', etiqueta: TEXTOS.correo, tipo: 'correo' },
  { campo: 'password', etiqueta: TEXTOS.contrasena, tipo: 'contrasena-nueva' },
  { campo: 'tipo_vehiculo', etiqueta: TEXTOS.tipoDeVehiculo, tipo: 'texto' },
];

const VACIO: Record<DatoDeTexto, string> = { nombre: '', telefono: '', correo: '', password: '', tipo_vehiculo: '' };

export default function RegistroDelRepartidor() {
  const regresar = useRegresar('/inicio-de-sesion');
  const { enviando, errorGeneral, errorDe, entrarCon } = useEntrada(CAMPOS_DEL_REGISTRO);
  const [datos, setDatos] = useState(VACIO);
  const [foto, setFoto] = useState<string | null>(null);

  function enviar() {
    if (enviando) {
      return;
    }
    void entrarCon(() => registrarRepartidor({ ...datos, foto: foto ? { uri: foto } : null }));
  }

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.registro, alRegresar: regresar }}>
      <Parrafo alineacion="inicio">{TEXTOS.queSigue}</Parrafo>
      <TituloDeSeccion>{TEXTOS.tusDatos}</TituloDeSeccion>
      <CampoDeFoto
        nombre="foto"
        forma="circulo"
        textos={{
          titulo: TEXTOS.fotoDePerfil,
          laFoto: TEXTOS.tuFotoDePerfil,
          tomarFoto: TEXTOS.tomarFoto,
          elegirDeLaGaleria: TEXTOS.elegirDeLaGaleria,
          sinPermisoDeLaCamara: TEXTOS.sinPermisoDeLaCamara,
          fotoNoPreparada: TEXTOS.fotoNoPreparada,
        }}
        foto={foto}
        alCambiar={setFoto}
        error={errorDe('foto')}
        deshabilitado={enviando}
      />
      {CAMPOS.map(({ campo, etiqueta, tipo }) => (
        <CampoDeTexto
          key={campo}
          nombre={campo}
          etiqueta={etiqueta}
          tipo={tipo}
          valor={datos[campo]}
          alCambiar={(texto) => setDatos((anteriores) => ({ ...anteriores, [campo]: texto }))}
          error={errorDe(campo)}
        />
      ))}
      <MensajeDeError texto={errorGeneral} />
      <Boton ocupado={enviando} onPress={enviar}>
        {TEXTOS.enviarRegistro}
      </Boton>
    </Pantalla>
  );
}
