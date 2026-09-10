#!/bin/sh
set -eu
if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY ausente. Execute Instalar SIGME Docker.cmd.' >&2
    exit 1
fi
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/private storage/logs bootstrap/cache
exec "$@"
