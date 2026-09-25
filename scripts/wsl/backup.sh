#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

readonly PBKDF2_ITERATIONS=600000

label='manual'
path_only=0

usage() {
    cat <<'EOF'
Uso: backup.sh [--label ROTULO] [--path-only]

Cria um backup criptografado do banco principal, uploads privados e .env.
O rótulo aceita letras minúsculas, números e hífen.
EOF
}

while [[ "$#" -gt 0 ]]; do
    case "$1" in
        --label)
            [[ "$#" -ge 2 ]] || die 'Informe um valor para --label.'
            label="$2"
            shift 2
            ;;
        --path-only)
            path_only=1
            shift
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            die "Opção desconhecida: $1"
            ;;
    esac
done

[[ "${label}" =~ ^[a-z0-9][a-z0-9-]{0,31}$ ]] ||
    die 'O rótulo deve conter somente letras minúsculas, números e hífen (máximo de 32 caracteres).'

umask 077
require_env_file
require_docker

compose_project="$(read_env_value COMPOSE_PROJECT_NAME)"
[[ "${compose_project}" =~ ^[a-z0-9][a-z0-9_-]{0,62}$ ]] ||
    die 'COMPOSE_PROJECT_NAME ausente ou inválido no .env.'

readonly COMPOSE_FILE="${PROJECT_DIR}/compose.yaml"
[[ -f "${COMPOSE_FILE}" ]] || die "Arquivo Compose ausente: ${COMPOSE_FILE}."

compose_explicit() {
    (
        cd "${PROJECT_DIR}"
        docker compose \
            --project-name "${compose_project}" \
            --project-directory "${PROJECT_DIR}" \
            --env-file "${ENV_FILE}" \
            --file "${COMPOSE_FILE}" \
            "$@"
    )
}

log() {
    if [[ "${path_only}" -eq 0 ]]; then
        printf '[SIGME BACKUP] %s\n' "$*" >&2
    fi
}

backup_dir="$(read_env_value SIGME_BACKUP_DIR)"
backup_dir="${backup_dir:-${HOME}/sigme-data/backups}"
upload_dir="$(read_env_value SIGME_UPLOAD_DIR)"
key_file="${SIGME_BACKUP_KEY_FILE:-${HOME}/.config/sigme/backup.key}"

