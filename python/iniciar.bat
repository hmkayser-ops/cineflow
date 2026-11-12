@echo off
cd /d "%~dp0"
where python >nul 2>&1 || (echo Instale o Python 3 em python.org & pause & exit /b)
if not exist .venv python -m venv .venv
.venv\Scripts\pip install --quiet --disable-pip-version-check -r requirements.txt
if not exist cineflow.db .venv\Scripts\python dados_demo.py
echo.
echo CineFlow em http://127.0.0.1:5000  (Ctrl + C para parar)
echo.
start http://127.0.0.1:5000
.venv\Scripts\python app.py
