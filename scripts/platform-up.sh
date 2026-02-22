#!/bin/bash

set -euo pipefail

PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"

usage() {
    cat <<'EOF'
Usage: ./scripts/platform-up.sh [--vite]

Starts the full Docker platform stack defined in compose.yaml:
- laravel.test (web app)
- pgsql (TimescaleDB)
- redis
- nats
- mailpit
- reverb
- iot-listen-states
- iot-listen-presence
- iot-ingest-telemetry
- horizon
- scheduler

Options:
  --vite    Also start Vite dev server inside laravel.test
EOF
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

include_vite=0

for arg in "$@"; do
    case "$arg" in
        --vite)
            include_vite=1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $arg" >&2
            usage
            exit 1
            ;;
    esac
done

script_dir="$(cd "${BASH_SOURCE[0]%/*}" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"
compose_file="$repo_root/compose.yaml"

if ! command_exists docker; then
    echo "Error: docker is required." >&2
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "Error: docker compose is required." >&2
    exit 1
fi

if [[ ! -f "$compose_file" ]]; then
    echo "Error: compose file not found at $compose_file" >&2
    exit 1
fi

echo "Starting Docker platform stack..."
(cd "$repo_root" && docker compose -f compose.yaml up -d)

if [[ "$include_vite" -eq 1 ]]; then
    echo "Starting Vite dev server in laravel.test..."
    (cd "$repo_root" && docker compose -f compose.yaml exec -d laravel.test sh -lc "npm run dev -- --host 0.0.0.0 --port ${VITE_PORT:-5173}")
fi

echo
echo "Platform status:"
(cd "$repo_root" && docker compose -f compose.yaml ps)

echo
echo "Platform is up."
