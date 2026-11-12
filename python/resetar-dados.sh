#!/bin/sh
cd "$(dirname "$0")" || exit 1
[ -d .venv ] || python3 -m venv .venv
./.venv/bin/pip install --quiet --disable-pip-version-check -r requirements.txt
./.venv/bin/python dados_demo.py
