#!/usr/bin/env sh
set -eu

host="${LARAVEL_SERVER_HOST:-0.0.0.0}"
port="${LARAVEL_SERVER_PORT:-8000}"

cd public

exec php -S "${host}:${port}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
