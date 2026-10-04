#!/bin/sh
set -e

# Link storage if not linked
php artisan storage:link --force || true

# Clear & optimize cache
php artisan config:clear
php artisan cache:clear

# Execute main command
exec "$@"
