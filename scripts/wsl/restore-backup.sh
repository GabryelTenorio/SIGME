#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

readonly PBKDF2_ITERATIONS=600000

usage() {
    cat <<'EOF'
Uso: restore-backup.sh CAMINHO_DO_BACKUP

Substitui o banco principal e os uploads após confirmação textual exata.
Antes de qualquer substituição, cria e verifica um backup pré-restauração.
O .env do artefato é validado, mas não substitui automaticamente o .env atual.
EOF
}

if [[ "$#" -ne 1 ]]; then
    usage >&2
    exit 1
fi

[[ -t 0 ]] || die 'A restauração principal exige um terminal interativo.'

umask 077
require_env_file
require_docker

artifact="$1"
"${SCRIPT_DIR}/verify-backup.sh" "${artifact}" >/dev/null
artifact="$(realpath "${artifact}")"

compose_project="$(read_env_value COMPOSE_PROJECT_NAME)"
[[ "${compose_project}" =~ ^[a-z0-9][a-z0-9_-]{0,62}$ ]] ||
    die 'COMPOSE_PROJECT_NAME ausente ou inválido no .env.'

target_database="$(read_env_value DB_DATABASE)"
[[ "${target_database}" =~ ^[A-Za-z0-9_]+$ ]] || die 'DB_DATABASE ausente ou inválido no .env.'
[[ "${target_database}" != sigme_restore_test_* ]] || die 'O banco principal não pode usar o prefixo reservado de ensaio.'

