// La configuración de prueba del servidor de las pruebas de navegador: las variables de entorno de config/landing.php,
// config/soporte.php y config/medicion.php con valores inventados. Ninguno es de EL CLIENTE (DD-117, DD-082) ni de
// ninguna cuenta de medición.
export const CONFIGURACION_DE_PRUEBA = {
    // Las siete preguntas de landing-E20.
    LANDING_PREGUNTAS_FRECUENTES: 'tests/Browser/Soporte/preguntas-frecuentes-de-prueba.json',

    // El contenedor de etiquetas de prueba de medicion-E03 a medicion-E05: las pruebas sirven en su lugar
    // Soporte/contenedor-de-prueba.js, así que el identificador no corresponde a ningún contenedor real.
    MEDICION_GTM: 'GTM-PRUEBA1',

    // Los enlaces de las tiendas, a una dirección de prueba que las pruebas de medición interceptan (medicion-E04).
    LANDING_APP_STORE_ENLACE: 'https://tienda.prueba.test/app-store',
    LANDING_GOOGLE_PLAY_ENLACE: 'https://tienda.prueba.test/google-play',
};
