#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "${SCRIPT_DIR}/lib/common.sh"

require_env_file
require_docker

info 'Parando os serviços e preservando volumes, uploads e configurações.'
compose stop
