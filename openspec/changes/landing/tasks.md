Cada tarea sigue rojo, verde y refactor: primero el test con el identificador del escenario en su nombre, que falla;
luego el código mínimo que lo pone en verde; luego la limpieza. Todas viven en `servidor/`.

Horas con las bandas de S-006 (S = 2, M = 5): **39 h**, contra **30 h** del plan para F-L01 a F-L12 (10 S y 2 M del
inventario). La diferencia sale de las pruebas en tres navegadores, del panel de testimonios y de la acreditación. De
las 39 h, 4 h están bloqueadas (tareas 5.2 y 7.1).

Con el modelo de datos (ADR-0016) las horas no cambian: este cambio no crea tablas del modelo, así que no lleva tarea de
migración. Las tareas 4.1 y 4.2 esperan a que las tablas de testimonios y de logos de prensa entren a
`vault/arquitectura/modelo-de-datos.md` (`design.md`, "Tablas y migración"); cuando entren, se agrega su tarea de
migración antes de la 4.1.

## 1. Portada y estructura

- [ ] 1.1 landing-E01, landing-E02: ruta pública de la portada y vista Blade con las variables CSS de los tokens y las 12 secciones en su orden, con contenido de prueba — test: `servidor/tests/Browser/Landing/DoceSeccionesTest.php` (Chromium, WebKit y Firefox; escritorio y teléfono) — tamaño M

## 2. Secciones de contenido fijo

- [ ] 2.1 landing-E04: encabezado con nombre y logo de la configuración, tagline, imagen y botón que lleva a "Descarga la app" — test: `servidor/tests/Feature/Landing/EncabezadoTest.php` — tamaño S
- [ ] 2.2 landing-E05, landing-E06, landing-E07: paso a paso, tres pilares para usuarios e iconos animados que se quedan quietos con movimiento reducido — test: `servidor/tests/Feature/Landing/ComoFuncionaYBeneficiosTest.php` y `servidor/tests/Browser/Landing/MovimientoReducidoTest.php` — tamaño S
- [ ] 2.3 landing-E08, landing-E09: botones de App Store y Google Play con enlaces de la configuración, sin enlaces vacíos — test: `servidor/tests/Feature/Landing/BotonesDeDescargaTest.php` — tamaño S
- [ ] 2.4 landing-E10, landing-E11: invitación a lavanderías con el formulario y los tres pilares para socios — test: `servidor/tests/Feature/Landing/LavanderiasTest.php` — tamaño S

## 3. Cobertura

- [ ] 3.1 landing-E12, landing-E14: lectura de las zonas activas de las plazas activas, con la geometría simplificada, para la portada — test: `servidor/tests/Feature/Landing/CoberturaTest.php` — tamaño S
- [ ] 3.2 landing-E12, landing-E13: mapa interactivo con polígonos propios (acercar, alejar y nombre de la zona), sin peticiones a mapas de pago — test: `servidor/tests/Browser/Landing/MapaDeCoberturaTest.php` — tamaño M

## 4. Contenido editable desde el panel

- [ ] 4.1 landing-E15, landing-E16, landing-E19: testimonios en Filament con interruptor de activo y orden; de 3 a 5 tarjetas con marcadores de posición; edición solo con permiso de administración (espera: la tabla de testimonios en `modelo-de-datos.md`) — test: `servidor/tests/Feature/Landing/TestimoniosTest.php` — tamaño M
- [ ] 4.2 landing-E17, landing-E18: logos de prensa activables y la sección presente sin logos activos (espera: la tabla de logos de prensa en `modelo-de-datos.md`) — test: `servidor/tests/Feature/Landing/PrensaTest.php` — tamaño S
- [ ] 4.3 landing-E20: preguntas frecuentes en acordeón, con toque y con teclado — test: `servidor/tests/Browser/Landing/PreguntasFrecuentesTest.php` — tamaño S

## 5. Formularios y contacto

- [ ] 5.1 landing-E21: validación de los formularios de interés y de contacto — test: `servidor/tests/Feature/Landing/FormulariosTest.php` — tamaño S
- [ ] 5.2 landing-E22: entrega de cada formulario a su destino y confirmación (bloqueada: DD-119, BR-41) — test: `servidor/tests/Feature/Landing/FormulariosTest.php` — tamaño S
- [ ] 5.3 landing-E23: correo y WhatsApp Business tomados de la configuración — test: `servidor/tests/Feature/Landing/ContactoTest.php` — tamaño S

## 6. Pie legal

- [ ] 6.1 landing-E24, landing-E25: pie en cada página pública con los enlaces al aviso y a los términos y el aviso de derechos de autor de la configuración — test: `servidor/tests/Feature/Landing/PieLegalTest.php` — tamaño S

## 7. Acreditación

- [ ] 7.1 landing-E03: las 12 secciones con el contenido de EL CLIENTE en Chrome, Safari y Firefox reales, con capturas en `vault/evidencias/landing/` (bloqueada: DD-117, DD-023) — test: `servidor/tests/Browser/Landing/DoceSeccionesTest.php` con el contenido final — tamaño S
