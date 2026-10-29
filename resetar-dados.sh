#!/bin/sh
cd "$(dirname "$0")"
[ -f .env ] || { cp .env.example .env; php artisan key:generate --force; }
[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate:fresh --seed --force
echo "Dados de demonstração recriados."
