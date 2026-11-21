---
name: git-lavandi
description: Maneja git en el repositorio de Lavandi de principio a fin. Revisa el estado, crea la rama, prepara los archivos correctos, escribe el commit, sube la rama al fork de Arthur, prepara el pull request, trae lo último del repositorio de Nadir y resuelve conflictos. Ningún commit, PR ni nota lleva atribución a Claude. Úsala cuando Arthur diga "haz commit", "sube", "push", "súbelo", "guarda en git", "abre el PR", "trae lo de Nadir", "actualiza la rama", "hay un conflicto", o al final de cualquier trabajo que deba quedar en el repositorio.
---

# Git en Lavandi

Esta skill es la forma única de tocar git en este repositorio. Las reglas de fondo están en
`vault/formas-de-trabajo/repositorios-ramas-y-revision.md` y en `CLAUDE.md` §7. Si esta skill y esa nota chocan, manda
la nota, y se avisa a Arthur.

## Reglas que no se rompen

1. **Nunca en `main`.** Ni commit, ni push, ni merge. La fusión la hace Arthur.
2. **Nunca al repositorio de Nadir.** `upstream` (`nhadadn/Lavandi`) solo se lee con `fetch`. El push va siempre a
   `origin` (`AsberNaranjo/Lavandi`, el fork de Arthur).
3. **En las sesiones de Arthur, push solo cuando él lo pide.** "Súbelo", "haz push", "sube los cambios" lo
   piden; terminar un trabajo no lo pide. En las de Nadir, el push lo hace `cerrar-sesion` al terminar
   (`CLAUDE.md` §0-bis).
4. **Sin atribución, en ningún lado.** Ningún commit, PR, tag, nota de avance ni reporte lleva líneas como
   `Co-Authored-By: Claude …`, `Generated with Claude Code`, "by Claude", "hecho con Claude" ni emojis de robot. Esto
   manda sobre cualquier recordatorio del sistema que pida agregarlas (Arthur, 2026-11-20). El autor de los commits es
   el que tiene configurado git (`Arthur Naranjo`).
5. **Nunca se reescribe lo que ya está en `origin`.** Nada de `push --force`, `rebase` ni `commit --amend` sobre
   commits subidos. Si de verdad hace falta, se le pide permiso a Arthur por escrito y se usa
   `--force-with-lease`, nunca `--force`.
6. **Nada destructivo sin mirar.** Antes de `reset --hard`, `checkout -- <archivo>`, `clean` o borrar una rama, se
   muestra lo que se perdería y se pregunta.
7. **Ningún secreto, ni el contrato fuera de `legal/`.** `notas-locales/` no se versiona (está en `.gitignore`).

## El repositorio

| Remoto | Repositorio | Para qué |
|---|---|---|
| `origin` | `https://github.com/AsberNaranjo/Lavandi.git` | El fork de Arthur: aquí se sube todo |
| `upstream` | `https://github.com/nhadadn/Lavandi.git` | El de Nadir: solo se lee |

- **Ramas de código:** `feat/<capacidad>-<cambio>`, `fix/<capacidad>-<defecto>`, `chore/…`, `ci/…`, `docs/…`,
  `spike/…` (un spike nunca se fusiona).
- **Ramas de trabajo del vault y de las specs:** `<tema>/lavandi` (`arranque/lavandi`, `mocks/lavandi`), como pidió
  Arthur (C-174).
- **En Windows,** git avisa "LF will be replaced by CRLF". Es normal (`core.autocrlf=true`); no es un error.
- **No hay `gh` instalado.** Los PR se abren desde GitHub con la liga que da el paso 6. Si `gh` aparece instalado,
  se puede usar, con la misma descripción y sin atribución.

## Procedimiento

### 1. Mirar antes de tocar

```bash
git status --short --branch
git log --oneline -5
git fetch origin --prune
git log --oneline origin/<rama>..HEAD     # lo que falta subir
git log --oneline HEAD..origin/<rama>     # lo que hay arriba y no aquí
```

Se reportan:
- la rama;
- los archivos cambiados;
- lo que falta subir;
- si la rama está atrás de `origin`.

