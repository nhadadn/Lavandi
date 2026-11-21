Cada tarea sigue rojo, verde y refactor: primero el test con el identificador del escenario en su nombre, que falla;
luego el código mínimo que lo pone en verde; luego la limpieza.

Horas con las bandas de S-006 (S = 2): **8 h**, contra **2 h** del plan (F-T18, S, del inventario). De las 8 h, 2 h
están bloqueadas (tarea 1.3).

## 1. Sitio

- [ ] 1.1 textos-legales-E01, textos-legales-E02: páginas públicas del aviso de privacidad y de los términos con el documento cargado, sin texto de ejemplo en producción — test: `servidor/tests/Feature/TextosLegales/PaginasPublicasTest.php` — tamaño S
- [ ] 1.2 textos-legales-E04: reemplazo de un documento por su versión nueva sin tocar el código, con la misma dirección — test: `servidor/tests/Feature/TextosLegales/NuevaVersionTest.php` — tamaño S
- [ ] 1.3 textos-legales-E03: carga en producción de los documentos de EL CLIENTE, con captura en `vault/evidencias/textos-legales/` (bloqueada: DD-080) — test: `servidor/tests/Feature/TextosLegales/PaginasPublicasTest.php` con la configuración de producción — tamaño S

## 2. App

- [ ] 2.1 textos-legales-E05: enlaces de la app al aviso y a los términos con las direcciones de la configuración — test: `movil/__tests__/textos-legales/enlaces-legales.test.tsx` — tamaño S
