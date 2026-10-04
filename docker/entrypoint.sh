#!/bin/sh
set -e

# Run database migrations & seeder safely on startup
php artisan migrate --force || true
php artisan db:seed --class=InitialAdminSeeder --force || true

# Link storage if not linked
php artisan storage:link --force || true

# Clear & optimize cache
php artisan config:clear || true
php artisan cache:clear || true

# Execute main command
exec "$@"