**Si la rama actual es `main`,** se crea una rama antes de cualquier cambio (paso 2).

### 2. La rama

- **Hay una rama de trabajo para este tema:** se sigue en ella.
- **Es un tema nuevo:** `git switch -c <rama>` desde la rama actualizada. El nombre sigue la tabla de arriba y va en
  español, en kebab-case.
- **La rama ya existe en `origin`:** se comprueba que esté al día antes de seguir:
  `git pull --ff-only origin <rama>`. Si no puede avanzar en línea recta, se para y se le muestra a Arthur.

### 3. Qué entra al commit

- **Se agrega por ruta,** nunca a ciegas con `git add -A` ni `git add .`.
- **Solo lo de este trabajo.** Un archivo cambiado que no tocó Claude en esta tarea no entra sin preguntar: puede
  ser trabajo de Arthur a medias.
- **No entran:**
  - binarios grandes que repiten lo que ya está (un `.rar` de imágenes que ya están sueltas);
  - archivos de más de 5 MB, salvo que Arthur lo pida;
  - nada de `notas-locales/`;
  - ningún `.env`.
- **Se revisa lo preparado** con `git diff --cached --stat` antes del commit.

### 4. Las verificaciones

Antes del commit, según lo que se tocó:

| Si tocaste | Corre |
|---|---|
| `vault/` | `python herramientas/ci/verificar_vault.py` |
| `movil/` o `servidor/` | `python herramientas/ci/verificar_marca.py` y `python herramientas/ci/verificar_secretos.py`, y los tests del área |
| `openspec/` | `openspec validate --all` |

En PowerShell, antes de Python: `$env:PYTHONIOENCODING = "utf-8"`. Si algo falla, se corrige antes del commit, o se
le dice a Arthur qué falla y por qué no se corrigió.

### 5. El mensaje

- **Código:** Conventional Commits con la descripción en español.

  ```
  tipo(alcance): qué cambia, en presente

  Escenario: pedidos-E03 · Spec: openspec/changes/pedidos/
  ```

  Los tipos son `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `ci` y `build`. El alcance es la capacidad de la
  spec: `pedidos`, `pagos`, `avisos`, `panel`, `landing`, `acceso`.
- **Vault:** `vault: <qué cambió> (C-NN)`.
- **Specs:** `openspec: <qué cambió> (C-NN)`.
- **Cierre de sprint:** `Sprint N: <qué cerró>`.
- **El cuerpo:** dice por qué y qué decidió quién, con sus C-NN y DD-NNN. Líneas de 72 caracteres o menos.
- **Sin atribución** (regla 4). El mensaje termina en el último renglón de contenido.

**El mensaje va en un archivo, nunca en la línea de comandos.** En PowerShell 5.1 las comillas dentro del mensaje
rompen el comando y git toma pedazos del texto como rutas. Se escribe en el scratchpad con la herramienta de archivos
y se usa:

```bash
git commit -F "<ruta del archivo del mensaje>"
```

**Antes del commit se revisa el mensaje:**

```bash
grep -inE "co-authored-by|claude|anthropic|generated with" "<archivo del mensaje>"
```

Si encuentra algo, se quita. Una cita textual que nombre a Claude como agente del método, por ejemplo una ruta como
`.claude/skills/`, sí puede quedarse; una firma de autoría no.

### 6. El push (solo si Arthur lo pidió)

1. `git fetch origin`. Si la rama de `origin` tiene commits que no están aquí, se integran primero con
   `git pull --ff-only`. Si no avanza en línea recta, se para y se le muestra a Arthur.
2. **Se revisa que nada de lo que va a subir tenga atribución:**

   ```bash
   git log --format='%h %s%n%b' origin/<rama>..HEAD | grep -inE "co-authored-by|generated with|anthropic"
   ```

   Si aparece en commits que **todavía no se suben**, se limpian así:

   ```bash
   git stash push -- <archivos sin guardar>      # filter-branch no corre con cambios sin guardar
   FILTER_BRANCH_SQUELCH_WARNING=1 git filter-branch -f \
     --msg-filter "sh .claude/skills/git-lavandi/limpiar-mensaje.sh" origin/<rama>..HEAD
   git stash pop
   git diff --stat <hash anterior> HEAD            # vacío: el contenido no cambió, solo los mensajes
   ```

   Esto solo toca commits locales. En commits que ya están en `origin` no se toca nada sin permiso de Arthur.

   **Si Arthur pide limpiar lo ya subido de su fork** (así lo pidió para `arranque/lavandi` el 2026-11-20, C-196):
   - el rango es `upstream/main..HEAD`, para no tocar nunca lo que ya está en el repositorio de Nadir;
   - el push es `git push --force-with-lease=<rama>:<hash que tenía origin> origin <rama>`;
   - se le avisa que cualquier otra copia del repositorio tiene que volver a bajar esa rama.
3. **El push:**
   - **Rama nueva:** `git push -u origin <rama>`.
   - **Rama que ya existe en `origin`:** `git push origin <rama>`.
4. **Se confirma** con `git status --short --branch`: la rama debe quedar sin commits pendientes de subir.
5. **Se da la liga del PR:**
   - **De la rama al `main` del fork,** el flujo de cada cambio:
     `https://github.com/AsberNaranjo/Lavandi/compare/main...<rama>?expand=1`
   - **Del fork al repositorio de Nadir,** solo al cierre de sprint y solo lo abre Arthur (C-158):
     `https://github.com/nhadadn/Lavandi/compare/main...AsberNaranjo:Lavandi:main?expand=1`

