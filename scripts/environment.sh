#!/usr/bin/env bash
# Shared preconditions only; Sail and Compose own the service lifecycle.
set +x
set -Eeuo pipefail
task_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd -- "$task_root"

fail() { printf >&2 '%s\n' "$1"; exit "${2:-3}"; }
value() {
    local setting="$1" line result='' count=0
    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" == "$setting="* ]]; then
            result="${line#*=}"; count=$((count+1))
        fi
    done < "$task_root/.env"
    [[ "$count" -le 1 ]] || fail "Duplicate configuration setting: $setting"
    result="${result#\"}"; result="${result%\"}"
    printf '%s' "$result"
}
fingerprint() { printf '%s' "$task_root" | sha256sum | cut -d ' ' -f 1; }
runtime_source() {
    {
        while IFS= read -r source; do
            [[ -f "$source" && ! -L "$source" ]] || fail 'Invalid runtime source.'
            printf '%s\0' "$source"; cat -- "$source"
        done < <(git ls-files --cached --others --exclude-standard docker/8.5 | LC_ALL=C sort -u)
        printf '%s' "$(id -g)"
    } | sha256sum | cut -d ' ' -f 1
}
sail_files() {
    local selection=compose.yaml
    if [[ -e .epr-local/compose.volumes.yaml ]]; then
        [[ ! -L .epr-local/compose.volumes.yaml && "$(stat -c %u .epr-local/compose.volumes.yaml)" == "$(id -u)" && "$(stat -c %a .epr-local/compose.volumes.yaml)" == 600 ]] || fail 'Invalid legacy volume overlay.'
        [[ "$(cat .epr-local/compose.volumes.yaml)" == $'volumes:\n    sail-pgsql:\n        labels: !override {}\n    sail-redis:\n        labels: !override {}' ]] || fail 'Unexpected legacy volume overlay content.'
        selection+=':.epr-local/compose.volumes.yaml'
    fi
    printf '%s' "$selection"
}
check_resources() {
    local container actual volume label ids role destination
    ids="$(docker ps -a -q --filter "label=com.docker.compose.project=$task_project")"
    for container in $ids; do
        actual="$(docker inspect --format '{{ index .Config.Labels "com.docker.compose.project.working_dir" }}' "$container")"
        [[ "$actual" == "$task_root" ]] || fail 'Docker resources belong to another clone.'
        actual="$(docker inspect --format '{{range $name, $network := .NetworkSettings.Networks}}{{$name}} {{end}}' "$container")"
        [[ -z "$actual" || "$actual" == "${task_project}_sail " ]] || fail 'Unexpected Docker network.'
        if [[ "$(docker inspect --format '{{ index .Config.Labels "com.docker.compose.service" }}' "$container")" == pgsql ]]; then
            actual="$(docker inspect --format '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql"}}{{.Name}}{{end}}{{end}}' "$container")"
            [[ "$actual" == "${task_project}_sail-pgsql" ]] || fail 'Actual PostgreSQL volume mismatch.'
            for role in BOOTSTRAP APP MIGRATOR BACKUP; do
                destination="/run/secrets/postgres_${role,,}_password"
                actual="$(docker inspect --format "{{range .Mounts}}{{if eq .Destination \"$destination\"}}{{.Source}}{{end}}{{end}}" "$container")"
                [[ "$actual" == "$(value "POSTGRES_${role}_PASSWORD_FILE")" ]] || fail 'Actual PostgreSQL credential mount mismatch.'
            done
        fi
    done
    if docker network inspect "${task_project}_sail" >/dev/null 2>&1; then
        actual="$(docker network inspect --format '{{ index .Labels "com.docker.compose.project" }}' "${task_project}_sail")"
        label="$(docker network inspect --format '{{ index .Labels "org.epr.root" }}' "${task_project}_sail")"
        [[ "$actual" == "$task_project" ]] || fail 'Foreign Docker network.'
        [[ "$label" == "$(fingerprint)" || ( ( -z "$label" || "$label" == '<no value>' ) && -n "$ids" ) ]] || fail 'Network ownership requires inspection.'
    fi
    for volume in "${task_project}_sail-pgsql" "${task_project}_sail-redis"; do
        if docker volume inspect "$volume" >/dev/null 2>&1; then
            [[ -f .env ]] || fail 'Existing volume without matching configuration; preserve it for inspection.'
            actual="$(docker volume inspect --format '{{ index .Labels "com.docker.compose.project" }}' "$volume")"
            label="$(docker volume inspect --format '{{ index .Labels "org.epr.root" }}' "$volume")"
            [[ "$actual" == "$task_project" ]] || fail 'Foreign volume; preserve it.'
            [[ "$label" == "$(fingerprint)" || ( ( -z "$label" || "$label" == '<no value>' || "$label" == legacy-c ) && -n "$ids" ) ]] || fail 'Volume ownership requires inspection.'
        fi
    done
}
check_configuration() {
    local selector expected role secret compose_version digest
    local -A credentials=() settings=()
    local line setting
    [[ -z "${DOCKER_HOST:-}" && ( -z "${DOCKER_CONTEXT:-}" || "$DOCKER_CONTEXT" == default ) ]] || fail 'Inherited Docker selector refused.'
    compose_version="$(docker compose version --short)"
    compose_version="${compose_version#v}"
    [[ "$compose_version" =~ ^([0-9]+)\.([0-9]+) ]] || fail 'Unrecognized Compose version.'
    (( BASH_REMATCH[1] > 2 || ( BASH_REMATCH[1] == 2 && BASH_REMATCH[2] >= 30 ) )) || fail 'Compose 2.30 or later required.'
    [[ "$(docker context show)" == default ]] || fail 'Unexpected Docker context.'
    for selector in .env .epr-local bootstrap/cache/config.php .epr-local/development.lock; do
        [[ ! -L "$selector" ]] || fail 'Symlinked configuration or state refused.'
    done
    [[ -d .epr-local && "$(stat -c %u .epr-local)" == "$(id -u)" && "$(stat -c %a .epr-local)" == 700 ]] || fail 'Private local state required.'
    [[ -f .env && "$(stat -c %u .env)" == "$(id -u)" && "$(stat -c %a .env)" == 600 ]] || fail 'A private .env owned by the current user is required.'
    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" =~ ^([A-Z][A-Z0-9_]*)= ]]; then
            setting="${BASH_REMATCH[1]}"
            [[ -z "${settings[$setting]:-}" ]] || fail "Duplicate configuration setting: $setting"
            settings[$setting]=1
        fi
    done < .env
    [[ ! -e bootstrap/cache/config.php && -z "${APP_CONFIG_CACHE:-}" ]] || fail 'Inspect the inherited Laravel configuration cache.'
    task_project="$(value COMPOSE_PROJECT_NAME)"
    [[ "$task_project" =~ ^epr-(a|b|c|test-[abc]-[a-z0-9][a-z0-9-]{0,39})$ ]] || fail 'Invalid instance identity.'
    [[ "$(value EPR_ROOT_FINGERPRINT)" == "$(fingerprint)" ]] || fail 'Configuration belongs to another root.'
    [[ "$(value EPR_INSTANCE)" =~ ^[abc]$ ]] || fail 'Invalid instance.'
    [[ "$task_project" == "epr-$(value EPR_INSTANCE)" || "$task_project" == "epr-test-$(value EPR_INSTANCE)-"* ]] || fail 'Inconsistent instance identity.'
    for selector in APP_ENV APP_DEBUG DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_SEARCH_PATH WWWUSER WWWGROUP; do
        case "$selector" in
            APP_ENV) expected=local;; APP_DEBUG) expected=false;; DB_CONNECTION) expected=pgsql;;
            DB_HOST) expected=pgsql;; DB_PORT) expected=5432;; DB_DATABASE) expected=laravel;;
            DB_USERNAME) expected=epr_app;; DB_SEARCH_PATH) expected=epr;;
            WWWUSER) expected="$(id -u)";; WWWGROUP) expected="$(id -g)";;
        esac
        [[ "$(value "$selector")" == "$expected" ]] || fail "Unexpected configuration: $selector"
        [[ -z "${!selector:-}" || "${!selector}" == "$expected" ]] || fail "Inherited selector refused: $selector"
    done
    for selector in DB_URL APP_CONFIG_CACHE; do
        [[ -z "$(value "$selector")" && -z "${!selector:-}" ]] || fail "Unexpected configuration: $selector"
    done
    [[ "$(value APP_URL)" == "http://$task_project.localhost:$(value APP_PORT)" ]] || fail 'Unexpected HTTP origin.'
    [[ "$(value SAIL_FILES)" == "$(sail_files)" ]] || fail 'Unexpected Sail file configuration.'
    [[ -z "${SAIL_FILES:-}" || "${SAIL_FILES}" == "$(sail_files)" ]] || fail 'Unexpected Sail file selection.'
    [[ -z "${SAIL_DOCKER_BINARY:-}" || "${SAIL_DOCKER_BINARY}" == scripts/docker-guard ]] || fail 'Unexpected Docker binary.'
    [[ -z "${COMPOSE_FILE:-}" && -z "${COMPOSE_PROFILES:-}" ]] || fail 'Inherited Compose selection refused.'
    [[ -z "${COMPOSE_PROJECT_NAME:-}" || "$COMPOSE_PROJECT_NAME" == "$task_project" ]] || fail 'Inherited project refused.'
    [[ "${APP_SERVICE:-laravel.test}" == laravel.test && "${APP_USER:-sail}" == sail ]] || fail 'Foreign Sail service or user.'
    for role in BOOTSTRAP APP MIGRATOR BACKUP; do
        secret="$(value "POSTGRES_${role}_PASSWORD_FILE")"
        [[ "$secret" == /* && "$(realpath -e -- "$secret")" == "$secret" && "$secret" != "$task_root/"* && -f "$secret" && ! -L "$secret" ]] || fail 'Secrets must be real files outside the clone.'
        [[ "$(stat -c %u "$(dirname -- "$secret")")" == "$(id -u)" && "$(stat -c %a "$(dirname -- "$secret")")" == 700 ]] || fail 'Private secret directory required.'
        [[ "$(tail -c 1 "$secret" | od -An -tx1 | tr -d ' ')" == 0a ]] || fail 'Invalid credential terminator.'
        digest="$(sha256sum "$secret" | cut -d ' ' -f 1)"
        [[ -z "${credentials[$digest]:-}" ]] || fail 'Database roles require distinct credentials.'
        credentials[$digest]=1
        [[ "$(wc -c < "$secret")" -eq 65 && "$(head -c 64 "$secret")" =~ ^[0-9a-f]{64}$ ]] || fail 'Invalid database credential.'
    done
    [[ "$(value DB_PASSWORD)" == "$(head -c 64 "$(value POSTGRES_APP_PASSWORD_FILE)")" ]] || fail 'Inconsistent application credential.'
    check_resources
}
