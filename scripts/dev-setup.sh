#!/bin/bash

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate
fi

if [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite
fi

php artisan migrate
php artisan optimize:clear

echo "Dev environment ready."

