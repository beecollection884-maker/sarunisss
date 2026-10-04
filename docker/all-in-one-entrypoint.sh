#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "Notice: APP_KEY environment variable is missing. Auto-generating key for container execution..."
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
else
    export APP_KEY="${APP_KEY}"
fi

export MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-rootpassword}"
export DB_CONNECTION="mysql"
export DB_HOST="127.0.0.1"
export DB_PORT="3306"
export DB_DATABASE="${DB_DATABASE:-sarunis}"
export DB_USERNAME="${DB_USERNAME:-root}"
export DB_PASSWORD="${DB_PASSWORD:-$MYSQL_ROOT_PASSWORD}"
export CACHE_STORE="file"

if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql --auth-root-authentication-method=normal --skip-test-db >/dev/null
fi

su-exec mysql mariadbd --datadir=/var/lib/mysql --bind-address=127.0.0.1 --port=3306 --skip-networking=0 --socket=/run/mysqld/mysqld.sock &
MYSQL_PID="$!"

until mariadb-admin ping --host=127.0.0.1 --user=root --silent; do
    sleep 1
done

mariadb --host=127.0.0.1 --user=root <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL

if ! mariadb --host=127.0.0.1 --user=root --password="${MYSQL_ROOT_PASSWORD}" "${DB_DATABASE}" -e "SHOW TABLES" | grep -q .; then
    if [ -f /var/www/railway_dump.sql ]; then
        mariadb --host=127.0.0.1 --user=root --password="${MYSQL_ROOT_PASSWORD}" "${DB_DATABASE}" < /var/www/railway_dump.sql
    fi
fi

php artisan storage:link --force || true
php artisan config:clear || true
php artisan cache:clear || true
php artisan migrate --force
php artisan db:seed --class=InitialAdminSeeder --force

trap 'kill "$MYSQL_PID"; wait "$MYSQL_PID"' INT TERM

php artisan serve --host=0.0.0.0 --port=8000 &
APP_PID="$!"

wait "$APP_PID"
