#!/bin/sh
set -e

# Link storage if not linked
php artisan storage:link --force || true

# Clear & optimize cache
php artisan config:clear || true
php artisan cache:clear || true

# Run database migrations & seeder in background so web server starts instantly
(php artisan migrate --force && php artisan db:seed --class=InitialAdminSeeder --force) &

# Execute main web server command immediately
exec "$@"


