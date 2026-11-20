#!/bin/bash
# Al empezar cada sesión de Claude Code: traer lo último del repo y revisar el vault.
# No instala nada; el proyecto todavía no tiene código de producto.
# Nunca falla la sesión: si algo no se puede, lo avisa y sigue.
set -uo pipefail

cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0

# Solo al empezar o reanudar; en /clear y en compactación no hace falta.
fuente="$(cat 2>/dev/null | python3 -c 'import json,sys; print(json.load(sys.stdin).get("source",""))' 2>/dev/null || true)"
case "$fuente" in clear|compact) exit 0 ;; esac

echo "== Inicio de sesión: sincronizar el vault =="
echo "ANTES DE TRABAJAR: pregunta quién está en esta sesión (Nadir Hadad Navarrete o Arthur Naranjo) y anótalo en su tarea. Sin respuesta no se asienta nada grado C (CLAUDE.md §0-bis, C-220)."

if git fetch --quiet origin 2>/dev/null; then
  rama="$(git rev-parse --abbrev-ref HEAD)"
  # La rama actual se actualiza solo si es avance rápido y no hay cambios sin guardar.
  if git rev-parse --verify --quiet "@{u}" >/dev/null && [ -z "$(git status --porcelain)" ]; then
    git merge --ff-only --quiet "@{u}" 2>/dev/null \
      && echo "Rama $rama al día con origin." \
      || echo "AVISO: $rama y origin se separaron; hay que unirlas a mano."
  fi
  atras="$(git rev-list --count HEAD..origin/main 2>/dev/null || echo 0)"
  if [ "$atras" -gt 0 ]; then
    echo "AVISO: main tiene $atras commit(s) que esta rama no tiene. Actualiza con: git pull --rebase origin main"
  fi
else
  echo "AVISO: no se pudo consultar origin. Trabajas sobre la copia local."
fi

if ls vault/meta/trabajo/*.md >/dev/null 2>&1; then
  echo "Trabajo en curso (vault/meta/trabajo/):"
  grep -l "^situacion: en-curso" vault/meta/trabajo/*.md 2>/dev/null | while read -r f; do
    echo "  - $(basename "$f" .md): $(grep -m1 '^dueño:' "$f" | cut -d: -f2- | xargs) · rama $(grep -m1 '^rama:' "$f" | cut -d: -f2- | xargs)"
  done
fi

python3 herramientas/validar-vault/validar.py || echo "AVISO: el vault tiene errores; corrígelos antes de abrir un PR."
exit 0
