#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

readonly PBKDF2_ITERATIONS=600000

usage() {
    cat <<'EOF'
Uso: restore-test.sh CAMINHO_DO_BACKUP

Restaura o dump em um banco temporário isolado, executa CHECK TABLE e extrai
os uploads em uma área temporária. O banco principal sigme não é alterado.
EOF
}

if [[ "$#" -ne 1 ]]; then
    usage >&2
    exit 1
fi

umask 077
require_env_file
require_docker

artifact="$1"
"${SCRIPT_DIR}/verify-backup.sh" "${artifact}" >/dev/null
artifact="$(realpath "${artifact}")"

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

running_services="$(compose_explicit ps --status running --services)"
grep --fixed-strings --line-regexp --quiet 'mysql' <<< "${running_services}" ||
    die 'O serviço MySQL precisa estar em execução para o ensaio de restauração.'

main_database="$(read_env_value DB_DATABASE)"
[[ "${main_database}" =~ ^[A-Za-z0-9_]+$ ]] || die 'DB_DATABASE ausente ou inválido no .env.'

test_suffix="$(date --utc '+%Y%m%d%H%M%S')_$$"
test_database="sigme_restore_test_${test_suffix}"
[[ "${test_database}" != "${main_database}" ]] || die 'O banco temporário não pode ser o banco principal.'

key_file="${SIGME_BACKUP_KEY_FILE:-${HOME}/.config/sigme/backup.key}"
data_dir="$(read_env_value SIGME_DATA_DIR)"
data_dir="${data_dir:-${HOME}/sigme-data}"
[[ "${data_dir}" == /* ]] || die 'SIGME_DATA_DIR deve ser um caminho absoluto.'

restore_test_root="${data_dir}/restore-tests"
mkdir -p "${restore_test_root}"
chmod 700 "${restore_test_root}"
temporary_dir="$(mktemp --directory "${restore_test_root}/.restore-test-${test_suffix}.XXXXXX")"
chmod 700 "${temporary_dir}"
database_created=0

drop_test_database() {
    compose_explicit exec --no-TTY \
        --env "SIGME_TEST_DATABASE=${test_database}" \
        mysql sh -lc \
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --execute="DROP DATABASE IF EXISTS \`$SIGME_TEST_DATABASE\`"' \
        >/dev/null
}

cleanup() {
    local exit_code="$?"

    set +e
    if [[ "${database_created}" -eq 1 ]]; then
        drop_test_database
    fi
    rm -rf -- "${temporary_dir}"
    exit "${exit_code}"
}
trap cleanup EXIT INT TERM

payload="${temporary_dir}/payload.tar.gz"
content_dir="${temporary_dir}/content"
uploads_dir="${temporary_dir}/uploads"
mkdir --mode=700 "${content_dir}" "${uploads_dir}"

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

printf '[SIGME RESTORE TEST] Criando banco temporário %s.\n' "${test_database}"
compose_explicit exec --no-TTY \
    --env "SIGME_TEST_DATABASE=${test_database}" \
    mysql sh -lc \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --execute="CREATE DATABASE \`$SIGME_TEST_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"'
database_created=1

gzip --decompress --stdout "${content_dir}/database.sql.gz" |
    compose_explicit exec --no-TTY \
        --env "SIGME_TEST_DATABASE=${test_database}" \
        mysql sh -lc \
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root "$SIGME_TEST_DATABASE"'

check_output="$(
    compose_explicit exec --no-TTY \
        --env "SIGME_TEST_DATABASE=${test_database}" \
        mysql sh -lc '
            set -eu
            tables="$(MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --batch --skip-column-names "$SIGME_TEST_DATABASE" --execute="SHOW TABLES")"
            [ -n "$tables" ]
            for table in $tables; do
                MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --batch --skip-column-names "$SIGME_TEST_DATABASE" --execute="CHECK TABLE \`$table\`"
            done
        '
)"

if ! awk -F '\t' '
    $3 == "status" { checked += 1; if ($4 != "OK") bad += 1 }
    END { exit !(checked > 0 && bad == 0) }
' <<< "${check_output}"; then
    die 'CHECK TABLE falhou no banco temporário.'
fi

table_count="$(
    compose_explicit exec --no-TTY \
        --env "SIGME_TEST_DATABASE=${test_database}" \
        mysql sh -lc \
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --batch --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '\''$SIGME_TEST_DATABASE'\''"'
)"

migration_count="$(
    compose_explicit exec --no-TTY \
        --env "SIGME_TEST_DATABASE=${test_database}" \
        mysql sh -lc \
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --batch --skip-column-names "$SIGME_TEST_DATABASE" --execute="SELECT COUNT(*) FROM migrations"'
)"

[[ "${table_count}" =~ ^[1-9][0-9]*$ ]] || die 'O banco temporário não contém tabelas.'
[[ "${migration_count}" =~ ^[1-9][0-9]*$ ]] || die 'O banco temporário não contém histórico de migrations.'

tar \
    --extract \
    --gzip \
    --file "${content_dir}/uploads.tar.gz" \
    --directory "${uploads_dir}" \
    --no-same-owner \
    --no-same-permissions

expected_upload_files="$(sed -n 's/^UPLOAD_REGULAR_FILES=//p' "${content_dir}/METADATA.txt")"
restored_upload_files="$(find "${uploads_dir}" -type f -printf '.' | wc -c)"
[[ "${expected_upload_files}" =~ ^[0-9]+$ ]] || die 'Contagem de uploads ausente ou inválida nos metadados.'
[[ "${restored_upload_files}" == "${expected_upload_files}" ]] ||
    die 'A quantidade de uploads restaurados não corresponde ao backup.'

drop_test_database
database_created=0

printf '[SIGME RESTORE TEST] Ensaio aprovado: %s tabelas, %s migrations e %s uploads.\n' \
    "${table_count}" \
    "${migration_count}" \
    "${restored_upload_files}"
