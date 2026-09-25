#!/usr/bin/env bash

set -euo pipefail

_SIGME_COMMON_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${_SIGME_COMMON_DIR}/../../.." && pwd)"
ENV_FILE="${PROJECT_DIR}/.env"

die() {
    printf 'ERRO: %s\n' "$*" >&2
    exit 1
}

info() {
    printf '[SIGME] %s\n' "$*"
}

require_wsl2() {
    grep -qi microsoft /proc/sys/kernel/osrelease || die 'Execute este script dentro do WSL 2.'
}

require_linux_filesystem() {
    case "${PROJECT_DIR}" in
        /mnt/*)
            die 'O projeto deve ficar no filesystem Linux, por exemplo ~/projetos/sigme.'
            ;;
    esac
}

require_ubuntu_2404() {
    . /etc/os-release

    if [[ "${ID:-}" == 'ubuntu' && "${VERSION_ID:-}" == '24.04' ]]; then
        return
    fi

    if [[ "${SIGME_ALLOW_UNSUPPORTED_HOST:-0}" == '1' ]]; then
        info "AVISO: host não oficial detectado: ${PRETTY_NAME:-desconhecido}."
        return
    fi

    die "O ambiente oficial exige Ubuntu 24.04 LTS; detectado: ${PRETTY_NAME:-desconhecido}."
}

require_docker() {
    command -v docker >/dev/null 2>&1 || die 'Docker não foi encontrado dentro da distribuição WSL.'
    docker info >/dev/null 2>&1 || die 'O Docker não está respondendo. Inicie o Docker Desktop e habilite a integração WSL.'
    docker compose version >/dev/null 2>&1 || die 'Docker Compose não está disponível.'
}

require_env_file() {
    [[ -f "${ENV_FILE}" ]] || die 'Arquivo .env ausente. Execute scripts/wsl/bootstrap.sh.'
}

read_env_value() {
    local key="$1"
    local line

    [[ -f "${ENV_FILE}" ]] || return 0

    while IFS= read -r line || [[ -n "${line}" ]]; do
        line="${line%$'\r'}"
        case "${line}" in
            "${key}="*)
                printf '%s' "${line#*=}"
                return 0
                ;;
        esac
    done < "${ENV_FILE}"
}

set_env_value() {
    local key="$1"
    local value="$2"
    local temporary_file
    local line
    local replaced=0

    temporary_file="$(mktemp "${ENV_FILE}.XXXXXX")"
    chmod 600 "${temporary_file}"

    while IFS= read -r line || [[ -n "${line}" ]]; do
        line="${line%$'\r'}"
        case "${line}" in
            "${key}="*)
                printf '%s=%s\n' "${key}" "${value}" >> "${temporary_file}"
                replaced=1
                ;;
            *)
                printf '%s\n' "${line}" >> "${temporary_file}"
                ;;
        esac
    done < "${ENV_FILE}"

    if [[ "${replaced}" -eq 0 ]]; then
        printf '%s=%s\n' "${key}" "${value}" >> "${temporary_file}"
    fi

    mv "${temporary_file}" "${ENV_FILE}"
    chmod 600 "${ENV_FILE}"
}

compose() {
    (
        cd "${PROJECT_DIR}"
        docker compose --env-file "${ENV_FILE}" "$@"
    )
}

sigme_http_healthy() {
    local port="${1:-8080}"
    local marker

    marker="$(curl --fail --silent --show-error --output /dev/null --write-out '%header{x-sigme-service}' "http://localhost:${port}/up" 2>/dev/null || true)"
    [[ "${marker}" == 'SIGME' ]]
}
