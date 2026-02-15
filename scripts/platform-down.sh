#!/bin/bash

set -euo pipefail

PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"

usage() {
    cat <<'EOF'
Usage: ./scripts/platform-down.sh
EOF
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

is_process_running() {
    local pid="${1:-}"

    if [[ -z "$pid" ]]; then
        return 1
    fi

    if [[ ! "$pid" =~ ^[0-9]+$ ]]; then
        return 1
    fi

    kill -0 "$pid" >/dev/null 2>&1
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
fi

script_dir="$(cd "${BASH_SOURCE[0]%/*}" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"
state_dir="$repo_root/storage/platform"
manifest_path="$state_dir/manifest"

summary_lines=()

stop_pid_file() {
    local pid_file="$1"
    local name
    local pid
    local status
    local attempts=15
    local i

    name="$(basename "$pid_file" .pid)"
    pid="$(tr -d '[:space:]' < "$pid_file")"

    if [[ ! "$pid" =~ ^[0-9]+$ ]]; then
        status="stale"
        rm -f "$pid_file"
        summary_lines+=("$name|$status|$pid")
        return
    fi

    if ! is_process_running "$pid"; then
        status="not-running"
        rm -f "$pid_file"
        summary_lines+=("$name|$status|$pid")
        return
    fi

    kill "$pid" >/dev/null 2>&1 || true

    for ((i = 0; i < attempts; i++)); do
        if ! is_process_running "$pid"; then
            break
        fi

        sleep 1
    done

    if is_process_running "$pid"; then
        kill -9 "$pid" >/dev/null 2>&1 || true
        sleep 1

        if is_process_running "$pid"; then
            status="failed"
        else
            status="killed"
        fi
    else
        status="stopped"
    fi

    rm -f "$pid_file"
    summary_lines+=("$name|$status|$pid")
}

if [[ -d "$state_dir" ]] && compgen -G "$state_dir/*.pid" > /dev/null; then
    for pid_file in "$state_dir"/*.pid; do
        stop_pid_file "$pid_file"
    done
else
    echo "No managed PID files found."
fi

rm -f "$manifest_path"

nats_status="skipped"
if command_exists docker && docker compose version >/dev/null 2>&1; then
    if (cd "$repo_root" && docker compose -f docker-compose.nats.yml down); then
        nats_status="stopped"
    else
        nats_status="failed"
    fi
else
    nats_status="missing-docker"
fi

echo
echo "Platform shutdown summary:"
if [[ "${#summary_lines[@]}" -eq 0 ]]; then
    echo "  - no managed processes were found"
else
    for line in "${summary_lines[@]}"; do
        IFS='|' read -r name status pid <<< "$line"
        printf '  - %-16s %-12s pid=%s\n' "$name" "$status" "$pid"
    done
fi
printf '  - %-16s %-12s\n' "nats-container" "$nats_status"

if [[ "$nats_status" == "failed" ]]; then
    exit 1
fi

echo
echo "Platform is down."