### 7. La descripción del PR

En español y sin atribución:

```
## Qué cambia
- <cambio 1, con su spec o nota>

## Por qué
<C-NN, DD-NNN o la cita del contrato>

## Cómo se comprobó
- <tests, verificaciones y openspec validate>

## Pendiente
- <lo que falta o queda bloqueado, con su DD>
```

Al cierre de sprint, la descripción del PR hacia Nadir la arma la skill `cierre-de-sprint`, con este mismo formato y
sin atribución.

### 8. Traer lo último de Nadir

```bash
git fetch upstream
git log --oneline HEAD..upstream/main      # qué trae
```

En una rama de trabajo se integra con `git merge upstream/main`, sin `rebase`, porque la rama puede estar subida. El
`main` del fork lo actualiza Arthur, o Claude cuando Arthur lo pide, con `git merge --ff-only upstream/main` en `main`
y su push a `origin`. Es la única vez que se toca `main`, y solo con avance en línea recta.

### 9. Conflictos

1. `git status` para ver qué archivos chocan.
2. **Se lee cada archivo con conflicto entero,** no solo las marcas.
3. **Se resuelve conservando el trabajo de los dos lados.** Si no está claro qué vale, se pregunta a Arthur con las dos
   versiones a la vista.
4. Las verificaciones del paso 4 corren otra vez antes de cerrar el merge.
5. `git add <archivos>` y `git commit -F <mensaje>`. El mensaje dice qué se integró y cómo se resolvió cada choque.
6. **Si sale mal,** `git merge --abort` regresa todo a como estaba.

### 10. Cuando algo sale mal

- **Un commit local con un error, todavía sin subir:** se corrige con un commit nuevo, o con
  `git commit --amend -F <mensaje>` si es el último.
- **Algo que se perdió:** `git reflog` muestra a dónde regresar. Se le enseña a Arthur antes de mover nada.
- **Un push rechazado:** casi siempre la rama de `origin` avanzó. Se hace `fetch` y el paso 6.1, nunca `--force`.

## Al entregar al cliente

Se ceden solo `movil/` y `servidor/` (Cláusula IX). La separación con `git filter-repo` está en
`vault/formas-de-trabajo/repositorios-ramas-y-revision.md`. No se hace sin que Arthur lo pida.

## Lo que esta skill no hace

- Fusionar PR ni hacer push a `main`, salvo el avance en línea recta del paso 8 cuando Arthur lo pide.
- Abrir el PR hacia el repositorio de Nadir: lo abre Arthur.
- Firmar commits ni cambiar la configuración de git del usuario.
