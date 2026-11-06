#!/bin/sh
# Flip ERP container start: same boot steps as the Railway start command, then
# PHP-FPM + a once-a-minute scheduler + nginx in the foreground.
set -eu
cd /var/www/html

# Uploads volume is mounted on storage/app — make sure its layout exists.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "Waiting for the database..."
i=0
until php -r '
  $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "5432", getenv("DB_DATABASE"));
  new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
' 2>/dev/null; do
  i=$((i + 1)); [ "$i" -ge 60 ] && { echo "Database not reachable after 120s" >&2; exit 1; }
  sleep 2
done

[ -e public/storage ] || php artisan storage:link --no-interaction
# A restarted container keeps the previous config cache; clear it so seeders
# read the current environment (env() returns null while config is cached).
php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction
php artisan db:seed --class=RoleAndPermissionSeeder --force --no-interaction
php artisan db:seed --class=CurrencySeeder --force --no-interaction
php artisan db:seed --class=PlatformCompanySeeder --force --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
chown -R www-data:www-data storage bootstrap/cache

php-fpm -D

# Laravel scheduler (invoices:check-overdue runs daily) without a cron daemon.
( while :; do
    su -s /bin/sh -c 'php /var/www/html/artisan schedule:run --no-interaction >/dev/null 2>&1' www-data || true
    sleep 60
  done ) &

echo "Flip ERP ready on :8080"
exec nginx -g 'daemon off;'
