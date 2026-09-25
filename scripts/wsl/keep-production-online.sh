#!/usr/bin/env bash

set -u

app_container='sigme-v1-production-app-1'
ngrok_container='sigme-v1-production-ngrok-1'
local_health_url='http://127.0.0.1:8083/up'
fallback_public_health_url='https://densitometric-messily-hortense.ngrok-free.dev/up'
check_interval_seconds=30
failures_before_restart=3
restart_cooldown_seconds=300
log_file='/home/e-not-094/projetos/sigme/storage/logs/endpoint-watchdog.log'
lock_file='/run/lock/sigme-production-watchdog.lock'

public_failures=0
last_ngrok_restart=0
last_state=''

mkdir -p "$(dirname "${lock_file}")"
exec 9>"${lock_file}"
flock 9

mkdir -p "$(dirname "${log_file}")"
touch "${log_file}"

log() {
    printf '%s %s\n' "$(date --iso-8601=seconds)" "$*" >> "${log_file}"
}

report_state() {
    local state="$1"

    if [[ "${state}" != "${last_state}" ]]; then
        log "${state}"
        last_state="${state}"
    fi
}

container_is_running() {
    [[ "$(docker inspect --format '{{.State.Running}}' "$1" 2>/dev/null || true)" == 'true' ]]
}

public_health_url() {
    local app_url

    app_url="$({
        docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' "${app_container}" 2>/dev/null || true
    } | sed -n 's/^APP_URL=//p' | head -n 1)"

    if [[ "${app_url}" =~ ^https?:// ]]; then
        printf '%s/up' "${app_url%/}"
    else
        printf '%s' "${fallback_public_health_url}"
    fi
}

systemctl start docker
log 'Monitor do SIGME iniciado.'

while true; do
    if ! systemctl is-active --quiet docker; then
        docker_result="$(systemctl show docker --property=Result --value 2>/dev/null || true)"

        if [[ "${docker_result}" != 'success' ]]; then
            systemctl reset-failed docker >/dev/null 2>&1 || true
            systemctl start docker >/dev/null 2>&1 || true
            report_state 'Docker falhou; tentativa automática de recuperação executada.'
        else
            report_state 'Docker está parado manualmente; nenhuma recuperação foi executada.'
        fi

        sleep "${check_interval_seconds}"
        continue
    fi

    if ! container_is_running "${app_container}"; then
        public_failures=0
        report_state 'SIGME está parado; o monitor não iniciará o endpoint público.'
        sleep "${check_interval_seconds}"
        continue
    fi

    if ! curl --fail --silent --show-error --output /dev/null --max-time 10 "${local_health_url}"; then
        public_failures=0
        report_state 'SIGME está em execução, mas ainda não está saudável; aguardando.'
        sleep "${check_interval_seconds}"
        continue
    fi

    if ! container_is_running "${ngrok_container}"; then
        if docker start "${ngrok_container}" >/dev/null 2>&1; then
            log 'Container do ngrok estava parado e foi iniciado porque o SIGME está saudável.'
            last_ngrok_restart="$(date +%s)"
        else
            report_state 'Falha ao iniciar o container do ngrok.'
        fi

        public_failures=0
        sleep "${check_interval_seconds}"
        continue
    fi

    current_public_health_url="$(public_health_url)"

    if curl --fail --silent --show-error --output /dev/null --max-time 15 "${current_public_health_url}"; then
        public_failures=0
        report_state "SIGME e endpoint público estão saudáveis: ${current_public_health_url}"
    else
        public_failures=$((public_failures + 1))
        report_state "Endpoint público indisponível; falha consecutiva ${public_failures}/${failures_before_restart}."

        now="$(date +%s)"
        seconds_since_restart=$((now - last_ngrok_restart))

        if ((public_failures >= failures_before_restart && seconds_since_restart >= restart_cooldown_seconds)); then
            if docker restart "${ngrok_container}" >/dev/null 2>&1; then
                log 'Container do ngrok reiniciado após falhas consecutivas do endpoint público.'
                last_ngrok_restart="${now}"
            else
                log 'Falha ao reiniciar o container do ngrok.'
            fi

            public_failures=0
        fi
    fi

    sleep "${check_interval_seconds}"
done
