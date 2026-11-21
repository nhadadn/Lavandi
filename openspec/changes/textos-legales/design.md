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

## Tablas y migración

No crea tablas ni migraciones, y no lee ni escribe ninguna tabla de `vault/arquitectura/modelo-de-datos.md` (ADR-0016).

- `usuarios.terminos_aceptados_en` nace en `registro-e-inicio-de-sesion`. La llena el registro si EL CLIENTE pide la
  constancia de aceptación (registro-E29, bloqueado por DD-080). Este cambio no la toca, tampoco al cargar una versión
  nueva (textos-legales-E04).
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
   ADR-0011 o decida con ADR-0006.**
2. **En qué pantallas de la app aparecen los enlaces.** El consentimiento va en el registro (F-A02). Si además van en el
   perfil (F-A15) o en otra pantalla, no lo dice el contrato: **pregunta para Arthur.** No se diseña aquí.
3. **Cómo los abre la app.** Recomendación: en el navegador del sistema, igual que el checkout redirigido de la
   pasarela (ADR-0010: Conekta; otra, si el cliente firma el cambio, DD-079), sin dependencia nueva. Una vista dentro de la app también cumple la spec. Lo decide Arthur.
4. **El formato en que llegan los documentos** (Word, PDF o texto) y si hay **un solo documento de términos o uno por
   tipo de usuario**: el B.7 dice que los términos regulan la relación "con usuarios, socios lavandería y repartidores".
   Se propone agregarlo a DD-080.
5. **Las direcciones.** Propuesta: `/aviso-de-privacidad` y `/terminos`. El A.2 dice "términos de servicio" y el B.7
   "términos y condiciones"; se toma como el mismo documento salvo que EL CLIENTE diga otra cosa (DD-080).
