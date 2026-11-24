## Context

- EL PRESTADOR solo incorpora los documentos que entregue EL CLIENTE (`NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7`).
  EL CLIENTE es el responsable de los datos y debe "Elaborar, publicar y mantener actualizado el aviso de privacidad"
  (`NSV-2026-001-v8.pdf:10:Cláusula X`).
- El B.10 pone el aviso publicado en la semana 12 del calendario, antes del envío a las tiendas
  (`NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.10`). No ha llegado (DD-080).
- Mientras tanto, el registro de la app usa el enlace como dato de configuración
  (`vault/formas-de-trabajo/reparto-de-tareas.md`; DD-080).
- Las páginas las sirve el mismo Laravel que la landing (ADR-0011, sobre ADR-0003). La app es la de ADR-0002.

## Goals / Non-Goals

**Goals:**

- Que el aviso y los términos tengan direcciones fijas y públicas que puedan declararse en las tiendas, en el pie de la
  landing y en el registro de la app.
- Que una versión nueva del cliente entre sin programar, como "Ajustes menores de contenido" del A.7
  (`NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7`).

**Non-Goals:**

- Redactar o revisar los documentos (B.7).
- El consentimiento del registro (`registro-e-inicio-de-sesion`).
- Atender derechos ARCO o llevar un registro de consentimientos (Cláusula X: son de EL CLIENTE).

## Decisions

No hay decisión técnica nueva: el cambio aplica ADR-0002, ADR-0003 y ADR-0011. Lo que sí hay que decidir está abajo.

### Cómo quedó implementado (tarea 1.1, 2026-11-22)

Las decisiones que abajo quedaban para Arthur las tomó Claude por delegación de Arthur (C-236), con su recomendación
escrita aquí. Ninguna es una decisión técnica nueva: aplican ADR-0003, ADR-0006 y ADR-0011, sin editarlos, y no suman
dependencias.

1. **El texto vigente es un archivo en el almacenamiento del entorno (pregunta abierta 1), con ADR-0006.** Cada
   documento es un archivo de texto UTF-8 (`aviso-de-privacidad.txt` y `terminos.txt`) en un disco privado:
   `textos_legales_s3` en producción, la carpeta `textos-legales` del bucket privado del entorno, y
   `textos_legales_local` fuera de producción, `storage/app/private/textos-legales`, que no se versiona
   (`servidor/config/textos_legales.php` y `config/filesystems.php`). El servidor lee el archivo y lo muestra en la
   página: el archivo nunca se sirve directo, así que el bucket sigue privado, como pide ADR-0006. Un archivo que
   cambia no toca el código ni pide desplegar (textos-legales-E04).
   - Por qué no el recurso de Filament: necesita una tabla que no está en `modelo-de-datos.md` y que ADR-0011 amplíe lo
     editable más allá de testimonios y prensa. El archivo no necesita ninguna de las dos.
   - Por qué no un archivo del repositorio: cambiarlo exige desplegar, y E04 lo prohíbe.
2. **El documento se muestra como texto, tal cual (pregunta abierta 4, en lo que toca al sitio).** Lo que entregue EL
   CLIENTE, en Word, PDF o texto, se guarda como su texto, sin redactarlo. La página lo escapa y conserva sus saltos
   de línea y sus espacios (`white-space: pre-wrap`): ninguna marca de HTML se interpreta. Si DD-080 pide publicar el
   PDF tal cual, o un documento de términos por tipo de usuario, entra como cambio sobre esta spec.
3. **Las direcciones son `/aviso-de-privacidad` y `/terminos` (pregunta abierta 5),** como se propuso, con las rutas
   `textos-legales.aviso-de-privacidad` y `textos-legales.terminos` (`servidor/routes/web.php`). Los "términos de
   servicio" del A.2 se toman como los "términos y condiciones" del B.7 mientras DD-080 no diga otra cosa; el título de
   cada página es el nombre que le da el B.7.
4. **Sin el documento cargado:** en producción, la página responde 200 con "Este documento todavía no está
   disponible." y ningún texto de ejemplo ni de prueba (textos-legales-E02). Fuera de producción, un marcador
   "Contenido de prueba: …", marcado con `data-de-prueba` como los de la portada. Un archivo vacío o solo con espacios
   cuenta como no cargado.
