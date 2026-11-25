// Los textos del registro de la lavandería y de su perfil y horarios (openspec/changes/alta-de-lavanderias), juntos para
// revisarlos en un solo lugar. Los títulos son los de las imágenes b01-registro y b06-perfil de la Propuesta 04. Los
// errores de cada dato los da el servidor (ReglasDeLaLavanderia), también los de la descripción y la lista de fotos;
// aquí viven los de la dirección por su motivo (alta-lavanderias-E10) y los de la foto, que pasan en el teléfono.
export const TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO = {
  // La entrada al módulo: mientras se lee la lavandería de la cuenta, o si no llega.
  leyendo: 'Cargando tu lavandería…',
  intentarDeNuevo: 'Intentar de nuevo',
  // El registro (alta-lavanderias-E03).
  registrar: 'Registrar lavandería',
  paraRecibirPedidos: 'Para recibir pedidos, registra tu lavandería.',
  enviarRegistro: 'Enviar registro',
  // El perfil y horarios (alta-lavanderias-E19).
  perfilYHorarios: 'Perfil y horarios',
  guardarCambios: 'Guardar cambios',
  cambiosGuardados: 'Guardamos los cambios.',
  // Las secciones del formulario.
  tuLavanderia: 'Tu lavandería',
  nombre: 'Nombre',
  direccion: 'Dirección',
  calle: 'Calle',
  numero: 'Número',
  colonia: 'Colonia',
  codigoPostal: 'Código postal',
  municipio: 'Municipio',
  elegirMunicipio: 'Elige el municipio',
  cargandoMunicipios: 'Cargando los municipios…',
  buscarMunicipio: 'Buscar municipio',
  // Sin escenario propio: como en la dirección del pedido (direcciones-E17), sin lista no hay texto libre.
  municipiosNoDisponibles: 'No pudimos cargar la lista de municipios. Intenta de nuevo.',
  foto: 'Foto',
  fotoDeLaLavanderia: 'Foto de la lavandería',
  tomarFoto: 'Tomar foto',
  elegirDeLaGaleria: 'Elegir de la galería',
  // Sin escenario propio: sin permiso de la cámara, la galería sigue sirviendo, porque no pide permiso.
  sinPermisoDeLaCamara: 'Sin permiso de la cámara no podemos tomar la foto. Puedes elegirla de tu galería.',
  // Sin escenario propio: la foto que no se pudo leer o comprimir.
  fotoNoPreparada: 'No pudimos preparar la foto. Intenta con otra.',
  // Solo en el perfil y horarios: la descripción y las fotos del perfil (alta-lavanderias-E26 a E30).
  descripcion: 'Descripción',
  fotos: 'Fotos',
  agregarFoto: 'Agregar foto',
  quitar: 'Quitar',
  moverAntes: 'Mover antes',
  serviciosYPrecios: 'Servicios y precios',
  ventanas: 'Ventanas de disponibilidad',
  // Las ventanas son de una hora (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7), en la hora de la plaza.
  instruccionDeLasVentanas: 'Marca, día por día, las horas en que puedes recolectar. Cada ventana dura una hora.',
  dia: 'Día',
  buscarDia: 'Buscar día',
} as const;

/** "Precio por kilo de lavado estándar": el precio de cada servicio, que el contrato pide por kilo (Módulo A #5). */
export function precioPorKiloDe(nombreDelServicio: string): string {
  return `Precio por kilo de ${nombreDelServicio.charAt(0).toLowerCase()}${nombreDelServicio.slice(1)}`;
}

/** "Foto 2 de la lavandería": cada foto de la lista del perfil, por su lugar, para el lector de pantalla. */
export function fotoDeLaLista(numero: number): string {
  return `Foto ${numero} de la lavandería`;
}

/** "Quitar la foto 2": el botón de cada foto de la lista, para el lector de pantalla (alta-lavanderias-E28). */
export function quitarLaFoto(numero: number): string {
  return `${TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO.quitar} la foto ${numero}`;
}

/** "Mover antes la foto 2": el botón de cada foto de la lista, para el lector de pantalla (alta-lavanderias-E29). */
export function moverAntesLaFoto(numero: number): string {
  return `${TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO.moverAntes} la foto ${numero}`;
}

/** Lo que dice la sección de fotos del perfil: cuál es la principal y el máximo que da el servidor (E29 y E30). */
export function instruccionDeLasFotos(maximo: number): string {
  return `La primera es la principal. Puedes tener hasta ${maximo}.`;
}

/** "Sin ventanas", "1 ventana", "3 ventanas": lo que tiene cada día, en la lista de días. */
export function cuantasVentanas(cuantas: number): string {
  if (cuantas === 0) {
    return 'Sin ventanas';
  }
  return cuantas === 1 ? '1 ventana' : `${cuantas} ventanas`;
}

// El aviso de la dirección por el motivo que da el servidor (alta-lavanderias-E10; design.md del cambio, "El contrato
// de la API"). Con un motivo que la app no conoce, el mensaje del servidor.
const MOTIVOS_DE_LA_DIRECCION: Record<string, string> = {
  fuera_de_la_zona_de_servicio: 'La dirección está fuera de las zonas de servicio.',
  direccion_no_encontrada: 'No encontramos la dirección. Revisa la calle, el número, la colonia y el código postal.',
  // La geocodificación no respondió (503): no se guardó nada.
  geocodificacion_no_disponible: 'No pudimos ubicar la dirección. Inténtalo más tarde.',
  // Solo al editar: la dirección cae en otra plaza y la lavandería no cambia de plaza (alta-lavanderias-E25).
  en_otra_plaza: 'La dirección nueva queda en otra plaza de servicio. Ese cambio todavía no se puede hacer.',
};

/** Si el motivo es de la dirección: la app lo muestra junto a ella. */
export function esMotivoDeLaDireccion(motivo: string | undefined): motivo is string {
  return motivo !== undefined && Object.prototype.hasOwnProperty.call(MOTIVOS_DE_LA_DIRECCION, motivo);
}

/** El aviso de la dirección por su motivo; sin uno que la app conozca, el mensaje del servidor. */
export function avisoDeLaDireccion(motivo: string | undefined, mensajeDelServidor: string | undefined): string | undefined {
  return esMotivoDeLaDireccion(motivo) ? MOTIVOS_DE_LA_DIRECCION[motivo] : mensajeDelServidor;
}