upload_dir="$(read_env_value SIGME_UPLOAD_DIR)"
[[ -n "${upload_dir}" && "${upload_dir}" == /* ]] || die 'SIGME_UPLOAD_DIR deve ser um caminho absoluto.'

case "${upload_dir}" in
    /|/home|"${HOME}"|/var|/srv|/opt|/mnt|"${PROJECT_DIR}")
        die "Diretório de uploads amplo demais para restauração: ${upload_dir}."
        ;;
esac

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

running_services="$(compose_explicit ps --status running --services)"
grep --fixed-strings --line-regexp --quiet 'mysql' <<< "${running_services}" ||
    die 'O serviço MySQL precisa estar em execução para a restauração.'

service_was_running() {
    grep --fixed-strings --line-regexp --quiet "$1" <<< "${running_services}"
}

confirmation="RESTAURAR ${compose_project}:${target_database}"
printf 'ATENÇÃO: esta operação substituirá o banco %s e os uploads em %s.\n' \
    "${target_database}" \
    "${upload_dir}"
printf 'Um backup pré-restauração será criado primeiro.\n'
printf 'Digite exatamente "%s" para continuar: ' "${confirmation}"
IFS= read -r answer
[[ "${answer}" == "${confirmation}" ]] || die 'Restauração cancelada.'

printf '[SIGME RESTORE] Criando backup pré-restauração.\n'
pre_restore_backup="$("${SCRIPT_DIR}/backup.sh" --label pre-restore --path-only)"
"${SCRIPT_DIR}/verify-backup.sh" "${pre_restore_backup}" >/dev/null
printf '[SIGME RESTORE] Backup pré-restauração verificado: %s\n' "${pre_restore_backup}"

key_file="${SIGME_BACKUP_KEY_FILE:-${HOME}/.config/sigme/backup.key}"
temporary_dir="$(mktemp --directory "${TMPDIR:-/tmp}/sigme-main-restore.XXXXXX")"
chmod 700 "${temporary_dir}"
payload="${temporary_dir}/payload.tar.gz"
content_dir="${temporary_dir}/content"
mkdir --mode=700 "${content_dir}"

restore_started=0
restore_succeeded=0
replacement_upload_dir=''
previous_upload_dir=''

cleanup() {
    local exit_code="$?"

    set +e

    if [[ "${restore_started}" -eq 1 && "${restore_succeeded}" -eq 0 ]]; then
        compose_explicit stop laravel.test queue scheduler >/dev/null 2>&1
        printf '[SIGME RESTORE] Falha após o início da substituição. Aplicação, fila e agendador permaneceram parados.\n' >&2
        printf '[SIGME RESTORE] Backup pré-restauração preservado em: %s\n' "${pre_restore_backup}" >&2
        if [[ -n "${previous_upload_dir}" && -d "${previous_upload_dir}" ]]; then
            printf '[SIGME RESTORE] Uploads anteriores preservados em: %s\n' "${previous_upload_dir}" >&2
        fi
    fi

    if [[ -n "${replacement_upload_dir}" && -d "${replacement_upload_dir}" ]]; then
        rm -rf -- "${replacement_upload_dir}"
    fi

    rm -rf -- "${temporary_dir}"
    exit "${exit_code}"
}
trap cleanup EXIT INT TERM

openssl enc \
    -d \
    -aes-256-cbc \
    -pbkdf2 \
    -iter "${PBKDF2_ITERATIONS}" \
    -md sha256 \
    -pass "file:${key_file}" \
    -in "${artifact}" \
    -out "${payload}"

tar \
    --extract \
    --gzip \
    --file "${payload}" \
    --directory "${content_dir}" \
    --no-same-owner \
    --no-same-permissions

(
    cd "${content_dir}"
    sha256sum --check MANIFEST.sha256 >/dev/null
)

read_env_file_value() {
    local source_file="$1"
    local key="$2"
    local line

    while IFS= read -r line || [[ -n "${line}" ]]; do
        line="${line%$'\r'}"
        case "${line}" in
            "${key}="*)
                printf '%s' "${line#*=}"
                return 0
                ;;
        esac
    done < "${source_file}"
}

current_app_key="$(read_env_value APP_KEY)"
backup_app_key="$(read_env_file_value "${content_dir}/environment.env" APP_KEY)"
[[ -n "${current_app_key}" && "${backup_app_key}" == "${current_app_key}" ]] ||
    die 'A APP_KEY do backup difere da instalação atual. Use o procedimento de recuperação de desastre; nada foi substituído.'

upload_parent="$(dirname "${upload_dir}")"
mkdir -p "${upload_parent}"
[[ -d "${upload_parent}" && ! -L "${upload_parent}" ]] || die 'Diretório pai dos uploads inválido.'

replacement_upload_dir="$(mktemp --directory "${upload_parent}/.sigme-upload-restore.XXXXXX")"
chmod 700 "${replacement_upload_dir}"
tar \
    --extract \
    --gzip \
    --file "${content_dir}/uploads.tar.gz" \
    --directory "${replacement_upload_dir}" \
    --no-same-owner \
    --no-same-permissions

expected_upload_files="$(sed -n 's/^UPLOAD_REGULAR_FILES=//p' "${content_dir}/METADATA.txt")"
replacement_upload_files="$(find "${replacement_upload_dir}" -type f -printf '.' | wc -c)"
[[ "${expected_upload_files}" =~ ^[0-9]+$ ]] || die 'Contagem de uploads ausente ou inválida nos metadados.'
[[ "${replacement_upload_files}" == "${expected_upload_files}" ]] ||
    die 'A quantidade de uploads preparada não corresponde ao backup.'

services_to_restart=()
service_was_running 'laravel.test' && services_to_restart+=('laravel.test')
service_was_running 'queue' && services_to_restart+=('queue')
service_was_running 'scheduler' && services_to_restart+=('scheduler')

printf '[SIGME RESTORE] Parando aplicação, fila e agendador.\n'
compose_explicit stop laravel.test queue scheduler >/dev/null
restore_started=1

printf '[SIGME RESTORE] Recriando e importando o banco autorizado.\n'
compose_explicit exec --no-TTY \
    --env "SIGME_TARGET_DATABASE=${target_database}" \
    mysql sh -lc \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --execute="DROP DATABASE IF EXISTS \`$SIGME_TARGET_DATABASE\`; CREATE DATABASE \`$SIGME_TARGET_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"'

gzip --decompress --stdout "${content_dir}/database.sql.gz" |
    compose_explicit exec --no-TTY \
        --env "SIGME_TARGET_DATABASE=${target_database}" \
        mysql sh -lc \
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root "$SIGME_TARGET_DATABASE"'

check_output="$(
    compose_explicit exec --no-TTY \
        --env "SIGME_TARGET_DATABASE=${target_database}" \
        mysql sh -lc '
            set -eu
            tables="$(MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --batch --skip-column-names "$SIGME_TARGET_DATABASE" --execute="SHOW TABLES")"
            [ -n "$tables" ]
            for table in $tables; do
                MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --batch --skip-column-names "$SIGME_TARGET_DATABASE" --execute="CHECK TABLE \`$table\`"
            done
        '
)"

if ! awk -F '\t' '
    $3 == "status" { checked += 1; if ($4 != "OK") bad += 1 }
    END { exit !(checked > 0 && bad == 0) }
' <<< "${check_output}"; then
    die 'CHECK TABLE falhou após importar o banco principal.'
fi

timestamp="$(date --utc '+%Y%m%dT%H%M%SZ')"
if [[ -e "${upload_dir}" ]]; then
    [[ -d "${upload_dir}" && ! -L "${upload_dir}" ]] || die 'Destino atual dos uploads não é um diretório seguro.'
    previous_upload_dir="${upload_parent}/.sigme-uploads-before-${timestamp}-$$"
    mv -- "${upload_dir}" "${previous_upload_dir}"
fi

mv -- "${replacement_upload_dir}" "${upload_dir}"
replacement_upload_dir=''

if [[ "${#services_to_restart[@]}" -gt 0 ]]; then
    printf '[SIGME RESTORE] Reiniciando apenas os serviços que estavam ativos.\n'
    compose_explicit start "${services_to_restart[@]}" >/dev/null
fi

restore_succeeded=1

printf '[SIGME RESTORE] Restauração concluída e CHECK TABLE aprovado.\n'
printf '[SIGME RESTORE] Backup pré-restauração: %s\n' "${pre_restore_backup}"
if [[ -n "${previous_upload_dir}" ]]; then
    printf '[SIGME RESTORE] Cópia dos uploads anteriores preservada em: %s\n' "${previous_upload_dir}"
fi