5. **El diseño:** el de la portada (`publico.layout`, los tokens de ADR-0013), con un encabezado mínimo que lleva el
   nombre y el logo de la configuración de vuelta a la portada. El pie legal lo pone `landing` (tarea 6.1).

### Una versión nueva (tarea 1.2, 2026-11-22)

6. **Se carga con un comando, sin pantalla:** `php artisan textos-legales:cargar <documento> <archivo>`, con
   `aviso-de-privacidad` o `terminos` y el archivo de texto UTF-8 de la versión nueva
   (`servidor/app/Console/Commands/CargarTextoLegal.php`). Deja el archivo tal cual en el almacenamiento del entorno, en
   lugar del anterior, y la misma dirección lo muestra desde ese momento (textos-legales-E04). Un documento que no
   existe, un archivo que no se lee, vacío o que no está en UTF-8 no se carga, y la versión publicada se queda. Es la
   misma forma de cargar datos sin programar que `plazas:cargar`; el Módulo D no enumera una pantalla para esto
   (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`). Por decisión de Claude, por delegación de Arthur (C-236).
   - No guarda las versiones anteriores: el sitio publica solo la vigente, y llevar el historial de los avisos es de EL
     CLIENTE como responsable de los datos (`NSV-2026-001-v8.pdf:10:Cláusula X`). Si el bucket del entorno tiene
     versiones activadas, ahí quedan.
   - Para cargar en producción (textos-legales-E03, bloqueada por DD-080) se corre en el servidor de producción, con su
     `.env`.

### Las direcciones, para la app y las tiendas

- **Aviso de privacidad:** `GET /aviso-de-privacidad`, ruta `textos-legales.aviso-de-privacidad`.
- **Términos y condiciones:** `GET /terminos`, ruta `textos-legales.terminos`.
- Las dos cuelgan de `APP_URL` del entorno: en producción, `https://lavandi.mx/aviso-de-privacidad` y
  `https://lavandi.mx/terminos` cuando el dominio esté activo; en local, `http://localhost:8000/aviso-de-privacidad` y
  `http://localhost:8000/terminos` con `php artisan serve`. Se abren sin sesión, responden 200 y no cambian con una
  versión nueva: son las que se declaran en las fichas de las tiendas (`publicacion`) y las que enlaza el pie de la
  landing.
- **La app las toma de su configuración, no del código** (textos-legales-E05, tarea 2.1):
  `EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL` y `EXPO_PUBLIC_TERMINOS_URL`, de `movil/.env.example`, que ya lee
  `movil/src/configuracion/entorno.ts` (`direccionDelAvisoDePrivacidad()` y `direccionDeLosTerminos()`) y que usa el
  registro. En cada entorno valen la dirección completa de arriba con el `APP_URL` del
  servidor de ese entorno. El servidor no da una ruta de la API para ellas: no hace falta.

### Los enlaces de la app (tarea 2.1, 2026-11-22)

Las decisiones que quedaban para Arthur en las preguntas abiertas 2 y 3 las tomó Claude por delegación de Arthur (C-236),
con su recomendación escrita aquí. No suman dependencias: `expo-web-browser` ya entró con la tarea 4.1 de `pago`.

7. **Dónde van (pregunta abierta 2):** en el registro, donde ya estaban (registro-E28), y en "Mi perfil" (F-A15), en la
   sección "Legal" del mock A15, debajo de las direcciones guardadas y antes de "Eliminar mi cuenta". Por qué también en
   el perfil: el B.7 pide incorporar los documentos a la plataforma, y sin el perfil la persona solo los ve antes de
   tener cuenta. No van en otra pantalla. Cada enlace aparece solo si su dirección está configurada; sin ninguna, el
   perfil no muestra la sección.
8. **Cómo se abren (pregunta abierta 3):** en la vista de navegador del sistema dentro de la app, con `openBrowserAsync`
   de `expo-web-browser`, como el checkout del pago (adenda del 2026-11-22 de ADR-0010): la persona lee el documento y,
   al cerrarlo, sigue donde estaba. Antes, el registro los abría fuera de la app con `Linking.openURL`; ahora los dos
   lugares usan la misma pieza, `movil/src/textos-legales/EnlacesLegales.tsx`, y la prueba de registro-E28 comprueba lo
   mismo con la vista nueva. Si la vista no se abre, la pantalla sigue como estaba.
9. **Las direcciones** son las de `EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL` y `EXPO_PUBLIC_TERMINOS_URL`, que en cada entorno
   valen el `APP_URL` de su servidor seguido de `/aviso-de-privacidad` y `/terminos` (`movil/.env.example`). La app no
   escribe ninguna: lo comprueban registro-E28 y textos-legales-E05.

## Tablas y migración

No crea tablas ni migraciones, y no lee ni escribe ninguna tabla de `vault/arquitectura/modelo-de-datos.md` (ADR-0016).

- La constancia de aceptación no tiene columna: `usuarios.terminos_aceptados_en`, que nació en
  `registro-e-inicio-de-sesion`, la quitó la migración `2026_11_26_300000_quitar_del_esquema_lo_que_no_tiene_respaldo`, porque llevar un registro de
  consentimientos es de EL CLIENTE (Non-Goals; Cláusula X). Si DD-080 la pide (registro-E29), entra con su propia
  migración. Este cambio no la toca, tampoco al cargar una versión nueva (textos-legales-E04).
- El texto vigente de cada documento no tiene tabla en el modelo. Si la pregunta abierta 1 se resuelve con un archivo en
  el almacenamiento (ADR-0006), no hace falta ninguna. Si se resuelve con un recurso de Filament, la tabla tiene que
  entrar antes a `modelo-de-datos.md` y su migración pasará la prueba de esquema de `modelo-de-datos` (modelo-E01 a
  modelo-E16); no llevaría el trigger `impedir_cambio()`.

## Risks / Trade-offs

- [Sin el aviso publicado no se envía a las tiendas] → la Cláusula IV prevé ese caso: si la publicación se atrasa por
  "la falta del aviso de privacidad o de los términos y condiciones", el hito del pago 2 se acredita con la app en el
  ambiente de prueba (`NSV-2026-001-v8.pdf:7:Cláusula IV`).
- [Que un borrador o un texto de ejemplo se publique como si fuera el aviso] → textos-legales-E02.
- [Que la dirección cambie después de declararla en las tiendas] → se fija desde la primera versión
  (textos-legales-E04).

## Open Questions

1. **Dónde se guarda el texto vigente.** Un archivo dentro del repositorio no cumple textos-legales-E04, porque cambiarlo
   exige desplegar. Las opciones son un recurso de Filament, editable por la administración, o un archivo en el
   almacenamiento (ADR-0006). ADR-0011 solo hace editables los testimonios y la prensa: **necesita que Arthur amplíe
   ADR-0011 o decida con ADR-0006.** *Resuelta el 2026-11-22, por delegación de Arthur (C-236):* un archivo en el
   almacenamiento privado del entorno, con ADR-0006 ("Cómo quedó implementado", punto 1).
2. **En qué pantallas de la app aparecen los enlaces.** El consentimiento va en el registro (F-A02). Si además van en el
   perfil (F-A15) o en otra pantalla, no lo dice el contrato: **pregunta para Arthur.** No se diseña aquí. *Resuelta el
   2026-11-22, por delegación de Arthur (C-236):* en el registro y en "Mi perfil" ("Los enlaces de la app", punto 7).
3. **Cómo los abre la app.** Recomendación: en el navegador del sistema, igual que el checkout redirigido de la
   pasarela (ADR-0010: Conekta; otra, si el cliente firma el cambio, DD-079), sin dependencia nueva. Una vista dentro de la app también cumple la spec. Lo decide Arthur. *Resuelta el 2026-11-22, por delegación de Arthur (C-236):* en la vista de navegador del sistema dentro de la app, como el checkout ("Los enlaces de la app", punto 8).
4. **El formato en que llegan los documentos** (Word, PDF o texto) y si hay **un solo documento de términos o uno por
   tipo de usuario**: el B.7 dice que los términos regulan la relación "con usuarios, socios lavandería y repartidores".
   Se propone agregarlo a DD-080.
5. **Las direcciones.** Propuesta: `/aviso-de-privacidad` y `/terminos`. El A.2 dice "términos de servicio" y el B.7
   "términos y condiciones"; se toma como el mismo documento salvo que EL CLIENTE diga otra cosa (DD-080). *Resuelta el
   2026-11-22:* las de la propuesta ("Cómo quedó implementado", punto 3).
