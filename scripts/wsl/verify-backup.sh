#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

readonly PBKDF2_ITERATIONS=600000

usage() {
    cat <<'EOF'
Uso: verify-backup.sh CAMINHO_DO_BACKUP

Valida o SHA-256 externo, descriptografa em diretório temporário, confere o
manifesto SHA-256 interno e testa os arquivos do banco e dos uploads.
EOF
}

if [[ "$#" -ne 1 ]]; then
    usage >&2
    exit 1
fi

umask 077

artifact="$1"
[[ -f "${artifact}" && ! -L "${artifact}" ]] || die "Backup ausente ou inválido: ${artifact}."
artifact="$(realpath "${artifact}")"
checksum_file="${artifact}.sha256"
key_file="${SIGME_BACKUP_KEY_FILE:-${HOME}/.config/sigme/backup.key}"

[[ -f "${checksum_file}" && ! -L "${checksum_file}" ]] ||
    die "SHA-256 externo ausente: ${checksum_file}."
[[ -f "${key_file}" && ! -L "${key_file}" && -s "${key_file}" ]] ||
    die "Chave de backup ausente ou inválida: ${key_file}."

read -r expected_hash expected_name extra_fields < "${checksum_file}"
[[ "${expected_hash}" =~ ^[0-9a-f]{64}$ && -z "${extra_fields:-}" ]] ||
    die 'Arquivo de SHA-256 externo inválido.'
[[ "${expected_name}" == "$(basename "${artifact}")" ]] ||
    die 'O SHA-256 externo não corresponde ao nome do artefato informado.'

actual_hash="$(sha256sum "${artifact}" | awk '{print $1}')"
[[ "${actual_hash}" == "${expected_hash}" ]] || die 'O SHA-256 externo do backup não confere.'

temporary_dir="$(mktemp --directory "${TMPDIR:-/tmp}/sigme-backup-verify.XXXXXX")"
chmod 700 "${temporary_dir}"

cleanup() {
    local exit_code="$?"
    set +e
    rm -rf -- "${temporary_dir}"
    exit "${exit_code}"
}
trap cleanup EXIT INT TERM

payload="${temporary_dir}/payload.tar.gz"
content_dir="${temporary_dir}/content"
mkdir --mode=700 "${content_dir}"

openssl enc \
    -d \
    -aes-256-cbc \
    -pbkdf2 \
    -iter "${PBKDF2_ITERATIONS}" \
    -md sha256 \
    -pass "file:${key_file}" \
    -in "${artifact}" \
    -out "${payload}"

mapfile -t members < <(tar --list --gzip --file "${payload}")
[[ "${#members[@]}" -eq 5 ]] || die 'O pacote interno não contém exatamente os cinco arquivos esperados.'

seen_database=0
seen_uploads=0
seen_environment=0
seen_metadata=0
seen_manifest=0

for member in "${members[@]}"; do
    case "${member}" in
        database.sql.gz)
            [[ "${seen_database}" -eq 0 ]] || die "Membro duplicado no pacote interno: ${member}."
            seen_database=1
            ;;
        uploads.tar.gz)
            [[ "${seen_uploads}" -eq 0 ]] || die "Membro duplicado no pacote interno: ${member}."
            seen_uploads=1
            ;;
        environment.env)
            [[ "${seen_environment}" -eq 0 ]] || die "Membro duplicado no pacote interno: ${member}."
            seen_environment=1
            ;;
        METADATA.txt)
            [[ "${seen_metadata}" -eq 0 ]] || die "Membro duplicado no pacote interno: ${member}."
            seen_metadata=1
            ;;
        MANIFEST.sha256)
            [[ "${seen_manifest}" -eq 0 ]] || die "Membro duplicado no pacote interno: ${member}."
            seen_manifest=1
            ;;
        *)
            die "Membro inesperado no pacote interno: ${member}."
            ;;
    esac
done

while IFS= read -r listing_line; do
    [[ "${listing_line:0:1}" == '-' ]] || die 'O pacote interno contém entrada que não é arquivo regular.'
done < <(LC_ALL=C tar --list --verbose --gzip --file "${payload}")

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

gzip --test "${content_dir}/database.sql.gz"
tar --list --gzip --file "${content_dir}/uploads.tar.gz" >/dev/null

while IFS= read -r upload_member; do
    case "${upload_member}" in
        .|./|./*) ;;
        *) die "Caminho inválido no arquivo de uploads: ${upload_member}." ;;
    esac

    relative_member="${upload_member#./}"
    case "/${relative_member}/" in
        */../*) die "Travessia de diretório detectada nos uploads: ${upload_member}." ;;
    esac
done < <(tar --list --gzip --file "${content_dir}/uploads.tar.gz")

while IFS= read -r listing_line; do
    case "${listing_line:0:1}" in
        -|d) ;;
        *) die 'O arquivo de uploads contém entrada que não é arquivo regular nem diretório.' ;;
    esac
done < <(LC_ALL=C tar --list --verbose --gzip --file "${content_dir}/uploads.tar.gz")

grep --quiet '^FORMAT_VERSION=1$' "${content_dir}/METADATA.txt" || die 'Versão do formato de backup inválida.'
grep --quiet '^APP_KEY=.' "${content_dir}/environment.env" || die 'O .env preservado não contém APP_KEY.'
grep --quiet '^DB_DATABASE=.' "${content_dir}/environment.env" || die 'O .env preservado não contém DB_DATABASE.'

printf '[SIGME BACKUP] Backup íntegro e descriptografável: %s\n' "${artifact}"
