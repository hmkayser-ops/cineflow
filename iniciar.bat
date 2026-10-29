@echo off
chcp 65001 >nul
cd /d "%~dp0"
title CineFlow
where php >nul 2>nul
if errorlevel 1 (
  echo PHP nao encontrado. Instale o Laravel Herd: https://herd.laravel.com
  pause
  exit /b 1
)
if not exist .env (
  copy .env.example .env >nul
  php artisan key:generate --force
)
if not exist database\database.sqlite (
  type nul > database\database.sqlite
  php artisan migrate --seed --force
)
echo.
echo CineFlow rodando em http://127.0.0.1:8000  (feche esta janela para parar)
echo.
start "" http://127.0.0.1:8000
php artisan serve --host=127.0.0.1 --port=8000
