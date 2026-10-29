#!/bin/sh
cd "$(dirname "$0")"
command -v php >/dev/null 2>&1 || { echo "PHP não encontrado. Instale o Laravel Herd: https://herd.laravel.com"; exit 1; }
[ -f .env ] || { cp .env.example .env; php artisan key:generate --force; }
[ -f database/database.sqlite ] || { touch database/database.sqlite; php artisan migrate --seed --force; }
echo ""
echo "CineFlow rodando em http://127.0.0.1:8000  (Ctrl + C para parar)"
echo ""
( sleep 2; (open http://127.0.0.1:8000 || xdg-open http://127.0.0.1:8000) >/dev/null 2>&1 ) &
php artisan serve --host=127.0.0.1 --port=8000
