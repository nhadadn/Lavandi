Cada tarea sigue rojo, verde y refactor: primero el test con el identificador del escenario en su nombre, que falla;
luego el código mínimo que lo pone en verde; luego la limpieza. Todas viven en `servidor/`.

Horas con las bandas de S-006 (S = 2, M = 5): **25 h**, contra **7 h** del plan (F-T16, M, y F-T17, S, del inventario).
De las 25 h, 6 h son de acreditación y están bloqueadas (tareas 1.3, 2.2 y 3.3).

## 1. Medición

- [x] 1.1 medicion-E01, medicion-E02: contenedor de GTM en el diseño base de las páginas públicas, con el identificador de la configuración, y nada de medición sin identificador — test: `servidor/tests/Feature/Medicion/ContenedorDeEtiquetasTest.php` — tamaño S
- [x] 1.2 medicion-E03, medicion-E04, medicion-E05: eventos de visita, de descarga y de formulario enviado, sin datos personales, interceptados hacia GA4 y Meta Pixel con un contenedor de prueba — test: `servidor/tests/Browser/Medicion/Eventos.spec.js` (Chromium, WebKit y Firefox) y `servidor/tests/Feature/Medicion/EventosDeLaPortadaTest.php` — tamaño M
- [ ] 1.3 medicion-E06: los eventos en el tiempo real de GA4 y en el administrador de eventos de Meta, en las cuentas de EL CLIENTE (bloqueada: DD-118, DD-023) — test: `servidor/tests/Browser/Medicion/EventosTest.php` contra producción, con capturas en `vault/evidencias/medicion/` — tamaño S

## 2. Buscadores

- [x] 2.1 medicion-E07, medicion-E08, medicion-E10: título y descripción por página, mapa del sitio y archivo de robots, e información estructurada con las plazas activas — test: `servidor/tests/Feature/Medicion/BuscadoresTest.php` — tamaño M
- [ ] 2.2 medicion-E09: verificación por DNS y envío del mapa del sitio en la Search Console de EL CLIENTE (bloqueada: DD-023, DD-118) — test: `servidor/tests/Feature/Medicion/BuscadoresTest.php` con el dominio de producción, más la captura — tamaño S

## 3. Velocidad y candado

- [x] 3.1 medicion-E11: imágenes optimizadas para teléfono, GTM en diferido y Lighthouse CI con umbral de 0.70 en rendimiento — test: `servidor/lighthouserc.json`, corrido por la integración continua en el trabajo `landing-velocidad`, más `servidor/tests/Feature/Medicion/VelocidadTest.php` y `servidor/tests/Browser/Medicion/Velocidad.spec.js` — tamaño M
- [x] 3.2 medicion-E13: redirección permanente de http a https en la portada, con el dominio de la configuración — test: `servidor/tests/Feature/Medicion/CandadoTest.php` — tamaño S
- [ ] 3.3 medicion-E12, medicion-E14: PageSpeed Insights y candado sobre `lavandi.mx` publicado, con capturas (bloqueada: DD-023, DD-070) — test: Lighthouse con `servidor/lighthouserc.json` contra la dirección publicada, más las capturas — tamaño S
