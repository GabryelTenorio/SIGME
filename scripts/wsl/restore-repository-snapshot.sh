#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SNAPSHOT_DIR="${PROJECT_DIR}/database/snapshots"
SNAPSHOT_FILE="${SNAPSHOT_DIR}/sigme.sql.gz"
DATABASE_NAME='sigme'

[[ -f "${PROJECT_DIR}/.env" ]] || {
    echo 'Erro: execute ./scripts/wsl/bootstrap.sh antes da restauração.' >&2
    exit 1
}

[[ -f "${SNAPSHOT_FILE}" ]] || {
    echo "Erro: snapshot ausente em ${SNAPSHOT_FILE}." >&2
    exit 1
}

cd "${SNAPSHOT_DIR}"
sha256sum --check SHA256SUMS

echo
echo "Esta operação substituirá todos os dados do banco ${DATABASE_NAME}."
read -r -p 'Digite RESTAURAR para continuar: ' confirmation
[[ "${confirmation}" == 'RESTAURAR' ]] || {
    echo 'Restauração cancelada.'
    exit 1
}

cd "${PROJECT_DIR}"
docker compose stop laravel.test queue scheduler

restore_failed=1
restore_services() {
    if [[ "${restore_failed}" -ne 0 ]]; then
        echo 'A restauração falhou. Reiniciando os serviços para permitir diagnóstico.' >&2
        docker compose start laravel.test queue scheduler >/dev/null 2>&1 || true
    fi
}
trap restore_services EXIT

docker compose exec -T mysql sh -lc \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --execute="DROP DATABASE IF EXISTS sigme; CREATE DATABASE sigme CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'

gzip --decompress --stdout "${SNAPSHOT_FILE}" | \
    docker compose exec -T mysql sh -lc \
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root sigme'

docker compose start laravel.test queue scheduler
docker compose exec -T laravel.test php artisan migrate:status

restore_failed=0
trap - EXIT
echo 'Snapshot restaurado com sucesso.'
