#!/bin/bash

set -euo pipefail

PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"

usage() {
    cat <<'EOF'
Usage: ./scripts/platform-down.sh

Stops and removes the Docker platform stack defined in compose.yaml.
EOF
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
fi

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

echo "Stopping Docker platform stack..."
(cd "$repo_root" && docker compose -f compose.yaml down)

echo
echo "Platform is down."
