#!/usr/bin/env bash
set -euo pipefail

audit_project="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$audit_project"
audit_compose=(docker compose --env-file .env.example --project-name sigme-audit -f docker/compose.audit.yaml)

case "${1:-test}" in
  up)
    mkdir -p storage/framework/testing/audit/{mysql,private,sessions,views,cache,disks,evidence,build}
    "${audit_compose[@]}" up -d
    ;;
  test)
    shift || true
    "${audit_compose[@]}" exec -T -w /var/www/html -e APP_ENV=testing -e DB_DATABASE=sigme_testing -e LOG_CHANNEL=null app php vendor/bin/phpunit "$@"
    ;;
  stop)
    "${audit_compose[@]}" stop
    ;;
  *)
    echo 'Uso: bash scripts/wsl/audit.sh up|test [opções PHPUnit]|stop' >&2
    exit 2
    ;;
esac
