#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

require_env_file
require_docker

compose ps

running_services="$(compose ps --status running --services)"

for required_service in laravel.test queue scheduler mysql mailpit; do
    grep --fixed-strings --line-regexp --quiet "${required_service}" <<< "${running_services}" ||
        die "Serviço obrigatório não está em execução: ${required_service}."
done

app_port="${APP_PORT:-$(read_env_value APP_PORT)}"
app_port="${app_port:-8080}"

if sigme_http_healthy "${app_port}"; then
    info 'Saúde HTTP: OK'
else
    info 'Saúde HTTP: indisponível'
    exit 1
fi
