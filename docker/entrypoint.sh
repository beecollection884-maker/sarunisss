#!/bin/sh
set -e

# Link storage if not linked
php artisan storage:link --force || true

# Clear & optimize cache
php artisan config:clear || true
php artisan cache:clear || true

# Run database migrations & seeder
php artisan migrate --force || true
php artisan db:seed --class=InitialAdminSeeder --force || true

# Execute main web server command immediately
exec "$@"



