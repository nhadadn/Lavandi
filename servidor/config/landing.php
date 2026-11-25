<?php

/*
|--------------------------------------------------------------------------
| El contenido de la landing
|--------------------------------------------------------------------------
|
| NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; ADR-0011; openspec/changes/landing/.
|
| Los textos y las imágenes de la portada son de EL CLIENTE (NSV-2026-001-v8.pdf:6:Cláusula III, inciso B; DD-117) y
| entran aquí como contenido, sin programar (NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7). Mientras no lleguen, cada
| uno va vacío y la portada muestra en su lugar un marcador de contenido de prueba, marcado como tal: nunca se
| inventa un texto, una promesa, un precio ni una cifra. Los testimonios y los logos de prensa se editan en el panel
| (ADR-0011).
|
| Los títulos de las secciones, los cuatro pasos y los títulos de los seis pilares son los del contrato y viven en la
| vista. El nombre del producto es APP_NAME (config/app.php); el WhatsApp es el de config/soporte.php.
|
*/

return [

    /*
     * El logo, como ruta dentro de public/. Por omisión, el símbolo aprobado por EL CLIENTE (C-63), en un archivo de
     * nombre neutro, como en la app (C-237): su versión de 192 píxeles, el triple de lo más que se muestra (64), que es
     * lo que pide la pantalla de un teléfono (medicion-E11). El original, de 1024, sigue en marca/logotipo.png.
     */
    'logo' => env('LANDING_LOGO', 'marca/logotipo-192.png'),

    /*
     * Sección 1, "Hero / Encabezado": el tagline y la imagen con el mockup de la app, como ruta dentro de public/, con
     * su texto alternativo. El mockup necesita pantallas terminadas de la app (landing-E04).
     */
    'encabezado' => [
        'tagline' => env('LANDING_TAGLINE'),
        'imagen' => env('LANDING_IMAGEN_ENCABEZADO'),
        'imagen_texto' => env('LANDING_IMAGEN_ENCABEZADO_TEXTO', ''),
    ],

    /*
     * Sección 2, "¿Cómo funciona?": el texto bajo cada uno de los cuatro pasos del contrato, Pide → Recogemos → Lavamos
     * → Entregamos (landing-E05).
     */
    'como_funciona' => [
        'pide' => null,
        'recogemos' => null,
        'lavamos' => null,
        'entregamos' => null,
    ],

    /*
     * Sección 3, "Beneficios para usuarios": el texto de cada uno de los tres pilares del contrato (landing-E06). El
     * de precio no publica cifras mientras DD-010 siga abierta.
     */
    'beneficios_usuarios' => [
        'comodidad' => null,
        'precio' => null,
        'confianza' => null,
    ],

    /*
     * Sección 4, "Descarga la app": el enlace de cada tienda y el texto de su botón mientras no lo tenga (landing-E08 y
     * landing-E09). Los enlaces existen hasta que la app esté publicada (publicacion); solo se publica uno que sea una
     * dirección http o https. Qué dice el botón mientras tanto lo decide EL CLIENTE (DD-117).
     */
    'tiendas' => [
        'app_store' => [
            'enlace' => env('LANDING_APP_STORE_ENLACE'),
            'texto_sin_enlace' => env('LANDING_APP_STORE_TEXTO_SIN_ENLACE'),
        ],
        'google_play' => [
            'enlace' => env('LANDING_GOOGLE_PLAY_ENLACE'),
            'texto_sin_enlace' => env('LANDING_GOOGLE_PLAY_TEXTO_SIN_ENLACE'),
        ],
    ],

    /*
     * La imagen con el mockup de pantalla de "Descarga la app", como ruta dentro de public/, con su texto alternativo.
     */
    'descarga' => [
        'imagen' => env('LANDING_IMAGEN_DESCARGA'),
        'imagen_texto' => env('LANDING_IMAGEN_DESCARGA_TEXTO', ''),
    ],

    /*
     * Sección 5, "Lavanderías partner": el texto de la invitación a unirse (landing-E10).
     */
    'lavanderias' => [
        'invitacion' => env('LANDING_INVITACION_LAVANDERIAS'),
    ],

    /*
     * Sección 6, "Beneficios para socios": el texto de cada uno de los tres pilares del contrato (landing-E11). El de
     * cero gestión de cobranza promete que la plataforma cobra por el socio: no se escribe hasta que DD-013 cierre.
     */
    'beneficios_socios' => [
        'mas_clientes' => null,
        'pagos_seguros' => null,
        'cero_gestion_de_cobranza' => null,
    ],

    /*
     * Sección 7, "Cobertura": la tolerancia, en grados, con la que PostGIS simplifica el polígono de cada zona antes de
     * mandarlo a la portada. 0.0005 grados son unos 55 metros: no cambia la forma a la escala del mapa y quita los
     * vértices de más (landing-E12; App\Landing\ZonasDeLaCobertura).
     */
    'cobertura' => [
        'simplificacion' => (float) env('LANDING_COBERTURA_SIMPLIFICACION', 0.0005),
    ],

    /*
     * Sección 8, "Testimonios": los testimonios se editan en el panel (ADR-0011). Aquí va solo el texto del marcador de
     * posición que completa tres tarjetas mientras haya menos de tres activos (landing-E16); lo decide EL CLIENTE, para
     * que no se lea como una opinión real (DD-117).
     */
    'testimonios' => [
        'marcador' => env('LANDING_TESTIMONIO_MARCADOR'),
    ],

    /*
     * Sección 9, "Preguntas frecuentes": la ruta del archivo JSON con las preguntas y sus respuestas, absoluta o relativa
     * a la carpeta del servidor, como lista de {"pregunta": "…", "respuesta": "…"}. Se muestran a lo más ocho
     * (landing-E20; App\Landing\PreguntasFrecuentes).
     */
    'preguntas_frecuentes' => env('LANDING_PREGUNTAS_FRECUENTES'),

    /*
     * Sección 10, "Prensa / Reconocimientos": los logos de medios o menciones se editan en el panel, con su interruptor
     * de activo y su orden (ADR-0011). Aquí va solo el texto que muestra la sección mientras no haya ningún logo activo,
     * porque la sección no se oculta (landing-E18); lo decide EL CLIENTE, como los logos (DD-117).
     */
    'prensa' => [
        'sin_logos' => env('LANDING_PRENSA_SIN_LOGOS'),
    ],

    /*
     * Sección 11, "Contacto & Soporte": el correo de contacto, que confirma EL CLIENTE (DD-117). El número de WhatsApp
     * Business es el de config/soporte.php (SOPORTE_WHATSAPP, DD-082), el mismo del soporte de la app (landing-E23).
     */
    'contacto' => [
        'correo' => env('LANDING_CORREO_CONTACTO'),
    ],

    /*
     * Sección 12, "Footer legal": el titular y el año del aviso de derechos de autor (landing-E25). Quién aparece como
     * titular y desde cuándo es DD-120: los derechos patrimoniales pasan a EL CLIENTE con el pago íntegro
     * (NSV-2026-001-v8.pdf:10:Cláusula IX). Los enlaces al aviso de privacidad y a los términos son las rutas de
     * textos-legales (config/textos_legales.php).
     */
    'derechos' => [
        'titular' => env('LANDING_DERECHOS_TITULAR'),
        'anio' => env('LANDING_DERECHOS_ANIO'),
    ],

    /*
     * La descripción para buscadores de cada página pública, por su nombre (medicion-E07;
     * openspec/changes/medicion-de-la-landing/). Los textos los aprueba EL CLIENTE (DD-117). Mientras no lleguen, fuera de
     * producción cada página lleva un marcador de contenido de prueba como descripción; en producción no lleva ninguna,
     * porque no se inventa (App\Medicion\DescripcionParaBuscadores). El título de cada página es el nombre del producto,
     * y el de los documentos legales y el de la página del borrado de la cuenta llevan además su nombre.
     */
    'descripciones' => [
        'portada' => env('LANDING_DESCRIPCION_PORTADA'),
        'aviso-de-privacidad' => env('LANDING_DESCRIPCION_AVISO_DE_PRIVACIDAD'),
        'terminos' => env('LANDING_DESCRIPCION_TERMINOS'),
        // La página para pedir el borrado de la cuenta (textos-legales-E06; adenda del 2026-11-24 de ADR-0005).
        'borrado-de-cuenta' => env('LANDING_DESCRIPCION_BORRADO_DE_CUENTA'),
    ],

];
