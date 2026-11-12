#!/bin/sh
# Prepara o ambiente e sobe o CineFlow. Use: ./iniciar.sh
cd "$(dirname "$0")" || exit 1
command -v python3 >/dev/null 2>&1 || { echo "Instale o Python 3 em python.org"; exit 1; }
[ -d .venv ] || python3 -m venv .venv
./.venv/bin/pip install --quiet --disable-pip-version-check -r requirements.txt
[ -f cineflow.db ] || ./.venv/bin/python dados_demo.py
echo ""
echo "CineFlow em http://127.0.0.1:5000  (Ctrl + C para parar)"
echo ""
( sleep 2; open http://127.0.0.1:5000 >/dev/null 2>&1 ) &
./.venv/bin/python app.py
