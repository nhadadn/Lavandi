// Lo que comparten las pruebas de navegador de la portada.

/** Las 12 secciones del A.2, en su orden (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2), como las marca data-seccion. */
export const SECCIONES = [
    'encabezado',
    'como-funciona',
    'beneficios-usuarios',
    'descarga',
    'lavanderias',
    'beneficios-socios',
    'cobertura',
    'testimonios',
    'preguntas-frecuentes',
    'prensa',
    'contacto',
    'pie-legal',
];

/** Los dos anchos de las pruebas: escritorio y teléfono. */
export const ANCHOS = {
    escritorio: { width: 1280, height: 800 },
    telefono: { width: 390, height: 844 },
};
