#!/bin/sh
# Filtro de mensajes para git filter-branch --msg-filter: quita la atribución a la herramienta (C-195)
# y los renglones vacíos que quedan al final. Lee el mensaje por la entrada y lo escribe por la salida.
sed -E '/^Co-Authored-By: .*(Claude|Anthropic)/Id; /Generated with \[?Claude/Id' | sed -e :a -e '/^\n*$/{$d;N;ba' -e '}'
