#!/bin/sh
# Avvio del container: prepara storage, genera la chiave, migra il database, carica la scuola di esempio
# al primo avvio e avvia il worker di coda; poi lascia il posto al web server.
set -e
cd /app

mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

# APP_KEY: se non è passata, si genera al primo avvio e si conserva nel volume storage.
if [ -z "${APP_KEY:-}" ]; then
    [ -s storage/app/.app_key ] || php artisan key:generate --show > storage/app/.app_key
    APP_KEY="$(cat storage/app/.app_key)"
    export APP_KEY
fi

php artisan migrate --force

# Scuola di esempio (≈15 classi, 40 docenti): solo la prima volta, il marcatore resta nel volume storage.
if [ "${SEED_ESEMPIO:-1}" = "1" ] && [ ! -f storage/app/.seeded ]; then
    php artisan db:seed --force
    touch storage/app/.seeded
fi

php artisan config:cache
php artisan view:cache

# Password dell'amministratore di esempio (facoltativa) e avvio del worker di coda.
php docker/avvio.php || true

exec "$@"
