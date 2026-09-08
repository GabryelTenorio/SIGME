#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

require_wsl2
require_linux_filesystem
require_ubuntu_2404
require_env_file
require_docker

info 'Iniciando os serviços sem remover volumes.'
compose up --detach

app_port="${APP_PORT:-$(read_env_value APP_PORT)}"
app_port="${app_port:-8080}"

for attempt in {1..60}; do
    if sigme_http_healthy "${app_port}"; then
        info "SIGME saudável em http://localhost:${app_port}."
        exit 0
    fi

    sleep 2
done

compose ps
die 'O endpoint /up não respondeu com sucesso dentro do prazo.'
