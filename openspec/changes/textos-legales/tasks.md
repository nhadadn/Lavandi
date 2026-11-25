Cada tarea sigue rojo, verde y refactor: primero el test con el identificador del escenario en su nombre, que falla;
luego el código mínimo que lo pone en verde; luego la limpieza.

Horas con las bandas de S-006 (S = 2): **10 h**, contra **2 h** del plan (F-T18, S, del inventario). De las 10 h, 2 h
están bloqueadas (tarea 1.3). La tarea 1.4 suma 2 h, de la adenda del 2026-11-24 de ADR-0005.

## 1. Sitio

- [x] 1.1 textos-legales-E01, textos-legales-E02: páginas públicas del aviso de privacidad y de los términos con el documento cargado, sin texto de ejemplo en producción — test: `servidor/tests/Feature/TextosLegales/PaginasPublicasTest.php` — tamaño S
- [x] 1.2 textos-legales-E04: reemplazo de un documento por su versión nueva sin tocar el código, con la misma dirección — test: `servidor/tests/Feature/TextosLegales/NuevaVersionTest.php` — tamaño S
- [ ] 1.3 textos-legales-E03: carga en producción de los documentos de EL CLIENTE, con captura en `vault/evidencias/textos-legales/` (bloqueada: DD-080) — test: `servidor/tests/Feature/TextosLegales/PaginasPublicasTest.php` con la configuración de producción — tamaño S
- [x] 1.4 textos-legales-E06, textos-legales-E07: la página pública para pedir el borrado de la cuenta — test: `servidor/tests/Feature/TextosLegales/BorradoDeCuentaTest.php` — tamaño S

## 2. App

- [x] 2.1 textos-legales-E05: enlaces de la app al aviso y a los términos con las direcciones de la configuración — test: `movil/__tests__/textos-legales/enlaces-legales.test.tsx` — tamaño S
