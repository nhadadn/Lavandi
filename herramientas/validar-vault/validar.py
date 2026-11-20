"""Validación del vault antes de unir un cambio.

Falla (código de salida 1) si:
  1. una nota de vault/ no tiene el frontmatter obligatorio (estado, grado, fuente, fecha, dueño)
     o su estado no es borrador | vigente | superseded;
  2. un enlace [[nota]] no apunta a ninguna nota del vault;
  3. un identificador C-NN o DD-NNN está definido dos veces (dos ramas que tomaron el mismo número).

Uso: python herramientas/validar-vault/validar.py  (desde la raíz del repo)
Los errores que ya existían están en excepciones.txt y no hacen fallar.
Sin dependencias fuera de la biblioteca estándar.
"""
import pathlib
import re
import sys
from collections import defaultdict

RAIZ = pathlib.Path(__file__).resolve().parents[2]
VAULT = RAIZ / "vault"
CAMPOS = ("estado", "grado", "fuente", "fecha", "dueño")
ESTADOS = {"borrador", "vigente", "superseded"}
EXENTAS = {"README.md"}  # notas de carpeta que explican el contenido, sin valor de evidencia


def notas():
    for p in sorted(VAULT.rglob("*.md")):
        if any(parte.startswith(".") for parte in p.relative_to(VAULT).parts):
            continue
        yield p


def frontmatter(texto):
    if not texto.startswith("---\n"):
        return None
    fin = texto.find("\n---", 4)
    if fin < 0:
        return None
    campos = {}
    for linea in texto[4:fin].splitlines():
        if ":" in linea and not linea.startswith((" ", "\t")):
            clave, valor = linea.split(":", 1)
            campos[clave.strip()] = valor.strip()
    return campos


def main():
    errores = []
    lista = list(notas())
    nombres = {p.stem for p in lista}
    nombres |= {p.stem for p in VAULT.rglob("*") if p.is_file()}
    definiciones = defaultdict(list)

    for p in lista:
        rel = p.relative_to(RAIZ)
        texto = p.read_text(encoding="utf-8")
        if p.name not in EXENTAS and "plantillas" not in p.parts:
            fm = frontmatter(texto)
            if fm is None:
                errores.append(f"{rel}: sin frontmatter")
            else:
                faltan = [c for c in CAMPOS if c not in fm]
                if faltan:
                    errores.append(f"{rel}: faltan campos {', '.join(faltan)}")
                elif fm["estado"] not in ESTADOS:
                    errores.append(f"{rel}: estado «{fm['estado']}» no es borrador | vigente | superseded")
        sin_codigo = re.sub(r"```.*?```|`[^`\n]*`", "", texto, flags=re.S)
        for destino in re.findall(r"\[\[([^\]|#]+)", sin_codigo):
            destino = destino.strip()
            if pathlib.PurePosixPath(destino).stem not in nombres:
                errores.append(f"{rel}: enlace roto [[{destino}]]")
        for n, linea in enumerate(texto.splitlines(), 1):
            m = re.match(r"^- (C-\d+)(?:\.| \()", linea)
            if m and "actas" in p.parts:
                definiciones[m.group(1)].append(f"{rel}:{n}")
            m = re.match(r"^\|\s*(DD-\d+)\s*\|", linea)
            if m and p.name == "deuda-de-decision.md":
                definiciones[m.group(1)].append(f"{rel}:{n}")
        if "deuda" in p.parts and p.parent.name == "deuda" and re.match(r"DD-\d+$", p.stem):
            definiciones[p.stem].append(str(rel))

    for ident, lugares in sorted(definiciones.items()):
        if len(lugares) > 1:
            errores.append(f"{ident} definido {len(lugares)} veces: {' · '.join(lugares)}")

    archivo = pathlib.Path(__file__).with_name("excepciones.txt")
    conocidos = set()
    if archivo.exists():
        conocidos = {l.strip() for l in archivo.read_text(encoding="utf-8").splitlines()
                     if l.strip() and not l.startswith("#")}
    nuevos = [e for e in errores if e not in conocidos]
    for e in nuevos:
        print(e)
    resueltos = conocidos - set(errores)
    for e in sorted(resueltos):
        print(f"Ya corregido, bórralo de excepciones.txt: {e}")
    print(f"\n{len(lista)} notas revisadas, {len(nuevos)} errores nuevos, "
          f"{len(conocidos & set(errores))} conocidos en excepciones.txt.")
    return 1 if nuevos or resueltos else 0


if __name__ == "__main__":
    sys.exit(main())
