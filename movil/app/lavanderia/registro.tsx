// El registro de la lavandería, del Módulo B (openspec/changes/alta-de-lavanderias, alta-lavanderias-E03;
// NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1: "Nombre, dirección, foto, servicios, precios y horario").
// La app lo abre en lugar de los pedidos a la cuenta de socio lavandería que todavía no tiene lavandería
// (app/lavanderia/_layout.tsx). La persona llena los datos, toma una foto o la elige de su galería, y envía: la app
// comprime la foto con los parámetros de configuración (ADR-0006) y manda el registro en multipart
// (POST /api/socio/lavanderias). Si el servidor rechaza un dato, lo muestra junto a su campo, y la dirección con su
// motivo (alta-lavanderias-E10). Con el registro guardado, la lavandería pasa a sus pedidos.
//
// Mientras manda, el botón se bloquea: un doble toque no manda dos registros. Si la cuenta ya tenía lavandería (409),
// sin escenario propio, la vuelve a leer y abre sus pedidos. Sin conexión o con el servidor caído, sin escenario
// propio: los mensajes generales de la app, y lo escrito se queda. Qué estado tiene la lavandería recién registrada y
// si espera el alta del panel es DD-053 (alta-lavanderias-E11 y E12): el servidor la deja en `pendiente`.
//
// La composición es la de la imagen b01-registro de la Propuesta 04: el título a la izquierda y los datos por secciones;
// "Enviar registro" y "Cerrar sesión" abajo, fijos, como en las demás primeras pantallas de un módulo.
import { useRef, useState } from 'react';

import { registrarLavanderia } from '../../src/alta-lavanderias/api';
import {
  datosDe,
  erroresDelEnvio,
  formularioVacio,
  type ErroresDelFormulario,
  type Formulario,
} from '../../src/alta-lavanderias/formulario';
import { FormularioDeLaLavanderia } from '../../src/alta-lavanderias/FormularioDeLaLavanderia';
import { useLavanderiaDeLaCuenta } from '../../src/alta-lavanderias/ProveedorDeLaLavanderia';
import { TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO as TEXTOS } from '../../src/alta-lavanderias/textos';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { Titulo } from '../../src/piezas/Titulo';
import { CerrarSesion } from '../../src/sesion/CerrarSesion';
import { useSesion } from '../../src/sesion/ProveedorDeSesion';

const SIN_ERRORES: ErroresDelFormulario = { campos: {} };

export default function RegistroDeLaLavanderia() {
  const { token } = useSesion();
  const { poner, leerDeNuevo } = useLavanderiaDeLaCuenta();
  const [formulario, setFormulario] = useState<Formulario>(formularioVacio);
  const [errores, setErrores] = useState<ErroresDelFormulario>(SIN_ERRORES);
  const [enviando, setEnviando] = useState(false);
  // Ataja el segundo toque antes de que el botón se bloquee.
  const enCamino = useRef(false);

  async function enviar() {
    if (!token || enCamino.current) {
      return;
    }
    enCamino.current = true;
    setEnviando(true);
    setErrores(SIN_ERRORES);
    const datos = datosDe(formulario);
    const resultado = await registrarLavanderia(token, datos);
    enCamino.current = false;
    setEnviando(false);
    if (resultado.ok) {
      // Con su lavandería, el módulo abre sus pedidos (app/lavanderia/_layout.tsx).
      poner(resultado.datos);
      return;
    }
    setErrores(erroresDelEnvio(resultado, datos));
    if (resultado.motivo === 'conflicto') {
      leerDeNuevo();
    }
  }

  return (
    <Pantalla
      desplazable
      acciones={
        <>
          <Boton ocupado={enviando} onPress={() => void enviar()}>
            {TEXTOS.enviarRegistro}
          </Boton>
          <CerrarSesion />
        </>
      }
    >
      <Titulo alineacion="inicio">{TEXTOS.registrar}</Titulo>
      <Parrafo alineacion="inicio">{TEXTOS.paraRecibirPedidos}</Parrafo>
      <FormularioDeLaLavanderia formulario={formulario} alCambiar={setFormulario} errores={errores.campos} />
      <MensajeDeError texto={errores.general} />
    </Pantalla>
  );
}
