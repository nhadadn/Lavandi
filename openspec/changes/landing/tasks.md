Cada tarea sigue rojo, verde y refactor: primero el test con el identificador del escenario en su nombre, que falla;
luego el código mínimo que lo pone en verde; luego la limpieza. Todas viven en `servidor/`.

Horas con las bandas de S-006 (S = 2, M = 5): **41 h**, contra **30 h** del plan para F-L01 a F-L12 (10 S y 2 M del
inventario). La diferencia sale de las pruebas en tres navegadores, del panel de testimonios, de la migración de
testimonios y de la acreditación. De las 41 h, 4 h están bloqueadas (tareas 5.2 y 7.1).

Con el modelo de datos (ADR-0016): la tabla de testimonios entró a `vault/arquitectura/modelo-de-datos.md` el
2026-11-22, con su tarea de migración, la 4.0, antes de la 4.1 (`design.md`, "Tablas y migración"). La 4.2 sigue
esperando la tabla de logos de prensa; cuando entre, se agrega su migración antes de ella.

Las pruebas de navegador son de Playwright desde Node (`design.md`, pregunta abierta 7): viven en
`servidor/tests/Browser/` como `*.spec.js` y se corren con `npx playwright test` desde `servidor/`.

## 1. Portada y estructura

- [x] 1.1 landing-E01, landing-E02: ruta pública de la portada y vista Blade con las variables CSS de los tokens y las 12 secciones en su orden, con contenido de prueba — test: `servidor/tests/Browser/Landing/DoceSecciones.spec.js` (Chromium, WebKit y Firefox; escritorio y teléfono) y `servidor/tests/Feature/Landing/PortadaTest.php` — tamaño M

## 2. Secciones de contenido fijo

- [x] 2.1 landing-E04: encabezado con nombre y logo de la configuración, tagline, imagen y botón que lleva a "Descarga la app" — test: `servidor/tests/Feature/Landing/EncabezadoTest.php` — tamaño S
- [x] 2.2 landing-E05, landing-E06, landing-E07: paso a paso, tres pilares para usuarios e iconos animados que se quedan quietos con movimiento reducido — test: `servidor/tests/Feature/Landing/ComoFuncionaYBeneficiosTest.php` y `servidor/tests/Browser/Landing/MovimientoReducido.spec.js` — tamaño S
- [x] 2.3 landing-E08, landing-E09: botones de App Store y Google Play con enlaces de la configuración, sin enlaces vacíos — test: `servidor/tests/Feature/Landing/BotonesDeDescargaTest.php` — tamaño S
- [x] 2.4 landing-E10, landing-E11: invitación a lavanderías con el formulario y los tres pilares para socios — test: `servidor/tests/Feature/Landing/LavanderiasTest.php` — tamaño S

## 3. Cobertura

- [x] 3.1 landing-E12, landing-E14: lectura de las zonas activas de las plazas activas, con la geometría simplificada, para la portada — test: `servidor/tests/Feature/Landing/CoberturaTest.php` — tamaño S
- [x] 3.2 landing-E12, landing-E13: mapa interactivo con polígonos propios (acercar, alejar y nombre de la zona), sin peticiones a mapas de pago — test: `servidor/tests/Browser/Landing/MapaDeCobertura.spec.js` — tamaño M

## 4. Contenido editable desde el panel

- [x] 4.0 Migración de `testimonios`, como entró a `modelo-de-datos.md` (sección "Landing"), en la lista `sin_plaza` de `config/modelo.php`, con su modelo y su fábrica de datos de prueba; base de landing-E15, landing-E16 y landing-E19 — test: `servidor/tests/Feature/Landing/MigracionTest.php` (modelo-E01 a modelo-E17 sobre la tabla) — tamaño S
- [x] 4.1 landing-E15, landing-E16, landing-E19 y landing-E26: testimonios en Filament con interruptor de activo, orden y borrado; de 3 a 5 tarjetas con marcadores de posición; edición solo con permiso de administración — test: `servidor/tests/Feature/Landing/TestimoniosTest.php` — tamaño M
- [ ] 4.2 landing-E17, landing-E18: logos de prensa activables y la sección presente sin logos activos (espera: la tabla de logos de prensa en `modelo-de-datos.md` y los logos de EL CLIENTE) — test: `servidor/tests/Feature/Landing/PrensaTest.php` — tamaño S
- [x] 4.3 landing-E20: preguntas frecuentes en acordeón, con toque y con teclado — test: `servidor/tests/Browser/Landing/PreguntasFrecuentes.spec.js` y `servidor/tests/Feature/Landing/PreguntasFrecuentesTest.php` — tamaño S

## 5. Formularios y contacto

- [x] 5.1 landing-E21: validación de los formularios de interés y de contacto — test: `servidor/tests/Feature/Landing/FormulariosTest.php` — tamaño S
- [ ] 5.2 landing-E22: entrega de cada formulario a su destino y confirmación (bloqueada: DD-119, BR-41) — test: `servidor/tests/Feature/Landing/FormulariosTest.php` — tamaño S
- [x] 5.3 landing-E23: correo y WhatsApp Business tomados de la configuración — test: `servidor/tests/Feature/Landing/ContactoTest.php` — tamaño S

## 6. Pie legal

- [x] 6.1 landing-E24, landing-E25: pie en cada página pública con los enlaces al aviso y a los términos y el aviso de derechos de autor de la configuración — test: `servidor/tests/Feature/Landing/PieLegalTest.php` — tamaño S

## 7. Acreditación

- [ ] 7.1 landing-E03: las 12 secciones con el contenido de EL CLIENTE en Chrome, Safari y Firefox reales, con capturas en `vault/evidencias/landing/` (bloqueada: DD-117, DD-023) — test: `servidor/tests/Browser/Landing/DoceSecciones.spec.js` con el contenido final — tamaño S
