#!/bin/bash
# Al terminar cada turno de Claude: el vault no queda con cambios sin guardar ni con errores.
# Si los hay, devuelve el turno a Claude para que ejecute la skill cerrar-sesion (C-207).
# Bloquea solo una vez seguida, para no entrar en bucle.
set -uo pipefail
cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0
# En Windows (Git Bash) no hay python3: se usa python. PYTHONIOENCODING, para los acentos.
PY="$(command -v python3 || command -v python || echo python3)"
export PYTHONIOENCODING=utf-8

entrada="$(cat 2>/dev/null || true)"
activo="$(printf '%s' "$entrada" | "$PY" -c 'import json,sys; print(json.load(sys.stdin).get("stop_hook_active", False))' 2>/dev/null || echo False)"
[ "$activo" = "True" ] && exit 0

motivos=()
if [ -n "$(git status --porcelain -- vault CLAUDE.md .claude herramientas 2>/dev/null)" ]; then
  motivos+=("hay cambios sin commit en el vault o en la configuración del proyecto")
fi
if ! salida="$("$PY" herramientas/validar-vault/validar.py 2>&1)"; then
  motivos+=("el vault no valida: $(printf '%s' "$salida" | head -5 | tr '\n' ' ')")
fi

if [ "${#motivos[@]}" -gt 0 ]; then
  razon="Antes de terminar, ejecuta la skill cerrar-sesion: $(IFS=';'; echo "${motivos[*]}")."
  "$PY" -c 'import json,sys; print(json.dumps({"decision": "block", "reason": sys.argv[1]}, ensure_ascii=False))' "$razon"
fi
exit 0
