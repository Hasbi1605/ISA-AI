#!/usr/bin/env sh
set -eu

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data bootstrap/cache storage || true

for cache_file in packages.php services.php; do
    if [ ! -f "bootstrap/cache/${cache_file}" ] && [ -f "/usr/local/share/ista-bootstrap-cache/${cache_file}" ]; then
        cp "/usr/local/share/ista-bootstrap-cache/${cache_file}" "bootstrap/cache/${cache_file}"
    fi
done

php artisan storage:link >/dev/null 2>&1 || true
php artisan view:clear >/dev/null 2>&1 || true

exec "$@"
