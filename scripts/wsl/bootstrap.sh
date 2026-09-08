#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

COMPOSER_IMAGE='composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c'

require_wsl2
require_linux_filesystem
require_ubuntu_2404
require_docker

DATA_DIR="${HOME}/sigme-data"
UPLOAD_DIR="${DATA_DIR}/uploads"
BACKUP_DIR="${DATA_DIR}/backups"
LOG_DIR="${DATA_DIR}/logs-operacionais"
CONFIG_DIR="${HOME}/.config/sigme"
BACKUP_KEY="${CONFIG_DIR}/backup.key"

info 'Preparando diretórios persistentes.'
mkdir -p \
    "${UPLOAD_DIR}" \
    "${BACKUP_DIR}" \
    "${DATA_DIR}/exports" \
    "${DATA_DIR}/restore-tests" \
    "${LOG_DIR}" \
    "${CONFIG_DIR}"
chmod 700 "${CONFIG_DIR}"

if [[ ! -f "${ENV_FILE}" ]]; then
    cp "${PROJECT_DIR}/.env.example" "${ENV_FILE}"
fi
chmod 600 "${ENV_FILE}"

if [[ -z "$(read_env_value DB_PASSWORD)" || "$(read_env_value DB_PASSWORD)" == 'password' ]]; then
    set_env_value DB_PASSWORD "$(openssl rand -hex 32)"
fi

if [[ -z "$(read_env_value DB_ROOT_PASSWORD)" ]]; then
    set_env_value DB_ROOT_PASSWORD "$(openssl rand -hex 32)"
fi

if [[ ! -f "${BACKUP_KEY}" ]]; then
    umask 077
    openssl rand -base64 48 > "${BACKUP_KEY}"
fi
chmod 600 "${BACKUP_KEY}"

set_env_value COMPOSE_PROJECT_NAME sigme
set_env_value APP_NAME SIGME
set_env_value APP_URL http://localhost:8080
set_env_value DB_CONNECTION mysql
set_env_value DB_HOST mysql
set_env_value DB_PORT 3306
set_env_value DB_DATABASE sigme
set_env_value DB_TEST_DATABASE sigme_testing
set_env_value DB_USERNAME sigme
set_env_value FILESYSTEM_DISK private-local
set_env_value QUEUE_CONNECTION database
set_env_value MAIL_MAILER smtp
set_env_value MAIL_HOST mailpit
set_env_value MAIL_PORT 1025
set_env_value SIGME_DATA_DIR "${DATA_DIR}"
set_env_value SIGME_UPLOAD_DIR "${UPLOAD_DIR}"
set_env_value SIGME_BACKUP_DIR "${BACKUP_DIR}"
set_env_value SIGME_LOG_DIR "${LOG_DIR}"

if [[ "$(id -u)" -eq 0 ]]; then
    set_env_value WWWUSER 1337
    set_env_value WWWGROUP 1000
    set_env_value SUPERVISOR_PHP_USER root
else
    set_env_value WWWUSER "$(id -u)"
    set_env_value WWWGROUP "$(id -g)"
    set_env_value SUPERVISOR_PHP_USER sail
fi

info 'Instalando dependências PHP a partir do composer.lock.'
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "${PROJECT_DIR}:/app" \
    --workdir /app \
    "${COMPOSER_IMAGE}" \
    composer install --no-interaction --prefer-dist --optimize-autoloader

compose config --quiet

info 'Construindo a imagem PHP 8.5 / Node 24.'
compose build

info 'Iniciando MySQL e Mailpit.'
compose up --detach mysql mailpit

for attempt in {1..60}; do
    if compose exec --no-TTY mysql sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping --host=127.0.0.1 --user=root --silent' >/dev/null 2>&1; then
        break
    fi

    if [[ "${attempt}" -eq 60 ]]; then
        die 'MySQL não ficou saudável dentro do prazo.'
    fi

    sleep 2
done

compose exec --no-TTY mysql sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --execute="CREATE DATABASE IF NOT EXISTS sigme_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON sigme_testing.* TO '\''sigme'\''@'\''%'\''; FLUSH PRIVILEGES;"'

if [[ -z "$(read_env_value APP_KEY)" ]]; then
    compose run --rm laravel.test php artisan key:generate --force --no-interaction
fi

[[ -f "${PROJECT_DIR}/package-lock.json" ]] || die 'package-lock.json ausente. O lock JavaScript deve ser versionado.'

info 'Instalando dependências JavaScript e compilando assets.'
compose run --rm laravel.test npm ci
compose run --rm laravel.test npm run build

info 'Executando migrations e o teste mínimo do ambiente.'
compose run --rm laravel.test php artisan migrate --force --no-interaction
compose run --rm laravel.test php artisan test --compact tests/Feature/HealthEndpointTest.php

compose up --detach

app_port="${APP_PORT:-$(read_env_value APP_PORT)}"
app_port="${app_port:-8080}"

for attempt in {1..60}; do
    if sigme_http_healthy "${app_port}"; then
        info "SIGME iniciado em http://localhost:${app_port}."
        info 'Mailpit disponível em http://localhost:8025.'
        info 'Backup criptografado ainda é uma etapa pendente desta fundação.'
        exit 0
    fi

    sleep 2
done

die 'A aplicação não respondeu com sucesso em /up dentro do prazo.'
