# syntax=docker/dockerfile:1
#
# Immagine unica di Orario Scuola: PHP + web server (FrankenPHP/Caddy), assets già compilati
# e solver Python (OR-Tools). Si avvia con `docker compose up -d --build` (vedi compose.yaml).

# 1) Assets (Vite + Tailwind)
FROM node:22-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# 2) Dipendenze PHP. Si installano anche quelle di sviluppo: i seeder della scuola di esempio usano Faker.
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-scripts --ignore-platform-reqs

# 3) Immagine finale (Debian bookworm: Python 3.11, quello richiesto dal solver)
FROM dunglas/frankenphp:1-php8.4-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-venv \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_mysql gd zip intl bcmath pcntl mbstring opcache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app

# Solver: l'ambiente virtuale sta dove lo cerca SolverRunner (solver/.venv). pytest serve solo allo sviluppo.
COPY solver/requirements.txt solver/requirements.txt
RUN grep -v '^pytest' solver/requirements.txt > /tmp/requirements.txt \
    && python3 -m venv solver/.venv \
    && solver/.venv/bin/pip install --no-cache-dir -r /tmp/requirements.txt

COPY . .
COPY --from=vendor /build/vendor vendor
COPY --from=assets /build/public/build public/build
COPY docker/Caddyfile /etc/caddy/Caddyfile
RUN chmod +x docker/entrypoint.sh && php artisan package:discover --ansi

# Il worker di coda lo avvia l'applicazione con `php artisan`: gli serve il PHP da riga di comando.
ENV PHP_BINARY=/usr/local/bin/php \
    SERVER_NAME=:80

EXPOSE 80
ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