[[ "${backup_dir}" == /* ]] || die 'SIGME_BACKUP_DIR deve ser um caminho absoluto.'
[[ -n "${upload_dir}" && "${upload_dir}" == /* ]] || die 'SIGME_UPLOAD_DIR deve ser um caminho absoluto.'
[[ -d "${upload_dir}" && ! -L "${upload_dir}" ]] || die "Diretório de uploads inválido: ${upload_dir}."
[[ -f "${key_file}" && ! -L "${key_file}" && -s "${key_file}" ]] ||
    die "Chave de backup ausente ou inválida: ${key_file}."

key_mode="$(stat --format='%a' "${key_file}")"
case "${key_mode}" in
    400|600) ;;
    *) die "A chave de backup deve usar permissão 600 ou 400; atual: ${key_mode}." ;;
esac

mkdir -p "${backup_dir}"
[[ -d "${backup_dir}" && ! -L "${backup_dir}" ]] || die "Diretório de backup inválido: ${backup_dir}."
chmod 700 "${backup_dir}"

running_services="$(compose_explicit ps --status running --services)"
grep --fixed-strings --line-regexp --quiet 'mysql' <<< "${running_services}" ||
    die 'O serviço MySQL precisa estar em execução para criar o backup.'

service_was_running() {
    grep --fixed-strings --line-regexp --quiet "$1" <<< "${running_services}"
}

app_was_running=0
queue_was_running=0
scheduler_was_running=0
maintenance_changed=0
runtime_restored=0

service_was_running 'laravel.test' && app_was_running=1
service_was_running 'queue' && queue_was_running=1
service_was_running 'scheduler' && scheduler_was_running=1

temporary_dir=''
partial_artifact=''
partial_checksum=''
artifact=''
checksum_file=''
backup_committed=0

restore_runtime() {
    local services_to_start=()

    if [[ "${queue_was_running}" -eq 1 ]]; then
        services_to_start+=('queue')
    fi

    if [[ "${scheduler_was_running}" -eq 1 ]]; then
        services_to_start+=('scheduler')
    fi

    if [[ "${#services_to_start[@]}" -gt 0 ]]; then
        compose_explicit start "${services_to_start[@]}" >/dev/null
    fi

    if [[ "${maintenance_changed}" -eq 1 && "${app_was_running}" -eq 1 ]]; then
        compose_explicit exec --no-TTY laravel.test php artisan up --no-interaction >/dev/null
    fi

    runtime_restored=1
}

cleanup() {
    local exit_code="$?"

    set +e

    if [[ "${runtime_restored}" -eq 0 ]]; then
        restore_runtime >/dev/null 2>&1
    fi

    if [[ -n "${temporary_dir}" && -d "${temporary_dir}" ]]; then
        rm -rf -- "${temporary_dir}"
    fi

    [[ -n "${partial_artifact}" ]] && rm -f -- "${partial_artifact}"
    [[ -n "${partial_checksum}" ]] && rm -f -- "${partial_checksum}"

    if [[ "${backup_committed}" -eq 0 ]]; then
        [[ -n "${artifact}" ]] && rm -f -- "${artifact}"
        [[ -n "${checksum_file}" ]] && rm -f -- "${checksum_file}"
    fi

    exit "${exit_code}"
}
trap cleanup EXIT INT TERM

if [[ "${app_was_running}" -eq 1 ]]; then
    if ! compose_explicit exec --no-TTY laravel.test sh -lc 'test -f storage/framework/down'; then
        log 'Colocando a aplicação em manutenção para manter banco e uploads coerentes.'
        compose_explicit exec --no-TTY laravel.test php artisan down --retry=60 --no-interaction >/dev/null
        maintenance_changed=1
    fi
fi

services_to_stop=()
[[ "${queue_was_running}" -eq 1 ]] && services_to_stop+=('queue')
[[ "${scheduler_was_running}" -eq 1 ]] && services_to_stop+=('scheduler')

if [[ "${#services_to_stop[@]}" -gt 0 ]]; then
    log 'Pausando fila e agendador durante a captura.'
    compose_explicit stop "${services_to_stop[@]}" >/dev/null
fi

timestamp="$(date --utc '+%Y%m%dT%H%M%SZ')"
candidate_artifact="${backup_dir}/sigme-${timestamp}-${label}.backup.enc"
candidate_checksum="${candidate_artifact}.sha256"
[[ ! -e "${candidate_artifact}" && ! -e "${candidate_checksum}" ]] ||
    die 'Já existe um backup com o mesmo instante e rótulo; tente novamente.'
artifact="${candidate_artifact}"
checksum_file="${candidate_checksum}"
partial_artifact="${artifact}.partial.$$"
partial_checksum="${checksum_file}.partial.$$"
temporary_dir="$(mktemp --directory "${backup_dir}/.sigme-backup-${timestamp}.XXXXXX")"
chmod 700 "${temporary_dir}"

log 'Gerando dump consistente do MySQL.'
compose_explicit exec --no-TTY mysql sh -lc \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump --user=root --single-transaction --quick --routines --triggers --events --hex-blob --default-character-set=utf8mb4 --no-tablespaces "$MYSQL_DATABASE"' |
    gzip --best > "${temporary_dir}/database.sql.gz"
gzip --test "${temporary_dir}/database.sql.gz"

if [[ -n "$(find "${upload_dir}" -type l -print -quit)" ]]; then
    die 'O diretório de uploads contém link simbólico; o backup foi interrompido por segurança.'
fi

log 'Empacotando uploads privados.'
tar --create --gzip --file "${temporary_dir}/uploads.tar.gz" --directory "${upload_dir}" .
tar --list --gzip --file "${temporary_dir}/uploads.tar.gz" >/dev/null

install --mode=600 "${ENV_FILE}" "${temporary_dir}/environment.env"

git_revision="$(git -C "${PROJECT_DIR}" rev-parse HEAD 2>/dev/null || printf 'unknown')"
upload_file_count="$(find "${upload_dir}" -type f -printf '.' | wc -c)"
upload_bytes="$(du --bytes --summarize "${upload_dir}" | awk '{print $1}')"

cat > "${temporary_dir}/METADATA.txt" <<EOF
FORMAT_VERSION=1
CREATED_AT_UTC=${timestamp}
COMPOSE_PROJECT=${compose_project}
GIT_REVISION=${git_revision}
UPLOAD_REGULAR_FILES=${upload_file_count}
UPLOAD_BYTES=${upload_bytes}
PBKDF2_ITERATIONS=${PBKDF2_ITERATIONS}
EOF

(
    cd "${temporary_dir}"
    sha256sum database.sql.gz uploads.tar.gz environment.env METADATA.txt > MANIFEST.sha256
)

log 'Criptografando o artefato com AES-256-CBC e PBKDF2.'
tar --create --gzip --file=- \
    --directory "${temporary_dir}" \
    database.sql.gz uploads.tar.gz environment.env METADATA.txt MANIFEST.sha256 |
    openssl enc \
        -aes-256-cbc \
        -salt \
        -pbkdf2 \
        -iter "${PBKDF2_ITERATIONS}" \
        -md sha256 \
        -pass "file:${key_file}" \
        -out "${partial_artifact}"

chmod 600 "${partial_artifact}"
mv -- "${partial_artifact}" "${artifact}"
partial_artifact=''

artifact_hash="$(sha256sum "${artifact}" | awk '{print $1}')"
printf '%s  %s\n' "${artifact_hash}" "$(basename "${artifact}")" > "${partial_checksum}"
chmod 600 "${partial_checksum}"
mv -- "${partial_checksum}" "${checksum_file}"
partial_checksum=''

log 'Verificando hash externo, descriptografia e manifesto interno.'
"${SCRIPT_DIR}/verify-backup.sh" "${artifact}" >/dev/null

restore_runtime
backup_committed=1

if [[ "${path_only}" -eq 0 ]]; then
    log "Backup verificado: ${artifact}"
fi

printf '%s\n' "${artifact}"
