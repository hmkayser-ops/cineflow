@echo off
cd /d "%~dp0"
if not exist .env (
  copy .env.example .env >nul
  php artisan key:generate --force
)
if not exist database\database.sqlite type nul > database\database.sqlite
php artisan migrate:fresh --seed --force
echo Dados de demonstracao recriados.
pause
