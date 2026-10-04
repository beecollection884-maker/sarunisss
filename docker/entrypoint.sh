#!/bin/sh
set -e

php artisan storage:link --force || true

exec "$@"




